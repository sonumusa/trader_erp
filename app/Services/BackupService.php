<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Database backup service (spec §53).
 *
 * Works on Hostinger shared hosting WITHOUT shell access:
 *   * prefers `mysqldump` when available (CLI or exec)
 *   * falls back to a pure-PHP SQL export (SHOW CREATE TABLE + batched SELECTs)
 * Backups are stored in storage/backups — OUTSIDE the webroot, never served
 * directly. Downloads go through an authenticated, permission-checked route.
 */
final class BackupService
{
    /** Create a backup; returns metadata. */
    public static function create(): array
    {
        $dir = self::dir();
        $filename = 'backup_' . date('Ymd_His') . '.sql';
        $path = $dir . '/' . $filename;

        $dump = self::mysqldump();
        if ($dump !== null && $dump !== '') {
            file_put_contents($path, $dump, LOCK_EX);
        } else {
            $export = self::phpExport();
            file_put_contents($path, $export, LOCK_EX);
        }
        @chmod($path, 0600);

        return [
            'name' => $filename,
            'path' => $path,
            'size' => filesize($path),
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    /** List backups newest first. */
    public static function list(): array
    {
        $dir = self::dir();
        $out = [];
        foreach (glob($dir . '/backup_*.sql') ?: [] as $file) {
            $out[] = [
                'name'       => basename($file),
                'path'       => $file,
                'size'       => filesize($file),
                'created_at' => date('Y-m-d H:i:s', filemtime($file)),
            ];
        }
        usort($out, fn($a, $b) => strcmp($b['name'], $a['name']));
        return $out;
    }

    public static function find(string $name): ?array
    {
        $name = basename($name); // no path traversal
        $path = self::dir() . '/' . $name;
        if (!is_file($path) || !str_starts_with($name, 'backup_') || !str_ends_with($name, '.sql')) {
            return null;
        }
        return [
            'name' => $name,
            'path' => $path,
            'size' => filesize($path),
            'created_at' => date('Y-m-d H:i:s', filemtime($path)),
        ];
    }

    public static function delete(string $name): bool
    {
        $b = self::find($name);
        if ($b) {
            return @unlink($b['path']);
        }
        return false;
    }

    /** mysqldump via exec when available (Hostinger has it). */
    private static function mysqldump(): ?string
    {
        $cfg = config('database');
        if (!function_exists('exec')) {
            return null;
        }
        $cmd = sprintf(
            'mysqldump --host=%s --port=%d --user=%s --password=%s --single-transaction --routines %s 2>/dev/null',
            escapeshellarg($cfg['host']),
            (int) ($cfg['port'] ?? 3306),
            escapeshellarg($cfg['user']),
            escapeshellarg((string) $cfg['pass']),
            escapeshellarg($cfg['name'])
        );
        $output = [];
        $rc = -1;
        @exec($cmd, $output, $rc);
        if ($rc !== 0 || $output === []) {
            return null;
        }
        return implode("\n", $output);
    }

    /** Pure-PHP export — works everywhere, no shell required. */
    private static function phpExport(): string
    {
        $pdo = Database::pdo();
        $out = "-- TradeERP database backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n-- Engine: pure-PHP export (no shell access required)\n\n";
        $out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $table = (string) $table;
            $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
            $out .= "DROP TABLE IF EXISTS `{$table}`;\n" . ($create[1] ?? '') . ";\n\n";

            $stmt = $pdo->query("SELECT * FROM `{$table}`");
            $cols = array_map(fn($c) => "`{$c}`", array_keys($stmt->fetch(PDO::FETCH_ASSOC) ?: []));
            if ($cols === []) {
                continue;
            }
            $colSql = '(' . implode(', ', $cols) . ')';
            $out .= "INSERT INTO `{$table}` {$colSql} VALUES\n";

            $stmt = $pdo->query("SELECT * FROM `{$table}`");
            $rows = [];
            foreach ($stmt as $row) {
                $vals = [];
                foreach ($row as $v) {
                    $vals[] = $v === null ? 'NULL' : $pdo->quote((string) $v);
                }
                $rows[] = '(' . implode(', ', $vals) . ')';
                if (count($rows) >= 500) {
                    $out .= implode(",\n", $rows) . ";\n";
                    $rows = [];
                }
            }
            if ($rows !== []) {
                $out .= implode(",\n", $rows) . ";\n";
            }
            $out .= "\n";
        }

        $out .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        return $out;
    }

    private static function dir(): string
    {
        $dir = APP_ROOT . '/storage/backups';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }
}
