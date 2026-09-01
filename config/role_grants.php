<?php

/**
 * Default permission grants for SYSTEM roles.
 * Keyed by role slug → list of permission keys ('*' wildcards allowed).
 * Applied idempotently by the installer and by bin/migrate.php on upgrades
 * (existing custom grants are never removed — only missing defaults are added).
 */

return [
    'super-admin' => '*', // every permission (also enforced at runtime)
    'administrator' => [
        'dashboard.dashboard.view' => 1,
        'users.user.*' => 1, 'users.role.*' => 1, 'users.permission.*' => 1,
        'settings.*' => 1,
        'audit.audit_log.*' => 1,
        'reports.report.*' => 1,
        'accounting.account.*' => 1, 'accounting.defaults.*' => 1, 'accounting.voucher.*' => 1,
        'inventory.warehouse.*' => 1, 'inventory.uom.*' => 1,
        'inventory.item.*' => 1, 'inventory.item_group.*' => 1,
        'inventory.transfer.*' => 1, 'inventory.adjustment.*' => 1,
        'inventory.stock.view' => 1, 'inventory.opening_stock.*' => 1,
        'sales.customer.*' => 1,
        'sales.sales_quotation.*' => 1, 'sales.sales_order.*' => 1,
        'sales.sales_invoice.*' => 1, 'sales.sales_return.*' => 1,
        'purchase.supplier.*' => 1,
        'purchase.purchase_quotation.*' => 1, 'purchase.purchase_order.*' => 1,
        'purchase.purchase_invoice.*' => 1, 'purchase.purchase_return.*' => 1,
    ],
    'manager' => [
        'dashboard.dashboard.view' => 1,
        'sales.customer.*' => 1,
        'sales.sales_quotation.*' => 1, 'sales.sales_order.*' => 1,
        'sales.sales_invoice.*' => 1, 'sales.sales_return.*' => 1,
        'purchase.supplier.*' => 1,
        'purchase.purchase_quotation.*' => 1, 'purchase.purchase_order.*' => 1,
        'purchase.purchase_invoice.*' => 1, 'purchase.purchase_return.*' => 1,
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
        'reports.report.view' => 1, 'reports.report.export' => 1,
        'settings.closing.view' => 1, 'settings.closing.override' => 1,
        'accounting.account.*' => 1, 'accounting.defaults.*' => 1, 'accounting.voucher.*' => 1,
        'inventory.warehouse.view' => 1, 'inventory.uom.view' => 1,
        'inventory.item.view' => 1, 'inventory.item_group.view' => 1,
        'inventory.stock.view' => 1,
        'sales.customer.view' => 1,
        'sales.sales_quotation.view' => 1, 'sales.sales_order.view' => 1,
        'sales.sales_invoice.view' => 1, 'sales.sales_invoice.print' => 1, 'sales.sales_invoice.export' => 1,
        'sales.sales_return.view' => 1,
        'purchase.supplier.view' => 1,
        'purchase.purchase_quotation.view' => 1, 'purchase.purchase_order.view' => 1,
        'purchase.purchase_invoice.view' => 1, 'purchase.purchase_invoice.print' => 1, 'purchase.purchase_invoice.export' => 1,
        'purchase.purchase_return.view' => 1,
    ],
    'sales-user' => [
        'dashboard.dashboard.view' => 1,
        'sales.customer.view' => 1, 'sales.customer.create' => 1, 'sales.customer.edit' => 1,
        'sales.sales_quotation.*' => 1, 'sales.sales_order.*' => 1,
        'sales.sales_invoice.*' => 1, 'sales.sales_return.*' => 1,
    ],
    'purchase-user' => [
        'dashboard.dashboard.view' => 1,
        'purchase.supplier.view' => 1, 'purchase.supplier.create' => 1, 'purchase.supplier.edit' => 1,
        'purchase.purchase_quotation.*' => 1, 'purchase.purchase_order.*' => 1,
        'purchase.purchase_invoice.*' => 1, 'purchase.purchase_return.*' => 1,
    ],
    'inventory-user' => [
        'dashboard.dashboard.view' => 1,
        'inventory.warehouse.*' => 1, 'inventory.uom.*' => 1,
        'inventory.item.*' => 1, 'inventory.item_group.*' => 1,
        'inventory.transfer.*' => 1, 'inventory.adjustment.*' => 1,
        'inventory.stock.view' => 1, 'inventory.opening_stock.*' => 1,
    ],
    'viewer' => [
        'dashboard.dashboard.view' => 1,
        'reports.report.view' => 1,
    ],
];
