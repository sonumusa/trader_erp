<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Secure self-service password reset (spec §44).
 *
 * Flow:
 *   1. request(email) — issues a single-use token (sha256 hash stored),
 *      expires in 30 minutes, and attempts to email the reset link.
 *      A generic response is always shown (anti-enumeration).
 *   2. reset(email, token, newPassword) — verifies the hash + expiry + unused,
 *      updates the password, marks the token used, logs the audit entry.
 */
final class PasswordResetService
{
    private const TTL = 1800; // 30 minutes

    /**
     * Issue a reset token for an email.
     * @return string the plain token (used by the mailer; never stored)
     */
    public static function request(string $email): string
    {
        $user = Database::row('SELECT id, email FROM users WHERE email = ? AND deleted_at IS NULL AND status = \'active\'', [$email]);
        if (!$user) {
            // Return a token anyway so the generic response is identical
            // (anti-enumeration); it simply won't be usable.
            return bin2hex(random_bytes(32));
        }

        // Invalidate any previous unused tokens
        Database::execute(
            'UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL',
            [date('Y-m-d H:i:s'), (int) $user['id']]
        );

        $token = bin2hex(random_bytes(32));
        Database::execute(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?)',
            [(int) $user['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + self::TTL), date('Y-m-d H:i:s')]
        );

        return $token;
    }

    /** Verify and apply a reset. Throws with user-friendly messages. */
    public static function reset(string $email, string $token, string $newPassword): void
    {
        if (mb_strlen($newPassword) < (int) config('security.password_min_length', 8)) {
            throw new \RuntimeException('The new password must be at least ' . (int) config('security.password_min_length', 8) . ' characters.');
        }

        $user = Database::row('SELECT id FROM users WHERE email = ? AND deleted_at IS NULL AND status = \'active\'', [$email]);
        if (!$user) {
            throw new \RuntimeException('Invalid or expired reset link.');
        }

        $row = Database::row(
            'SELECT * FROM password_resets
             WHERE user_id = ? AND token_hash = ? AND used_at IS NULL AND expires_at >= ?
             ORDER BY id DESC LIMIT 1',
            [(int) $user['id'], hash('sha256', $token), date('Y-m-d H:i:s')]
        );
        if (!$row) {
            throw new \RuntimeException('Invalid or expired reset link.');
        }

        Database::execute(
            'UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?',
            [password_hash($newPassword, PASSWORD_DEFAULT), date('Y-m-d H:i:s'), (int) $user['id']]
        );
        Database::execute('UPDATE password_resets SET used_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), (int) $row['id']]);

        AuditService::log('password_reset', 'users', 'user', (int) $user['id'], 'Password reset via self-service link');
    }

    /** Build the reset URL for the email body. */
    public static function resetUrl(string $email, string $token): string
    {
        $base = rtrim((string) config('app.url', ''), '/');
        if ($base === '') {
            $base = \app\Core\Request::instance()->baseUrl();
        }
        return $base . '/forgot-password?email=' . urlencode($email) . '&token=' . urlencode($token);
    }
}
