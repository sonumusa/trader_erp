<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Feature management service.
 * Modules are individually enable/disable-able. Disabled features are
 * hidden from the interface and blocked server-side.
 */
final class FeatureService
{
    private static function catalogueFile(): array
    {
        return require APP_ROOT . '/config/features.php';
    }

    public static function isEnabled(string $key): bool
    {
        $row = Database::row('SELECT is_enabled FROM features WHERE `key` = ? LIMIT 1', [$key]);
        if ($row === null) {
            $features = self::catalogue();
            return (bool) ($features[$key]['default'] ?? false);
        }
        return (int) $row['is_enabled'] === 1;
    }

    /** @return array<int,array<string,mixed>> all features for the settings UI */
    public static function all(): array
    {
        $features = self::catalogue();
        $rows = Database::query('SELECT * FROM features ORDER BY sort_order');
        $out = [];
        foreach ($rows as $row) {
            $meta = $features[$row['key']] ?? ['label' => $row['key'], 'description' => ''];
            $out[] = $row + ['label' => $meta['label'], 'description' => $meta['description']];
        }
        return $out;
    }

    public static function set(string $key, bool $enabled): void
    {
        Database::execute('UPDATE features SET is_enabled = ? WHERE `key` = ?', [$enabled ? 1 : 0, $key]);
    }

    public static function catalogue(): array
    {
        return self::catalogueFile();
    }
}
