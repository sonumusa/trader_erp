<?php

/**
 * TradeERP — optional demo data seeder
 * -------------------------------------
 * Inserts sample customers, suppliers, item groups, items, UOMs and a demo
 * branch so the system can be explored. All demo rows use identifiable
 * markers (emails @demo.local, codes DEMO-*) and can be fully removed with:
 *
 *     php bin/unseed_demo.php
 *
 * Usage: php bin/seed_demo.php   (requires an installed database)
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/Bootstrap.php';

use app\Core\Database;
use app\Services\CompanyContextService;

$companyId = CompanyContextService::currentCompanyId();
if ($companyId === null) {
    fwrite(STDERR, "No company found. Install the application first.\n");
    exit(1);
}

$now = date('Y-m-d H:i:s');
$done = 0;

/* Demo branch */
$branchId = (int) Database::value(
    'SELECT id FROM branches WHERE company_id = ? AND deleted_at IS NULL ORDER BY id LIMIT 1',
    [$companyId]
);

/* UOMs */
$uoms = ['Piece' => 'PCS', 'Kilogram' => 'KG', 'Carton' => 'CTN', 'Dozen' => 'DZN', 'Meter' => 'MTR', 'Liter' => 'LTR'];
foreach ($uoms as $name => $code) {
    if (!Database::value('SELECT id FROM uoms WHERE code = ?', [$code])) {
        Database::execute('INSERT INTO uoms (name, code, created_at, updated_at) VALUES (?, ?, ?, ?)', [$name, $code, $now, $now]);
        $done++;
    }
}
$pcsId = (int) Database::value('SELECT id FROM uoms WHERE code = \'PCS\'');
$kgId = (int) Database::value('SELECT id FROM uoms WHERE code = \'KG\'');

/* Item groups */
$groups = ['Electronics', 'Groceries', 'Stationery', 'Beverages'];
$gIds = [];
foreach ($groups as $g) {
    if (!Database::value('SELECT id FROM item_groups WHERE name = ?', [$g])) {
        Database::execute('INSERT INTO item_groups (name, code, created_at, updated_at) VALUES (?, ?, ?, ?)', [$g, strtoupper(substr($g, 0, 4)), $now, $now]);
        $gIds[$g] = (int) Database::lastInsertId();
        $done++;
    }
}

/* Items */
$items = [
    ['DEMO-001', 'LED Bulb 9W',      '890000001', 'Electronics',  $pcsId, 180.00, 260.00, 100],
    ['DEMO-002', 'Extension Cord 5m', '890000002', 'Electronics',  $pcsId, 320.00, 450.00,  50],
    ['DEMO-003', 'Basmati Rice 5kg',  '890000003', 'Groceries',    $kgId, 950.00, 1250.00, 200],
    ['DEMO-004', 'Cooking Oil 1L',    '890000004', 'Groceries',    $pcsId, 420.00, 540.00, 300],
    ['DEMO-005', 'A4 Paper Ream',     '890000005', 'Stationery',   $pcsId, 780.00, 950.00, 150],
    ['DEMO-006', 'Ball Pen (Box 12)', '890000006', 'Stationery',   $pcsId, 120.00, 180.00, 400],
    ['DEMO-007', 'Mineral Water 1.5L','890000007', 'Beverages',    $pcsId,  60.00, 100.00, 500],
];
foreach ($items as [$code, $name, $barcode, $group, $uomId, $pr, $sr, $min]) {
    if (Database::value('SELECT id FROM items WHERE item_code = ?', [$code])) {
        continue;
    }
    Database::execute(
        'INSERT INTO items (item_code, name, barcode, item_group_id, brand, default_purchase_rate, default_sales_rate,
                            stock_uom_id, min_stock, reorder_level, status, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'active\', NULL, ?, ?)',
        [$code, $name, $barcode, $gIds[$group] ?? null, 'Demo Brand', $pr, $sr, $uomId, $min, $min * 0.5, $now, $now]
    );
    $itemId = (int) Database::lastInsertId();
    Database::execute(
        'INSERT INTO item_uoms (item_id, uom_id, is_stock_uom, conversion_factor, created_at) VALUES (?, ?, 1, 1, ?)',
        [$itemId, $uomId, $now]
    );
    $done++;
}

/* Customers */
$customers = [
    ['DEMO-C01', 'Al-Falah Traders',     '03001234567', 'alfalah@demo.local',  'Lahore'],
    ['DEMO-C02', 'Metro General Store',  '03019876543', 'metro@demo.local',    'Karachi'],
    ['DEMO-C03', 'Shahid & Sons',        '03331234567', 'shahid@demo.local',   'Islamabad'],
];
foreach ($customers as [$code, $name, $phone, $email, $city]) {
    if (Database::value('SELECT id FROM customers WHERE code = ?', [$code])) {
        continue;
    }
    Database::execute(
        'INSERT INTO customers (company_id, branch_id, code, name, type, phone, email, city, status, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, \'business\', ?, ?, ?, \'active\', NULL, ?, ?)',
        [$companyId, $branchId, $code, $name, $phone, $email, $city, $now, $now]
    );
    $done++;
}

/* Suppliers */
$suppliers = [
    ['DEMO-S01', 'National Distributors', '04211122233', 'nd@demo.local',  'Lahore'],
    ['DEMO-S02', 'Punjab Wholesale',      '02133344455', 'pw@demo.local',  'Karachi'],
];
foreach ($suppliers as [$code, $name, $phone, $email, $city]) {
    if (Database::value('SELECT id FROM suppliers WHERE code = ?', [$code])) {
        continue;
    }
    Database::execute(
        'INSERT INTO suppliers (company_id, branch_id, code, name, phone, email, city, status, created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, \'active\', NULL, ?, ?)',
        [$companyId, $branchId, $code, $name, $phone, $email, $city, $now, $now]
    );
    $done++;
}

echo "Demo data seeded ({$done} records). Remove with: php bin/unseed_demo.php\n";
