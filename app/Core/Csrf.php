<?php

declare(strict_types=1);

namespace app\Core;

/**
 * CSRF protection — token generated per session, injected into every form,
 * validated by the CsrfMiddleware on all state-changing requests.
 */
final class Csrf
{
    public static function token(): string
    {
        if (!Session::has('_csrf_token')) {
            Session::set('_csrf_token', bin2hex(random_bytes(32)));
        }
        return (string) Session::get('_csrf_token');
    }

    public static function regenerate(): void
    {
        Session::set('_csrf_token', bin2hex(random_bytes(32)));
    }

    public static function validate(?string $token): bool
    {
        $expected = self::token();
        if ($token === null || $token === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }
}
