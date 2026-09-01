<?php

/**
 * TradeERP — default Chart of Accounts seeder.
 *
 * Seeds a complete hierarchical COA (groups + leaf accounts) plus the
 * Accounting Defaults mapping for a company. Re-runnable: skips rows that
 * already exist for the company (safe to call from installer, company
 * creation and setup wizard).
 *
 * Structure follows the Pakistani trading convention:
 *   Assets / Liabilities / Equity / Income / Expenses with sub-groups.
 *
 * @param PDO $pdo
 * @param int $companyId
 * @return array<string,int> counts inserted
 */
function seed_default_coa(PDO $pdo, int $companyId): array
{
    $now = date('Y-m-d H:i:s');

    $stmtAccount = $pdo->prepare(
        'INSERT INTO accounts (company_id, parent_id, code, name, account_type, is_group, is_system, is_cash, is_bank, is_active, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)'
    );

    /**
     * COA definition.
     * [code, name, type, is_group, is_system, is_cash, is_bank, children]
     */
    $coa = [
        ['1000', 'Assets', 'asset', 1, 1, 0, 0, [
            ['1100', 'Current Assets', 'asset', 1, 1, 0, 0, [
                ['1101', 'Cash in Hand', 'asset', 0, 1, 1, 0, []],
                ['1102', 'Bank Accounts', 'asset', 1, 1, 0, 1, [
                    ['1102-01', 'Main Bank Account', 'asset', 0, 0, 0, 1, []],
                ]],
                ['1103', 'Accounts Receivable', 'asset', 0, 1, 0, 0, []],
                ['1104', 'Inventory', 'asset', 0, 1, 0, 0, []],
                ['1105', 'Prepayments', 'asset', 0, 0, 0, 0, []],
                ['1106', 'Tax Receivable', 'asset', 0, 1, 0, 0, []],
            ]],
            ['1200', 'Fixed Assets', 'asset', 1, 1, 0, 0, [
                ['1201', 'Furniture & Fixtures', 'asset', 0, 0, 0, 0, []],
                ['1202', 'Equipment', 'asset', 0, 0, 0, 0, []],
                ['1203', 'Vehicles', 'asset', 0, 0, 0, 0, []],
            ]],
        ]],
        ['2000', 'Liabilities', 'liability', 1, 1, 0, 0, [
            ['2100', 'Current Liabilities', 'liability', 1, 1, 0, 0, [
                ['2101', 'Accounts Payable', 'liability', 0, 1, 0, 0, []],
                ['2102', 'Tax Payable', 'liability', 0, 1, 0, 0, []],
                ['2103', 'Withholding Tax Payable', 'liability', 0, 0, 0, 0, []],
                ['2104', 'Advance from Customers', 'liability', 0, 0, 0, 0, []],
            ]],
            ['2200', 'Long Term Liabilities', 'liability', 1, 1, 0, 0, [
                ['2201', 'Bank Loans', 'liability', 0, 0, 0, 0, []],
            ]],
        ]],
        ['3000', 'Equity', 'equity', 1, 1, 0, 0, [
            ['3100', 'Opening Balance Equity', 'equity', 0, 1, 0, 0, []],
            ['3200', "Owner's Capital", 'equity', 0, 0, 0, 0, []],
            ['3300', 'Retained Earnings', 'equity', 0, 0, 0, 0, []],
        ]],
        ['4000', 'Income', 'income', 1, 1, 0, 0, [
            ['4100', 'Sales', 'income', 0, 1, 0, 0, []],
            ['4200', 'Sales Returns', 'income', 0, 1, 0, 0, []],
            ['4300', 'Discount Received', 'income', 0, 0, 0, 0, []],
            ['4400', 'Other Income', 'income', 0, 0, 0, 0, []],
        ]],
        ['5000', 'Expenses', 'expense', 1, 1, 0, 0, [
            ['5100', 'Cost of Goods Sold', 'expense', 0, 1, 0, 0, []],
            ['5200', 'Purchases', 'expense', 0, 1, 0, 0, []],
            ['5300', 'Purchase Returns', 'expense', 0, 1, 0, 0, []],
            ['5400', 'Discount Allowed', 'expense', 0, 0, 0, 0, []],
            ['5500', 'Round Off', 'expense', 0, 0, 0, 0, []],
            ['5600', 'Stock Adjustment', 'expense', 0, 1, 0, 0, []],
            ['5700', 'Salaries & Wages', 'expense', 0, 0, 0, 0, []],
            ['5800', 'Rent, Rates & Taxes', 'expense', 0, 0, 0, 0, []],
            ['5900', 'Utilities', 'expense', 0, 0, 0, 0, []],
            ['5910', 'General Expenses', 'expense', 0, 0, 0, 0, []],
        ]],
    ];

    $inserted = ['groups' => 0, 'accounts' => 0];
    $ids = []; // code => account id

    $walk = function (array $nodes, ?int $parentId) use (&$walk, $stmtAccount, $pdo, $companyId, $now, &$ids, &$inserted): void {
        foreach ($nodes as $node) {
            [$code, $name, $type, $isGroup, $isSystem, $isCash, $isBank, $children] = $node;

            $existing = $pdo->prepare('SELECT id FROM accounts WHERE company_id = ? AND code = ? LIMIT 1');
            $existing->execute([$companyId, $code]);
            $row = $existing->fetch();

            if ($row) {
                $id = (int) $row['id'];
            } else {
                $stmtAccount->execute([$companyId, $parentId, $code, $name, $type, $isGroup, $isSystem, $isCash, $isBank, $now, $now]);
                $id = (int) $pdo->lastInsertId();
                $isGroup ? $inserted['groups']++ : $inserted['accounts']++;
            }
            $ids[$code] = $id;

            if ($children !== []) {
                $walk($children, $id);
            }
        }
    };
    $walk($coa, null);

    /* ---------- Accounting defaults ---------- */
    $defaults = [
        'cash'             => '1101', // Cash in Hand
        'receivable'       => '1103', // Accounts Receivable
        'inventory'        => '1104', // Inventory
        'payable'          => '2101', // Accounts Payable
        'tax_payable'      => '2102', // Tax Payable
        'tax_receivable'  => '1106', // Tax Receivable
        'opening_balance'  => '3100', // Opening Balance Equity
        'sales'            => '4100', // Sales
        'sales_return'     => '4200', // Sales Returns
        'discount_received'=> '4300', // Discount Received
        'cogs'             => '5100', // Cost of Goods Sold
        'purchase'         => '5200', // Purchases
        'purchase_return'  => '5300', // Purchase Returns
        'discount_allowed' => '5400', // Discount Allowed
        'round_off'        => '5500', // Round Off
        'stock_adjustment' => '5600', // Stock Adjustment
        'profit_loss'      => '3300', // Retained Earnings
        'bank'             => '1102-01', // Main Bank Account
    ];

    $stmtDefault = $pdo->prepare(
        'INSERT INTO account_defaults (company_id, branch_id, `key`, account_id, created_at, updated_at)
         VALUES (?, NULL, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE account_id = VALUES(account_id), updated_at = VALUES(updated_at)'
    );
    $inserted['defaults'] = 0;
    foreach ($defaults as $key => $code) {
        if (!isset($ids[$code])) {
            continue;
        }
        $stmtDefault->execute([$companyId, $key, $ids[$code], $now, $now]);
        $inserted['defaults']++;
    }

    return $inserted;
}
