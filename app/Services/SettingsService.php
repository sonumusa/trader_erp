<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Settings service — typed get/set for the global settings table
 * (system-wide configuration, not per-company).
 */
final class SettingsService
{
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        $value = $all[$key] ?? null;
        if ($value === null) {
            return $default;
        }
        // Auto-typing: booleans and numbers stored as strings
        if ($value === 'true' || $value === '1' && is_bool($default)) {
            return true;
        }
        if ($value === 'false' || $value === '0' && is_bool($default)) {
            return false;
        }
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $value = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        $exists = Database::row('SELECT id FROM settings WHERE `key` = ? LIMIT 1', [$key]);
        if ($exists) {
            Database::execute('UPDATE settings SET `value` = ?, updated_at = ? WHERE `key` = ?', [$value, date('Y-m-d H:i:s'), $key]);
        } else {
            Database::execute(
                'INSERT INTO settings (`key`, `value`, `group_name`, created_at, updated_at) VALUES (?, ?, ?, ?, ?)',
                [$key, $value, 'general', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
            );
        }
        self::$cache = null;
    }

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $rows = Database::query('SELECT `key`, `value` FROM settings');
        $out = [];
        foreach ($rows as $row) {
            $out[$row['key']] = $row['value'];
        }
        self::$cache = $out;
        return $out;
    }

    /** @return array<int,array<string,mixed>> grouped settings for the UI */
    public static function grouped(): array
    {
        $rows = Database::query('SELECT * FROM settings ORDER BY group_name, id');
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['group_name']][] = $row;
        }
        return $groups;
    }
}
