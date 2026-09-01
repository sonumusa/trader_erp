<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;
use app\Core\Session;

/**
 * Company & branch context service.
 *
 * The active company (and branch) live in the session; the user's chosen
 * company is remembered in users.default_company_id so it survives logins.
 */
final class CompanyContextService
{
    public static function defaultCompanyId(): ?int
    {
        return Database::value('SELECT id FROM companies WHERE deleted_at IS NULL ORDER BY id LIMIT 1');
    }

    public static function currentCompanyId(): ?int
    {
        $id = Session::get('company_id');
        if ($id !== null && Database::value('SELECT id FROM companies WHERE id = ? AND deleted_at IS NULL', [$id])) {
            return (int) $id;
        }
        $default = self::defaultCompanyId();
        if ($default !== null) {
            Session::set('company_id', $default);
        }
        return $default;
    }

    /** @return array<string,mixed>|null */
    public static function currentCompany(): ?array
    {
        $id = self::currentCompanyId();
        return $id ? Database::row('SELECT * FROM companies WHERE id = ? LIMIT 1', [$id]) : null;
    }

    /** @return array<int,array<string,mixed>> */
    public static function allCompanies(): array
    {
        return Database::query('SELECT id, name, code FROM companies WHERE deleted_at IS NULL ORDER BY name');
    }

    /** Switch the active company and remember it on the user's profile. */
    public static function switchCompany(int $companyId, ?int $userId = null): bool
    {
        $exists = Database::value('SELECT id FROM companies WHERE id = ? AND deleted_at IS NULL', [$companyId]);
        if (!$exists) {
            return false;
        }
        Session::set('company_id', $companyId);
        // Reset branch context to the new company's head office.
        $ho = self::headOffice($companyId);
        Session::set('branch_id', $ho ? (int) $ho['id'] : null);

        $userId = $userId ?? AuthService::id();
        if ($userId !== null) {
            Database::execute('UPDATE users SET default_company_id = ? WHERE id = ?', [$companyId, $userId]);
        }
        return true;
    }

    public static function headOffice(?int $companyId = null): ?array
    {
        $companyId = $companyId ?? self::currentCompanyId();
        if ($companyId === null) {
            return null;
        }
        return Database::row(
            'SELECT * FROM branches WHERE company_id = ? AND is_head_office = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1',
            [$companyId]
        );
    }

    public static function currentBranchId(): ?int
    {
        $id = Session::get('branch_id');
        if ($id !== null && Database::value('SELECT id FROM branches WHERE id = ? AND deleted_at IS NULL', [$id])) {
            return (int) $id;
        }
        $ho = self::headOffice();
        if ($ho !== null) {
            Session::set('branch_id', (int) $ho['id']);
            return (int) $ho['id'];
        }
        return null;
    }

    /** @return array<string,mixed>|null */
    public static function currentBranch(): ?array
    {
        $id = self::currentBranchId();
        return $id ? Database::row('SELECT * FROM branches WHERE id = ? LIMIT 1', [$id]) : null;
    }

    public static function switchBranch(int $branchId): bool
    {
        $companyId = self::currentCompanyId();
        $exists = Database::value(
            'SELECT id FROM branches WHERE id = ? AND company_id = ? AND deleted_at IS NULL',
            [$branchId, $companyId]
        );
        if (!$exists) {
            return false;
        }
        Session::set('branch_id', $branchId);
        return true;
    }

    /** @return array<int,array<string,mixed>> branches of the current company */
    public static function branches(?int $companyId = null): array
    {
        $companyId = $companyId ?? self::currentCompanyId();
        if ($companyId === null) {
            return [];
        }
        return Database::query(
            'SELECT * FROM branches WHERE company_id = ? AND deleted_at IS NULL ORDER BY is_head_office DESC, name',
            [$companyId]
        );
    }

    public static function currentFinancialYear(): ?array
    {
        return FinancialYearService::current();
    }
}
