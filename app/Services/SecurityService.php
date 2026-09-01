<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Login throttling — DB-backed lockout after repeated failures (spec §44).
 * Config: security.login_max_attempts / security.login_lock_minutes.
 */
final class SecurityService
{
    /** Whether the (email, ip) pair is currently locked out. */
    public static function isLocked(string $email, string $ip): bool
    {
        $max = (int) config('security.login_max_attempts', 5);
        $window = (int) config('security.login_lock_minutes', 15);

        $since = date('Y-m-d H:i:s', time() - $window * 60);
        $failures = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND ip_address = ? AND successful = 0 AND attempted_at >= ?',
            [$email, $ip, $since]
        );
        return $failures >= $max;
    }

    public static function recordAttempt(string $email, string $ip, bool $successful): void
    {
        Database::execute(
            'INSERT INTO login_attempts (email, ip_address, successful, attempted_at) VALUES (?, ?, ?, ?)',
            [$email, $ip, $successful ? 1 : 0, date('Y-m-d H:i:s')]
        );
    }

    /** Clear failure history after a successful login. */
    public static function clearFailures(string $email, string $ip): void
    {
        Database::execute(
            'DELETE FROM login_attempts WHERE email = ? AND ip_address = ?',
            [$email, $ip]
        );
    }
}
