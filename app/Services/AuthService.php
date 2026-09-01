<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;
use app\Core\Session;
use app\Models\User;

/**
 * Authentication service — login, logout, current user, session security.
 */
final class AuthService
{
    private static ?array $cachedUser = null;

    public static function attempt(string $email, string $password, string $ip): bool
    {
        // Throttle: reject early when locked out
        if (SecurityService::isLocked($email, $ip)) {
            self::recordActivity(null, 'login_locked', $ip);
            return false;
        }

        $user = Database::row(
            'SELECT * FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1',
            [$email]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            self::recordActivity(isset($user['id']) ? (int) $user['id'] : null, 'failed_login', $ip);
            SecurityService::recordAttempt($email, $ip, false);
            return false;
        }

        if ($user['status'] !== 'active') {
            SecurityService::recordAttempt($email, $ip, false);
            return false;
        }

        Session::regenerate(true);
        Session::set('user_id', (int) $user['id']);
        SecurityService::clearFailures($email, $ip);

        self::$cachedUser = $user;

        Database::execute(
            'UPDATE users SET last_login_at = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), (int) $user['id']]
        );
        self::recordActivity((int) $user['id'], 'login', $ip);

        return true;
    }

    public static function check(): bool
    {
        return Session::get('user_id') !== null && self::user() !== null;
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        $id = Session::get('user_id');
        if ($id === null) {
            return null;
        }
        if (self::$cachedUser !== null && (int) self::$cachedUser['id'] === (int) $id) {
            return self::$cachedUser;
        }
        $user = Database::row(
            'SELECT u.*, r.name AS role_name, r.slug AS role_slug
             FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.deleted_at IS NULL AND u.status = \'active\' LIMIT 1',
            [(int) $id]
        );
        if ($user === null) {
            Session::forget('user_id');
            return null;
        }
        self::$cachedUser = $user;
        return $user;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? (int) $user['id'] : null;
    }

    /** Active company context (session-bound; company switcher comes in Phase 2). */
    public static function companyId(): ?int
    {
        return Session::get('company_id') !== null ? (int) Session::get('company_id') : null;
    }

    public static function logout(): void
    {
        $id = self::id();
        if ($id) {
            self::recordActivity($id, 'logout', $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        }
        self::$cachedUser = null;
        Session::destroy();
    }

    public static function isSuperAdmin(): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        return (int) ($user['is_super_admin'] ?? 0) === 1
            || ($user['role_slug'] ?? '') === 'super-admin';
    }

    public static function recordActivity(?int $userId, string $event, string $ip): void
    {
        try {
            Database::execute(
                'INSERT INTO user_activity (user_id, event, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?)',
                [$userId, $event, $ip, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), date('Y-m-d H:i:s')]
            );
        } catch (\Throwable) {
            // Activity logging must never break authentication.
        }
    }
}
