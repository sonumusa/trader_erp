<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Document numbering service.
 *
 * One series per (company, document_type). Format:
 *   [branch-code-]PREFIX[-fy]-NNNNNN
 * examples:  SI-26-000001        (prefix + financial year + padded number)
 *            LHR-SI-26-000001    (branch code enabled)
 *
 * Generation is concurrency-safe: the series row is locked FOR UPDATE inside
 * a transaction so two simultaneous requests can never receive the same number.
 */
final class DocumentNumberService
{
    /** Catalogue of known document types → default prefix. */
    public const TYPES = [
        'sales_quotation'    => 'SQ',
        'sales_order'        => 'SO',
        'sales_invoice'      => 'SI',
        'sales_return'       => 'SR',
        'purchase_quotation' => 'PQ',
        'purchase_order'     => 'PO',
        'purchase_invoice'   => 'PI',
        'purchase_return'    => 'PR',
        'journal_entry'      => 'JV',
        'cash_payment'       => 'CPV',
        'bank_payment'       => 'BPV',
        'cash_receipt'       => 'CRV',
        'bank_receipt'       => 'BRV',
        'contra_voucher'     => 'CV',
        'stock_transfer'     => 'ST',
        'stock_adjustment'   => 'SA',
    ];

    /**
     * Generate (and consume) the next document number for a type.
     * The caller must persist the returned number on the document.
     *
     * @throws \RuntimeException when no active series exists
     */
    public static function next(string $type, ?int $companyId = null, ?int $branchId = null): string
    {
        $companyId = $companyId ?? CompanyContextService::currentCompanyId();
        if ($companyId === null) {
            throw new \RuntimeException('No company context is active.');
        }

        return Database::transaction(function () use ($type, $companyId, $branchId) {
            $row = Database::row(
                'SELECT * FROM document_number_series
                 WHERE company_id = ? AND document_type = ? AND is_active = 1
                 ORDER BY id LIMIT 1 FOR UPDATE',
                [$companyId, $type]
            );
            if ($row === null) {
                throw new \RuntimeException(
                    'No document numbering series is configured for ' . str_replace('_', ' ', $type) . '.'
                );
            }

            $number = (int) $row['next_number'];
            $parts = [];

            if ((int) $row['include_branch_code'] === 1) {
                $code = self::branchCode($branchId, $companyId);
                if ($code !== '') {
                    $parts[] = $code;
                }
            }

            $parts[] = $row['prefix'];

            if ((int) $row['include_financial_year'] === 1) {
                $fy = FinancialYearService::current($companyId);
                if ($fy !== null) {
                    $parts[] = date('y', strtotime((string) $fy['start_date']));
                }
            }

            $length = max(1, (int) $row['number_length']);
            $parts[] = str_pad((string) $number, $length, '0', STR_PAD_LEFT);

            Database::execute(
                'UPDATE document_number_series
                 SET next_number = ?, last_used_number = ?, updated_at = ?
                 WHERE id = ?',
                [$number + 1, $number, date('Y-m-d H:i:s'), (int) $row['id']]
            );

            return implode('-', $parts);
        });
    }

    /** All series for a company (for the numbering settings screen). */
    public static function all(?int $companyId = null): array
    {
        $companyId = $companyId ?? CompanyContextService::currentCompanyId();
        if ($companyId === null) {
            return [];
        }
        return Database::query(
            'SELECT s.*, b.code AS branch_code
             FROM document_number_series s
             LEFT JOIN branches b ON b.id = s.branch_id
             WHERE s.company_id = ?
             ORDER BY s.document_type',
            [$companyId]
        );
    }

    public static function countFor(int $companyId): int
    {
        return (int) Database::value(
            'SELECT COUNT(*) FROM document_number_series WHERE company_id = ?',
            [$companyId]
        );
    }

    public static function series(int $id): ?array
    {
        return Database::row('SELECT * FROM document_number_series WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * Update a series' formatting settings. Never lets next_number move
     * backwards below last_used_number (that would duplicate numbers).
     */
    public static function update(int $id, array $data): array
    {
        $row = self::series($id);
        if ($row === null) {
            throw new \RuntimeException('Numbering series not found.');
        }

        $lastUsed = (int) $row['last_used_number'];
        $next = (int) ($data['next_number'] ?? $row['next_number']);
        if ($next <= $lastUsed) {
            throw new \RuntimeException(
                'The next number must be greater than the last used number (' . $lastUsed . ') to avoid duplicates.'
            );
        }

        Database::execute(
            'UPDATE document_number_series
             SET prefix = ?, include_branch_code = ?, include_financial_year = ?,
                 starting_number = ?, next_number = ?, number_length = ?, is_active = ?, updated_at = ?
             WHERE id = ?',
            [
                $data['prefix'],
                !empty($data['include_branch_code']) ? 1 : 0,
                !empty($data['include_financial_year']) ? 1 : 0,
                (int) $data['starting_number'],
                $next,
                max(1, (int) $data['number_length']),
                !empty($data['is_active']) ? 1 : 0,
                date('Y-m-d H:i:s'),
                $id,
            ]
        );
        return self::series($id) ?? $row;
    }

    /**
     * Reset a series — an authorized, audited operation.
     * Rules:
     *   * requires the reset permission (checked by the controller)
     *   * never silently overwrites: new next_number must be > last_used_number,
     *     unless the series has never issued a number (last_used_number = 0)
     */
    public static function reset(int $id, int $newNext): array
    {
        $row = self::series($id);
        if ($row === null) {
            throw new \RuntimeException('Numbering series not found.');
        }

        $lastUsed = (int) $row['last_used_number'];

        if ($lastUsed > 0 && $newNext <= $lastUsed) {
            throw new \RuntimeException(
                'Cannot reset to ' . $newNext . ' — the last number already issued was ' . $lastUsed
                . '. Choose a value greater than it, otherwise documents would get duplicate numbers.'
            );
        }
        if ($lastUsed === 0 && $newNext < (int) $row['starting_number']) {
            throw new \RuntimeException('The new next number cannot be below the starting number.');
        }

        Database::execute(
            'UPDATE document_number_series SET next_number = ?, updated_at = ? WHERE id = ?',
            [$newNext, date('Y-m-d H:i:s'), $id]
        );

        return self::series($id) ?? $row;
    }

    /** Configure a series for a company (used when creating a new company). */
    public static function createDefaults(int $companyId): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO document_number_series
                (company_id, document_type, prefix, include_branch_code, include_financial_year,
                 starting_number, next_number, last_used_number, number_length, is_active, created_at, updated_at)
             VALUES (?, ?, ?, 0, 1, 1, 1, 0, 6, 1, ?, ?)'
        );
        foreach (self::TYPES as $type => $prefix) {
            $stmt->execute([$companyId, $type, $prefix, $now, $now]);
        }
        return count(self::TYPES);
    }

    private static function branchCode(?int $branchId, int $companyId): string
    {
        if ($branchId !== null) {
            return (string) Database::value('SELECT code FROM branches WHERE id = ? AND deleted_at IS NULL', [$branchId], '');
        }
        return (string) Database::value(
            'SELECT code FROM branches WHERE company_id = ? AND is_head_office = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1',
            [$companyId],
            ''
        );
    }
}
