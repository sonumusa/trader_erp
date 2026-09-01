<?php

declare(strict_types=1);

namespace app\Core;

/**
 * Session manager — strict cookie parameters, regeneration on privilege
 * change, idle timeout and an authenticated-user flag.
 */
final class Session
{
    private static bool $booted = false;

    public static function boot(array $cfg): void
    {
        if (self::$booted || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $lifetime = (int) ($cfg['lifetime'] ?? 7200);

        session_name($cfg['name'] ?? 'tradeerp_session');
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'secure'   => (bool) ($cfg['secure'] ?? false),
            'httponly' => (bool) ($cfg['httponly'] ?? true),
            'samesite' => $cfg['samesite'] ?? 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_start();

        // Idle timeout
        $inactive = (int) ($cfg['inactive'] ?? 1800);
        $last = (int) ($_SESSION['_last_activity'] ?? 0);
        if ($last > 0 && (time() - $last) > $inactive) {
            self::destroy();
            session_start();
        }
        $_SESSION['_last_activity'] = time();

        // Absolute lifetime
        $born = (int) ($_SESSION['_created_at'] ?? time());
        if ($born > 0 && (time() - $born) > $lifetime) {
            self::destroy();
            session_start();
        }
        $_SESSION['_created_at'] = $born;

        self::$booted = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    /** @return array<string,string> */
    public static function pullFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }

    public static function regenerate(bool $destroy = false): void
    {
        session_regenerate_id($destroy);
        $_SESSION['_last_activity'] = time();
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::$booted = false;
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        self::$booted = false;
    }
}
