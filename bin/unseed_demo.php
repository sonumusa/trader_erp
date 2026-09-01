<?php

/**
 * TradeERP — remove all demo data (safe reverse of bin/seed_demo.php).
 * Deletes only rows carrying the DEMO markers; application structure and
 * real records are untouched.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/Bootstrap.php';

use app\Core\Database;

$removed = 0;
$now = date('Y-m-d H:i:s');

// Soft-delete demo masters
foreach (['customers' => 'code LIKE \'DEMO-%\'', 'suppliers' => 'code LIKE \'DEMO-%\'', 'items' => 'item_code LIKE \'DEMO-%\''] as $table => $where) {
    $ids = array_column(Database::query("SELECT id FROM {$table} WHERE {$where}"), 'id');
    foreach ($ids as $id) {
        Database::execute("UPDATE {$table} SET deleted_at = ?, updated_at = ? WHERE id = ?", [$now, $now, $id]);
        $removed++;
    }
}

// Hard-remove demo item_uoms (junction rows)
Database::execute(
    'DELETE FROM item_uoms WHERE item_id IN (SELECT id FROM items WHERE item_code LIKE \'DEMO-%\')'
);

// Soft-delete demo item groups
foreach (Database::query("SELECT id FROM item_groups WHERE code IN ('ELEC','GROC','STAT','BEVE')") as $g) {
    Database::execute('UPDATE item_groups SET deleted_at = ?, updated_at = ? WHERE id = ?', [$now, $now, $g['id']]);
    $removed++;
}

// UOMs created by the demo seeder (only if unused)
foreach (Database::query("SELECT u.id FROM uoms u WHERE u.code IN ('PCS','KG','CTN','DZN','MTR','LTR')") as $u) {
    $inUse = (int) Database::value('SELECT COUNT(*) FROM items WHERE stock_uom_id = ? AND deleted_at IS NULL', [$u['id']]);
    if ($inUse === 0) {
        Database::execute('UPDATE uoms SET deleted_at = ?, updated_at = ? WHERE id = ?', [$now, $now, $u['id']]);
        $removed++;
    }
}

echo "Demo data removed ({$removed} records soft-deleted/cleaned).\n";
