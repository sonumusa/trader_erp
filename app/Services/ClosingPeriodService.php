<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Closing period service (spec §31).
 *
 * "Closed To Date": all transactions on or before the date are locked.
 * Normal users cannot create/edit/delete/cancel documents inside the closed
 * period. Authorized roles (settings.closing.override) may bypass — every
 * override is audited.
 *
 * Enforced by document services via guardDate() at the start of every
 * create/edit/cancel operation.
 */
final class ClosingPeriodService
{
    /** The closed-to date for a company (or null = nothing closed). */
    public static function closedTo(?int $companyId = null): ?string
    {
        $companyId = $companyId ?? CompanyContextService::currentCompanyId();
        if ($companyId === null) {
            return null;
        }
        $date = Database::value(
            'SELECT closed_to FROM closing_periods WHERE company_id = ? LIMIT 1',
            [$companyId]
        );
        return $date !== null && $date !== '' ? (string) $date : null;
    }

    /** Whether a date is inside the closed period. */
    public static function isLocked(string $date, ?int $companyId = null): bool
    {
        $closedTo = self::closedTo($companyId);
        return $closedTo !== null && $date <= $closedTo;
    }

    /**
     * The core guard: throws for normal users when $date is inside the
     * closed period; authorized users pass (override) and the bypass is
     * recorded in the audit log.
     */
    public static function guardDate(string $date, string $action, string $module): void
    {
        if (!self::isLocked($date)) {
            return;
        }

        if (PermissionService::can('settings', 'closing', 'override')) {
            AuditService::log(
                'closing_override',
                $module,
                null,
                null,
                sprintf(
                    'Authorized override: %s allowed on %s (closed-to %s)',
                    $action,
                    $date,
                    self::closedTo()
                )
            );
            return;
        }

        throw new \RuntimeException(sprintf(
            'This transaction date (%s) is inside the closing period (closed to %s). ' .
            'Only an authorized user can override the closing period.',
            $date,
            self::closedTo()
        ));
    }

    /** Set the closed-to date (null clears it). Audit + permission in controller. */
    public static function set(?string $date, ?int $companyId = null, ?int $userId = null): void
    {
        $companyId = $companyId ?? CompanyContextService::currentCompanyId();
        $userId = $userId ?? AuthService::id();
        $now = date('Y-m-d H:i:s');

        $existing = Database::row('SELECT id FROM closing_periods WHERE company_id = ? LIMIT 1', [$companyId]);
        if ($existing) {
            Database::execute(
                'UPDATE closing_periods SET closed_to = ?, set_by = ?, set_at = ?, updated_at = ? WHERE id = ?',
                [$date, $userId, $now, $now, (int) $existing['id']]
            );
        } else {
            Database::execute(
                'INSERT INTO closing_periods (company_id, closed_to, set_by, set_at, updated_at)
                 VALUES (?, ?, ?, ?, ?)',
                [$companyId, $date, $userId, $now, $now]
            );
        }
    }
}
