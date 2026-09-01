<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Audit trail service — records every important action.
 * Used for security reviews and the audit trail shown on documents.
 */
final class AuditService
{
    public static function log(
        string $action,
        string $module,
        ?string $documentType = null,
        ?int $documentId = null,
        ?string $description = null,
        ?int $userId = null,
    ): void {
        $userId = $userId ?? AuthService::id();
        $companyId = AuthService::companyId();

        try {
            Database::execute(
                'INSERT INTO audit_logs
                    (user_id, company_id, action, module, document_type, document_id, description, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $userId,
                    $companyId,
                    $action,
                    $module,
                    $documentType,
                    $documentId,
                    $description !== null ? mb_substr($description, 0, 500) : null,
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Throwable) {
            // Logging must never break the primary operation.
        }
    }

    /** Record a change-history entry (old vs new value). */
    public static function change(
        string $module,
        int $documentId,
        string $field,
        mixed $oldValue,
        mixed $newValue,
    ): void {
        if ((string) $oldValue === (string) $newValue) {
            return;
        }
        try {
            Database::execute(
                'INSERT INTO change_history
                    (user_id, module, document_type, document_id, field_name, old_value, new_value, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    AuthService::id(),
                    $module,
                    $module,
                    $documentId,
                    $field,
                    mb_substr((string) $oldValue, 0, 500),
                    mb_substr((string) $newValue, 0, 500),
                    date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Throwable) {
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 25, ?string $module = null): array
    {
        $sql = 'SELECT a.*, u.name AS user_name
                FROM audit_logs a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE 1=1';
        $params = [];
        if ($module) {
            $sql .= ' AND a.module = ?';
            $params[] = $module;
        }
        $sql .= ' ORDER BY a.id DESC LIMIT ' . (int) $limit;
        return Database::query($sql, $params);
    }

    /** @return array<int,array<string,mixed>> */
    public static function forDocument(string $module, int $documentId): array
    {
        return Database::query(
            'SELECT a.*, u.name AS user_name
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE a.module = ? AND a.document_id = ?
             ORDER BY a.id ASC',
            [$module, $documentId]
        );
    }

    public static function changesFor(string $module, int $documentId): array
    {
        return Database::query(
            'SELECT c.*, u.name AS user_name
             FROM change_history c
             LEFT JOIN users u ON u.id = c.user_id
             WHERE c.module = ? AND c.document_id = ?
             ORDER BY c.id ASC',
            [$module, $documentId]
        );
    }
}
