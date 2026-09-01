<?php

/**
 * Feature catalogue — single source of truth for the module toggles.
 * Used by FeatureService at runtime and by the installer for seeding.
 */

return [
    'inventory'       => ['label' => 'Inventory',           'description' => 'Items, warehouses, stock ledger', 'default' => true],
    'sales'           => ['label' => 'Sales',               'description' => 'Sales invoices, quotations, orders, returns', 'default' => true],
    'purchase'        => ['label' => 'Purchase',            'description' => 'Purchase invoices, orders, returns', 'default' => true],
    'accounting'      => ['label' => 'Accounting',          'description' => 'Chart of accounts, vouchers, ledgers', 'default' => true],
    'warehouses'      => ['label' => 'Warehouses',          'description' => 'Multiple warehouse support', 'default' => true],
    'multi_uom'       => ['label' => 'Multiple UOM',        'description' => 'Item-specific alternative UOMs', 'default' => true],
    'batch'           => ['label' => 'Batch',               'description' => 'Batch number tracking', 'default' => false],
    'serial'          => ['label' => 'Serial Number',       'description' => 'Serial number tracking', 'default' => false],
    'expiry'          => ['label' => 'Expiry',              'description' => 'Expiry date tracking', 'default' => false],
    'tax'             => ['label' => 'Tax',                 'description' => 'Sales tax / VAT configuration', 'default' => true],
    'branches'        => ['label' => 'Branches',            'description' => 'Multi-branch support', 'default' => true],
    'pos'             => ['label' => 'POS',                 'description' => 'Point of sale (future)', 'default' => false],
    'expenses'        => ['label' => 'Expenses',            'description' => 'Expense management (future)', 'default' => false],
    'bank_recon'      => ['label' => 'Bank Reconciliation', 'description' => 'Bank statement reconciliation (future)', 'default' => false],
    'whatsapp'        => ['label' => 'WhatsApp',            'description' => 'WhatsApp document sharing (future)', 'default' => false],
    'api'             => ['label' => 'API',                 'description' => 'REST API access (future)', 'default' => false],
    'fbr'             => ['label' => 'FBR',                 'description' => 'Pakistan FBR integration (future)', 'default' => false],
];
