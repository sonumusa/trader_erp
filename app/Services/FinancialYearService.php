<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Financial year service — current/active FY detection, activation and closing.
 */
final class FinancialYearService
{
    /** The FY that governs the current period for a company. */
    public static function current(?int $companyId = null): ?array
    {
        $companyId = $companyId ?? CompanyContextService::currentCompanyId();
        if ($companyId === null) {
            return null;
        }

        // 1. Explicitly active, open FY
        $fy = Database::row(
            'SELECT * FROM financial_years
             WHERE company_id = ? AND is_active = 1 AND is_closed = 0
             ORDER BY start_date DESC LIMIT 1',
            [$companyId]
        );
        if ($fy !== null) {
            return $fy;
        }

        // 2. FY containing today
        $fy = Database::row(
            'SELECT * FROM financial_years
             WHERE company_id = ? AND ? BETWEEN start_date AND end_date
             ORDER BY id DESC LIMIT 1',
            [$companyId, date('Y-m-d')]
        );
        if ($fy !== null) {
            return $fy;
        }

        // 3. Most recent
        return Database::row(
            'SELECT * FROM financial_years WHERE company_id = ? ORDER BY id DESC LIMIT 1',
            [$companyId]
        );
    }

    /** Activate one FY for a company (deactivates the others). */
    public static function activate(int $fyId, int $companyId): void
    {
        Database::execute(
            'UPDATE financial_years SET is_active = 0, updated_at = ? WHERE company_id = ? AND id != ?',
            [date('Y-m-d H:i:s'), $companyId, $fyId]
        );
        Database::execute(
            'UPDATE financial_years SET is_active = 1, updated_at = ? WHERE id = ? AND company_id = ?',
            [date('Y-m-d H:i:s'), $fyId, $companyId]
        );
    }

    /** Close (lock) an FY. */
    public static function close(int $fyId): void
    {
        Database::execute(
            'UPDATE financial_years SET is_closed = 1, updated_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $fyId]
        );
    }

    /** Reopen a closed FY. */
    public static function reopen(int $fyId): void
    {
        Database::execute(
            'UPDATE financial_years SET is_closed = 0, updated_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $fyId]
        );
    }

    /** Suggest a default name for a date range, e.g. 2026-2027. */
    public static function suggestName(string $start, string $end): string
    {
        return date('Y', strtotime($start)) . '-' . date('Y', strtotime($end));
    }

    /** Default fiscal start (1 July) and end (30 June next year) — Pakistan convention. */
    public static function defaultsForYear(int $year): array
    {
        return [
            'start' => $year . '-07-01',
            'end'   => ($year + 1) . '-06-30',
        ];
    }
}
