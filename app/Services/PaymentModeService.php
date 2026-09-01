<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Payment mode service.
 *
 * A payment mode carries the account that should be auto-selected when the
 * user picks it (Cash → Cash in Hand, Meezan Bank → Meezan account, ...).
 * is_cash / is_bank also drive the voucher type mapping in later phases
 * (Cash vs Bank Receipt/Payment).
 */
final class PaymentModeService
{
    /** All active modes for a company, default Cash first. */
    public static function all(?int $companyId = null, bool $activeOnly = true): array
    {
        $companyId = $companyId ?? CompanyContextService::currentCompanyId();
        if ($companyId === null) {
            return [];
        }
        $sql = 'SELECT pm.*, a.name AS account_name, a.code AS account_code
                FROM payment_modes pm
                LEFT JOIN accounts a ON a.id = pm.account_id
                WHERE pm.company_id = ? AND pm.deleted_at IS NULL';
        $params = [$companyId];
        if ($activeOnly) {
            $sql .= ' AND pm.is_active = 1';
        }
        $sql .= ' ORDER BY pm.code = \'CASH\' DESC, pm.name';
        return Database::query($sql, $params);
    }

    public static function get(int $id): ?array
    {
        return Database::row(
            'SELECT pm.*, a.name AS account_name, a.code AS account_code
             FROM payment_modes pm
             LEFT JOIN accounts a ON a.id = pm.account_id
             WHERE pm.id = ? AND pm.deleted_at IS NULL',
            [$id]
        );
    }

    /** The account the engine should use for a payment mode. */
    public static function accountId(int $modeId): ?int
    {
        $id = Database::value('SELECT account_id FROM payment_modes WHERE id = ? AND deleted_at IS NULL', [$modeId]);
        return $id !== null ? (int) $id : null;
    }

    public static function create(int $companyId, array $data): int
    {
        Database::execute(
            'INSERT INTO payment_modes (company_id, name, code, account_id, is_cash, is_bank, is_system, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)',
            [
                $companyId,
                $data['name'],
                strtoupper((string) $data['code']),
                !empty($data['account_id']) ? (int) $data['account_id'] : null,
                !empty($data['is_cash']) ? 1 : 0,
                !empty($data['is_bank']) ? 1 : 0,
                !empty($data['is_active']) ? 1 : 0,
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s'),
            ]
        );
        return (int) Database::lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::execute(
            'UPDATE payment_modes
             SET name = ?, code = ?, account_id = ?, is_cash = ?, is_bank = ?, is_active = ?, updated_at = ?
             WHERE id = ?',
            [
                $data['name'],
                strtoupper((string) $data['code']),
                !empty($data['account_id']) ? (int) $data['account_id'] : null,
                !empty($data['is_cash']) ? 1 : 0,
                !empty($data['is_bank']) ? 1 : 0,
                !empty($data['is_active']) ? 1 : 0,
                date('Y-m-d H:i:s'),
                $id,
            ]
        );
    }

    /** System modes (Cash etc.) cannot be deleted — only deactivated. */
    public static function canDelete(int $id): array
    {
        $mode = self::get($id);
        if (!$mode) {
            return [false, 'Payment mode not found.'];
        }
        if ((int) $mode['is_system'] === 1) {
            return [false, 'This is a system payment mode. Deactivate it instead of deleting.'];
        }
        return [true, 'OK'];
    }

    public static function destroy(int $id): void
    {
        Database::execute(
            'UPDATE payment_modes SET deleted_at = ?, is_active = 0, updated_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id]
        );
    }

    public static function count(?int $companyId = null): int
    {
        $companyId = $companyId ?? CompanyContextService::currentCompanyId();
        return (int) Database::value(
            'SELECT COUNT(*) FROM payment_modes WHERE company_id = ? AND deleted_at IS NULL',
            [$companyId]
        );
    }
}
