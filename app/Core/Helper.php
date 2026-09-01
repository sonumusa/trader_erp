<?php

declare(strict_types=1);

namespace app\Core;

/**
 * Miscellaneous helpers: flash helpers, number/date formatting, slugs.
 * Global functions are auto-loaded by the bootstrap.
 */
final class Helper
{
    /** Human-friendly exception chain message (for logging). */
    public static function exceptionText(\Throwable $e): string
    {
        $out = get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        $prev = $e->getPrevious();
        while ($prev !== null) {
            $out .= "\n  caused by: " . get_class($prev) . ': ' . $prev->getMessage() . ' in ' . $prev->getFile() . ':' . $prev->getLine();
            $prev = $prev->getPrevious();
        }
        return $out;
    }

    public static function randomToken(int $length = 32): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }

    public static function slug(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim((string) $text, '-');
    }
}

/* ------------------------------------------------------------------ *
 * Global helper functions
 * ------------------------------------------------------------------ */
if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }
}
