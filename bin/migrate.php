<?php

/**
 * TradeERP — Migration runner
 * ----------------------------
 * Applies pending SQL migrations from database/migrations/*.sql to an
 * existing installation (upgrades). Tracks applied files in the
 * schema_migrations table. Also syncs the permission catalogue and
 * (optionally) seeds missing defaults.
 *
 * Usage:  php bin/migrate.php
 * Requires config/config.php (i.e. an installed application).
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/Bootstrap.php';

use app\Core\Database;

echo "TradeERP migration runner\n";

$applied = array_column(Database::query('SELECT migration FROM schema_migrations'), 'migration');
$files = glob(APP_ROOT . '/database/migrations/*.sql');
sort($files);

$ran = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        echo "  skip  {$name}\n";
        continue;
    }
    echo "  apply {$name}\n";
    try {
        Database::transaction(function () use ($file, $name) {
            $sql = (string) file_get_contents($file);
            $statements = array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql)));
            foreach ($statements as $statement) {
                $clean = trim((string) preg_replace('/^\s*--.*$/m', '', $statement));
                if ($clean !== '') {
                    Database::execute($clean);
                }
            }
            Database::execute(
                'INSERT INTO schema_migrations (migration, applied_at) VALUES (?, ?)',
                [$name, date('Y-m-d H:i:s')]
            );
        });
    } catch (\Throwable $e) {
        // Idempotency guard: pre-Phase-2 installs ran the SQL without
        // recording it. "Duplicate column/table/key" means it is already
        // applied — mark it and move on; anything else is a real error.
        $msg = $e->getMessage();
        if (preg_match('/Duplicate (column name|key name|entry)|already exists/i', $msg)) {
            echo "  note  {$name} already present (marking applied)\n";
            Database::execute(
                'INSERT IGNORE INTO schema_migrations (migration, applied_at) VALUES (?, ?)',
                [$name, date('Y-m-d H:i:s')]
            );
        } else {
            throw $e;
        }
    }
    $ran++;
}

// ---- Sync permission catalogue (insert missing permissions) ----
$catalogue = require APP_ROOT . '/config/permissions.php';
$added = 0;
$stmt = Database::pdo()->prepare(
    'INSERT IGNORE INTO permissions (module, document, action, name, created_at) VALUES (?, ?, ?, ?, ?)'
);
foreach ($catalogue as $module => $docs) {
    foreach ($docs as $document => $actions) {
        foreach ($actions as $action) {
            $stmt->execute([
                $module, $document, $action,
                ucfirst($module) . ' — ' . ucfirst(str_replace('_', ' ', $document)) . ' — ' . ucfirst($action),
                date('Y-m-d H:i:s'),
            ]);
            $added += $stmt->rowCount();
        }
    }
}

// ---- Sync default grants for system roles (additive; keeps custom edits) ----
$grantFile = APP_ROOT . '/database/seeders/004_role_grants.php';
if (is_file($grantFile) && !function_exists('ensure_role_grants')) {
    require_once $grantFile;
}
$grantsAdded = function_exists('ensure_role_grants')
    ? ensure_role_grants(Database::pdo())
    : 0;

echo "  permissions added: {$added}, role grants added: {$grantsAdded}\n";
echo "Done ({$ran} migration(s)).\n";
