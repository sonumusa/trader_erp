<?php

declare(strict_types=1);

/**
 * Global shortcut helpers used across the application.
 * These wrap the Database/Session/View singletons for concise, safe code.
 */

use app\Core\Database;
use app\Core\Session;

if (!function_exists('db_query')) {
    function db_query(string $sql, array $params = []): array
    {
        return Database::query($sql, $params);
    }
}

if (!function_exists('db_row')) {
    function db_row(string $sql, array $params = []): ?array
    {
        return Database::row($sql, $params);
    }
}

if (!function_exists('db_value')) {
    function db_value(string $sql, array $params = [], mixed $default = null): mixed
    {
        return Database::value($sql, $params, $default);
    }
}

if (!function_exists('db_execute')) {
    function db_execute(string $sql, array $params = []): int
    {
        return Database::execute($sql, $params);
    }
}

if (!function_exists('db_last_insert_id')) {
    function db_last_insert_id(): string
    {
        return Database::lastInsertId();
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('today')) {
    function today(): string
    {
        return date('Y-m-d');
    }
}

if (!function_exists('format_money')) {
    function format_money(float|int|string|null $amount, int $decimals = 2): string
    {
        return number_format((float) $amount, $decimals);
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, string $format = 'd-M-Y'): string
    {
        if (!$date) {
            return '—';
        }
        $ts = strtotime($date);
        return $ts === false ? '—' : date($format, $ts);
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $datetime): string
    {
        return format_date($datetime, 'd-M-Y h:i A');
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }
}

if (!function_exists('session_set')) {
    function session_set(string $key, mixed $value): void
    {
        Session::set($key, $value);
    }
}

if (!function_exists('session_get')) {
    function session_get(string $key, mixed $default = null): mixed
    {
        return Session::get($key, $default);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        return Session::get('_old_input.' . $key, $default);
    }
}
