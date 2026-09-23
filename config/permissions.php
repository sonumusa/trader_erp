<?php

/**
 * Permission catalogue — single source of truth for Module → Document → Action.
 * Used by PermissionService at runtime and by the installer for seeding.
 * Future modules register their permissions here (or via migrations).
 */

return [
    'dashboard' => [
        'dashboard' => ['view'],
    ],
    'users' => [
        'user'       => ['view', 'create', 'edit', 'delete'],
        'role'       => ['view', 'create', 'edit', 'delete'],
        'permission' => ['view', 'edit'],
    ],
    'settings' => [
        'setting'        => ['view', 'edit'],
        'feature'        => ['view', 'edit'],
        'company'        => ['view', 'create', 'edit', 'delete'],
        'branch'         => ['view', 'create', 'edit', 'delete'],
        'financial_year' => ['view', 'create', 'edit', 'close'],
        'numbering'      => ['view', 'edit', 'reset'],
        'payment_mode'   => ['view', 'create', 'edit', 'delete'],
        'tax'            => ['view', 'create', 'edit', 'delete'],
        'closing'        => ['view', 'set', 'override'],
        'setup'          => ['view', 'run'],
        'backup'         => ['view', 'create', 'download', 'restore'],
        'system'         => ['view', 'edit'],
    ],
    'audit' => [
        'audit_log' => ['view', 'export'],
    ],
    'reports' => [
        'report' => ['view', 'export'],
    ],
    'accounting' => [
        'account' => ['view', 'create', 'edit', 'delete'],
        'defaults' => ['view', 'edit'],
        'voucher' => ['view', 'create', 'edit', 'cancel', 'print'],
    ],
    'inventory' => [
        'warehouse'    => ['view', 'create', 'edit', 'delete'],
        'uom'          => ['view', 'create', 'edit', 'delete'],
        'item'         => ['view', 'create', 'edit', 'delete'],
        'item_group'   => ['view', 'create', 'edit', 'delete'],
        'transfer'     => ['view', 'create', 'delete'],
        'adjustment'   => ['view', 'create'],
        'stock'        => ['view'],
        'opening_stock' => ['view', 'create'],
    ],
    'sales' => [
        'customer'        => ['view', 'create', 'edit', 'delete'],
        'sales_quotation' => ['view', 'create', 'cancel', 'print'],
        'sales_order'     => ['view', 'create', 'cancel', 'print'],
        'sales_invoice'   => ['view', 'create', 'cancel', 'print', 'export'],
        'sales_return'    => ['view', 'create', 'cancel', 'print'],
    ],
    'purchase' => [
        'supplier'           => ['view', 'create', 'edit', 'delete'],
        'purchase_quotation' => ['view', 'create', 'cancel', 'print'],
        'purchase_order'     => ['view', 'create', 'cancel', 'print'],
        'purchase_invoice'   => ['view', 'create', 'cancel', 'print', 'export'],
        'purchase_return'    => ['view', 'create', 'cancel', 'print'],
    ],
];
