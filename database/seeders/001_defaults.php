<?php

/**
 * TradeERP — default data seeder (used by the web installer).
 *
 * Seeds:
 *   roles, permissions, permission grants, features, settings,
 *   company, head-office branch, financial year, super admin user.
 *
 * @param PDO    $pdo
 * @param array  $opts  [
 *                        'company_name', 'company_code', 'currency',
 *                        'admin_name', 'admin_email', 'admin_password',
 *                        'fy_name', 'fy_start', 'fy_end', 'timezone'
 *                      ]
 * @return array<string,mixed> summary
 */
function run_installer_seed(PDO $pdo, array $opts): array
{
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $now = date('Y-m-d H:i:s');

    /* ---------- 1. Roles ---------- */
    $roles = [
        ['Super Admin',      'super-admin',    'Full access to every module.', 1],
        ['Administrator',    'administrator',  'Day-to-day administration of the system.', 1],
        ['Manager',          'manager',        'Oversees sales, purchase and inventory.', 1],
        ['Accountant',       'accountant',     'Handles accounting, vouchers and reports.', 1],
        ['Sales User',       'sales-user',     'Creates sales invoices and manages customers.', 1],
        ['Purchase User',    'purchase-user',  'Creates purchase invoices and manages suppliers.', 1],
        ['Inventory User',   'inventory-user', 'Manages items, warehouses and stock.', 1],
        ['Viewer',           'viewer',         'Read-only access to reports.', 1],
    ];

    $roleIds = [];
    $stmt = $pdo->prepare('INSERT INTO roles (name, slug, description, is_system, created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($roles as [$name, $slug, $desc, $system]) {
        $stmt->execute([$name, $slug, $desc, $system, $now, $now]);
        $roleIds[$slug] = (int) $pdo->lastInsertId();
    }

    /* ---------- 2. Permissions (from the shared catalogue) ---------- */
    $catalogue = require dirname(__DIR__, 2) . '/config/permissions.php';
    $permIds = [];   // key => id
    $pKeyByDoc = []; // key => [module, document]
    $stmt = $pdo->prepare('INSERT INTO permissions (module, document, action, name, created_at)
                           VALUES (?, ?, ?, ?, ?)');
    foreach ($catalogue as $module => $docs) {
        foreach ($docs as $document => $actions) {
            foreach ($actions as $action) {
                $key = $module . '.' . $document . '.' . $action;
                $stmt->execute([$module, $document, $action, ucfirst($module) . ' — ' . ucfirst(str_replace('_', ' ', $document)) . ' — ' . ucfirst($action), $now]);
                $permIds[$key] = (int) $pdo->lastInsertId();
                $pKeyByDoc[$key] = [$module, $document];
            }
        }
    }

    /* ---------- 3. Default permission grants per role ---------- */
    $grants = [
        'super-admin'    => null, // every permission
        'administrator'  => 'admin',
        'manager'        => 'manager',
        'accountant'     => 'accountant',
        'sales-user'     => 'sales',
        'purchase-user'  => 'purchase',
        'inventory-user' => 'inventory',
        'viewer'         => 'viewer',
    ];

    $roleGrant = [
        'admin' => [
            'dashboard.dashboard.view' => 1,
            'users.user.*' => 1, 'users.role.*' => 1, 'users.permission.*' => 1,
            'settings.*' => 1,
            'audit.audit_log.*' => 1,
            'accounting.account.*' => 1, 'accounting.defaults.*' => 1,
            'inventory.warehouse.*' => 1, 'inventory.uom.*' => 1,
            'inventory.item.*' => 1, 'inventory.item_group.*' => 1,
            'inventory.transfer.*' => 1, 'inventory.adjustment.*' => 1,
            'inventory.stock.view' => 1, 'inventory.opening_stock.*' => 1,
            'sales.customer.*' => 1, 'purchase.supplier.*' => 1,
        ],
        'manager' => [
            'dashboard.dashboard.view' => 1,
            'sales.customer.*' => 1, 'purchase.supplier.*' => 1,
            'inventory.warehouse.*' => 1, 'inventory.uom.*' => 1,
            'inventory.item.*' => 1, 'inventory.item_group.*' => 1,
            'inventory.transfer.*' => 1, 'inventory.adjustment.*' => 1,
            'inventory.stock.view' => 1, 'inventory.opening_stock.*' => 1,
        ],
        'accountant' => [
            'dashboard.dashboard.view' => 1,
            'users.user.view' => 1,
            'settings.setting.view' => 1, 'settings.company.view' => 1, 'settings.financial_year.view' => 1,
            'settings.numbering.view' => 1,
            'audit.audit_log.view' => 1, 'audit.audit_log.export' => 1,
            'accounting.account.*' => 1, 'accounting.defaults.*' => 1,
            'inventory.warehouse.view' => 1, 'inventory.uom.view' => 1,
            'inventory.item.view' => 1, 'inventory.item_group.view' => 1,
            'inventory.stock.view' => 1,
            'sales.customer.view' => 1, 'purchase.supplier.view' => 1,
        ],
        'sales' => [
            'dashboard.dashboard.view' => 1,
            'sales.customer.view' => 1, 'sales.customer.create' => 1, 'sales.customer.edit' => 1,
        ],
        'purchase' => [
            'dashboard.dashboard.view' => 1,
            'purchase.supplier.view' => 1, 'purchase.supplier.create' => 1, 'purchase.supplier.edit' => 1,
        ],
        'inventory' => [
            'dashboard.dashboard.view' => 1,
            'inventory.warehouse.*' => 1, 'inventory.uom.*' => 1,
            'inventory.item.*' => 1, 'inventory.item_group.*' => 1,
            'inventory.transfer.*' => 1, 'inventory.adjustment.*' => 1,
            'inventory.stock.view' => 1, 'inventory.opening_stock.*' => 1,
        ],
        'viewer' => [
            'dashboard.dashboard.view' => 1,
        ],
    ];

    $stmtGrant = $pdo->prepare('INSERT INTO permission_role (role_id, permission_id) VALUES (?, ?)');
    foreach ($roleIds as $slug => $rid) {
        $grantSet = $grants[$slug];
        if ($grantSet === null) {
            // Every permission
            foreach ($permIds as $pid) {
                $stmtGrant->execute([$rid, $pid]);
            }
            continue;
        }
        foreach ($roleGrant[$grantSet] ?? [] as $key => $_) {
            // wildcard matching: 'settings.*' or 'sales.customer.*'
            if (str_ends_with($key, '.*')) {
                $prefix = rtrim($key, '*');
                foreach ($permIds as $pkey => $pid) {
                    if (str_starts_with($pkey, $prefix)) {
                        $stmtGrant->execute([$rid, $pid]);
                    }
                }
            } elseif (isset($permIds[$key])) {
                $stmtGrant->execute([$rid, $permIds[$key]]);
            }
        }
    }

    /* ---------- 4. Features ---------- */
    $features = require dirname(__DIR__, 2) . '/config/features.php';
    $stmt = $pdo->prepare('INSERT INTO features (`key`, is_enabled, is_installed, sort_order, created_at, updated_at)
                           VALUES (?, ?, ?, ?, ?, ?)');
    $order = 0;
    foreach ($features as $key => $meta) {
        $stmt->execute([$key, $meta['default'] ? 1 : 0, $key === 'inventory' || $key === 'sales' || $key === 'purchase' || $key === 'accounting' ? 1 : 0, ++$order, $now, $now]);
    }

    /* ---------- 5. Settings ---------- */
    $settings = [
        'company_name'          => $opts['company_name'],
        'company_code'          => $opts['company_code'] ?? '',
        'currency'              => $opts['currency'] ?? 'PKR',
        'timezone'              => $opts['timezone'] ?? 'Asia/Karachi',
        'allow_negative_stock'  => '0',
        'inventory_cost_method' => 'moving_average',
        'installed_at'          => $now,
    ];
    $stmt = $pdo->prepare('INSERT INTO settings (`key`, `value`, group_name, created_at, updated_at) VALUES (?, ?, ?, ?, ?)');
    foreach ($settings as $k => $v) {
        $stmt->execute([$k, (string) $v, 'general', $now, $now]);
    }

    /* ---------- 6. Company + head office ---------- */
    $pdo->prepare('INSERT INTO companies (name, code, currency, fiscal_year_start, fiscal_year_end, created_at, updated_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$opts['company_name'], $opts['company_code'] ?? '', $opts['currency'] ?? 'PKR',
                   $opts['fy_start'], $opts['fy_end'], $now, $now]);
    $companyId = (int) $pdo->lastInsertId();

    $pdo->prepare('INSERT INTO branches (company_id, name, code, is_head_office, is_active, created_at, updated_at)
                   VALUES (?, ?, ?, 1, 1, ?, ?)')
        ->execute([$companyId, 'Head Office', 'HO', $now, $now]);
    $headOfficeId = (int) $pdo->lastInsertId();

    /* ---------- 7. Financial year ---------- */
    $pdo->prepare('INSERT INTO financial_years (company_id, name, start_date, end_date, is_active, is_closed, created_at, updated_at)
                   VALUES (?, ?, ?, ?, 1, 0, ?, ?)')
        ->execute([$companyId, $opts['fy_name'], $opts['fy_start'], $opts['fy_end'], $now, $now]);

    /* ---------- 8. Super admin user ---------- */
    $pdo->prepare('INSERT INTO users (company_id, role_id, name, email, password_hash, status, is_super_admin, created_by, created_at, updated_at)
                   VALUES (?, ?, ?, ?, ?, \'active\', 1, NULL, ?, ?)')
        ->execute([
            $companyId,
            $roleIds['super-admin'],
            $opts['admin_name'],
            $opts['admin_email'],
            password_hash($opts['admin_password'], PASSWORD_DEFAULT),
            $now,
            $now,
        ]);
    $adminId = (int) $pdo->lastInsertId();

    /* ---------- 9. Default warehouses (used by later phases) ---------- */
    $pdo->prepare('INSERT INTO warehouses (company_id, branch_id, name, code, is_default, status, created_at, updated_at)
                   VALUES (?, ?, ?, ?, 1, \'active\', ?, ?)')
        ->execute([$companyId, $headOfficeId, 'Main Store', 'MS', $now, $now]);

    /* ---------- 10. Document number series defaults ---------- */
    $docTypes = [
        'sales_quotation'    => 'SQ',
        'sales_order'        => 'SO',
        'sales_invoice'      => 'SI',
        'sales_return'       => 'SR',
        'purchase_quotation' => 'PQ',
        'purchase_order'     => 'PO',
        'purchase_invoice'   => 'PI',
        'purchase_return'    => 'PR',
        'journal_entry'      => 'JV',
        'cash_payment'       => 'CPV',
        'bank_payment'       => 'BPV',
        'cash_receipt'       => 'CRV',
        'bank_receipt'       => 'BRV',
        'contra_voucher'     => 'CV',
        'stock_transfer'     => 'ST',
        'stock_adjustment'   => 'SA',
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO document_number_series
            (company_id, document_type, prefix, include_branch_code, include_financial_year,
             starting_number, next_number, last_used_number, number_length, is_active, created_at, updated_at)
         VALUES (?, ?, ?, 0, 1, 1, 1, 0, 6, 1, ?, ?)'
    );
    foreach ($docTypes as $type => $prefix) {
        $stmt->execute([$companyId, $type, $prefix, $now, $now]);
    }

    return [
        'company_id'    => $companyId,
        'head_office_id' => $headOfficeId,
        'admin_id'      => $adminId,
        'permissions'   => count($permIds),
        'roles'         => count($roleIds),
        'series'        => count($docTypes),
    ];
}
