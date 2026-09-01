<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Reusable reporting engine.
 *
 * Every report:
 *   * derives from the accounting ledger / stock ledger / document tables —
 *     never from parallel totals (spec §63)
 *   * supports date-range + entity filters
 *   * supports ASC/DESC sorting on chosen columns
 *   * can be exported (Excel/CSV/print) respecting the current filters+sort
 *
 * Returns ['title', 'columns' => [key => label], 'rows' => [...], 'totals' => [...]]
 */
final class ReportService
{
    /* ------------------------------------------------------------------
     * Sales reports
     * ------------------------------------------------------------------ */

    public static function salesRegister(int $companyId, array $f): array
    {
        $where = 'WHERE si.company_id = ?';
        $params = [$companyId];
        self::dateRange($where, $params, 'si.invoice_date', $f);
        $sort = self::sort($f, 'si.id', ['si.invoice_date', 'si.invoice_no', 'customer', 'si.total']);

        $rows = Database::query(
            "SELECT si.invoice_no, si.invoice_date, si.status,
                    COALESCE(c.name, 'Walk-in') AS customer,
                    si.subtotal, si.discount_total, si.tax_total, si.total,
                    si.cogs_total, (si.subtotal - si.cogs_total) AS profit,
                    si.received_amount
             FROM sales_invoices si
             LEFT JOIN customers c ON c.id = si.customer_id
             {$where}
             ORDER BY {$sort}
             LIMIT 2000",
            $params
        );

        return [
            'title'   => 'Sales Register',
            'columns' => [
                'invoice_no' => 'Invoice No.', 'invoice_date' => 'Date', 'customer' => 'Customer',
                'subtotal' => 'Subtotal', 'discount_total' => 'Discount', 'tax_total' => 'Tax',
                'total' => 'Total', 'cogs_total' => 'COGS', 'profit' => 'Profit', 'received_amount' => 'Received', 'status' => 'Status',
            ],
            'rows' => $rows,
            'totals' => [
                'total' => array_sum(array_column($rows, 'total')),
                'cogs_total' => array_sum(array_column($rows, 'cogs_total')),
                'profit' => array_sum(array_column($rows, 'profit')),
                'tax_total' => array_sum(array_column($rows, 'tax_total')),
            ],
        ];
    }

    public static function salesByCustomer(int $companyId, array $f): array
    {
        $where = 'WHERE si.company_id = ? AND si.status = \'posted\'';
        $params = [$companyId];
        self::dateRange($where, $params, 'si.invoice_date', $f);
        $sort = self::sort($f, 'total DESC', ['total', 'customer', 'invoices']);

        $rows = Database::query(
            "SELECT COALESCE(c.name, 'Walk-in') AS customer,
                    COUNT(si.id) AS invoices,
                    SUM(si.subtotal) AS subtotal, SUM(si.tax_total) AS tax,
                    SUM(si.total) AS total, SUM(si.cogs_total) AS cogs,
                    SUM(si.total - si.tax_total - si.cogs_total) AS profit
             FROM sales_invoices si
             LEFT JOIN customers c ON c.id = si.customer_id
             {$where}
             GROUP BY COALESCE(c.name, 'Walk-in')
             ORDER BY {$sort}
             LIMIT 500",
            $params
        );

        return [
            'title'   => 'Sales by Customer',
            'columns' => ['customer' => 'Customer', 'invoices' => 'Invoices', 'subtotal' => 'Subtotal', 'tax' => 'Tax', 'total' => 'Total', 'cogs' => 'COGS', 'profit' => 'Profit'],
            'rows' => $rows,
            'totals' => ['total' => array_sum(array_column($rows, 'total')), 'profit' => array_sum(array_column($rows, 'profit'))],
        ];
    }

    public static function salesByItem(int $companyId, array $f): array
    {
        $where = 'WHERE si.company_id = ? AND si.status = \'posted\'';
        $params = [$companyId];
        self::dateRange($where, $params, 'si.invoice_date', $f);
        $sort = self::sort($f, 'total DESC', ['total', 'item', 'qty']);

        $rows = Database::query(
            "SELECT i.item_code, i.name AS item,
                    SUM(sii.quantity) AS qty,
                    SUM(sii.amount) AS total, SUM(sii.cogs_amount) AS cogs,
                    SUM(sii.amount - sii.cogs_amount) AS profit
             FROM sales_invoice_items sii
             JOIN sales_invoices si ON si.id = sii.invoice_id
             JOIN items i ON i.id = sii.item_id
             {$where}
             GROUP BY i.item_code, i.name
             ORDER BY {$sort}
             LIMIT 500",
            $params
        );

        return [
            'title'   => 'Sales by Item',
            'columns' => ['item_code' => 'Code', 'item' => 'Item', 'qty' => 'Qty', 'total' => 'Sales', 'cogs' => 'COGS', 'profit' => 'Profit'],
            'rows' => $rows,
            'totals' => ['total' => array_sum(array_column($rows, 'total')), 'cogs' => array_sum(array_column($rows, 'cogs')), 'profit' => array_sum(array_column($rows, 'profit'))],
        ];
    }

    /* ------------------------------------------------------------------
     * Purchase reports
     * ------------------------------------------------------------------ */

    public static function purchaseRegister(int $companyId, array $f): array
    {
        $where = 'WHERE pi.company_id = ?';
        $params = [$companyId];
        self::dateRange($where, $params, 'pi.invoice_date', $f);
        $sort = self::sort($f, 'pi.id', ['pi.invoice_date', 'pi.invoice_no', 's.name', 'pi.total']);

        $rows = Database::query(
            "SELECT pi.invoice_no, pi.invoice_date, pi.status, s.name AS supplier,
                    pi.subtotal, pi.discount_total, pi.tax_total, pi.total, pi.paid_amount
             FROM purchase_invoices pi
             JOIN suppliers s ON s.id = pi.supplier_id
             {$where}
             ORDER BY {$sort}
             LIMIT 2000",
            $params
        );

        return [
            'title'   => 'Purchase Register',
            'columns' => ['invoice_no' => 'Invoice No.', 'invoice_date' => 'Date', 'supplier' => 'Supplier', 'subtotal' => 'Subtotal', 'discount_total' => 'Discount', 'tax_total' => 'Tax', 'total' => 'Total', 'paid_amount' => 'Paid', 'status' => 'Status'],
            'rows' => $rows,
            'totals' => ['total' => array_sum(array_column($rows, 'total')), 'tax_total' => array_sum(array_column($rows, 'tax_total'))],
        ];
    }

    public static function purchaseBySupplier(int $companyId, array $f): array
    {
        $where = 'WHERE pi.company_id = ? AND pi.status = \'posted\'';
        $params = [$companyId];
        self::dateRange($where, $params, 'pi.invoice_date', $f);
        $sort = self::sort($f, 'total DESC', ['total', 'supplier', 'invoices']);

        $rows = Database::query(
            "SELECT s.name AS supplier, COUNT(pi.id) AS invoices,
                    SUM(pi.subtotal) AS subtotal, SUM(pi.tax_total) AS tax, SUM(pi.total) AS total
             FROM purchase_invoices pi
             JOIN suppliers s ON s.id = pi.supplier_id
             {$where}
             GROUP BY s.name
             ORDER BY {$sort}
             LIMIT 500",
            $params
        );

        return [
            'title'   => 'Purchase by Supplier',
            'columns' => ['supplier' => 'Supplier', 'invoices' => 'Invoices', 'subtotal' => 'Subtotal', 'tax' => 'Tax', 'total' => 'Total'],
            'rows' => $rows,
            'totals' => ['total' => array_sum(array_column($rows, 'total'))],
        ];
    }

    /* ------------------------------------------------------------------
     * Party reports
     * ------------------------------------------------------------------ */

    public static function customerOutstanding(int $companyId, array $f): array
    {
        $sort = self::sort($f, 'outstanding DESC', ['name', 'outstanding']);
        $rows = Database::query(
            "SELECT c.id, c.code, c.name, c.phone,
                    COALESCE((SELECT SUM(al.debit) - SUM(al.credit) FROM account_ledger al
                              WHERE al.party_type = 'customer' AND al.party_id = c.id), 0) AS outstanding
             FROM customers c
             WHERE c.company_id = ? AND c.deleted_at IS NULL
             ORDER BY {$sort}
             LIMIT 1000",
            [$companyId]
        );
        return [
            'title'   => 'Customer Outstanding',
            'columns' => ['code' => 'Code', 'name' => 'Customer', 'phone' => 'Phone', 'outstanding' => 'Outstanding'],
            'rows' => $rows,
            'totals' => ['outstanding' => array_sum(array_map('floatval', array_column($rows, 'outstanding')))],
        ];
    }

    public static function supplierPayable(int $companyId, array $f): array
    {
        $sort = self::sort($f, 'payable DESC', ['name', 'payable']);
        $rows = Database::query(
            "SELECT s.id, s.code, s.name, s.phone,
                    COALESCE((SELECT SUM(al.credit) - SUM(al.debit) FROM account_ledger al
                              WHERE al.party_type = 'supplier' AND al.party_id = s.id), 0) AS payable
             FROM suppliers s
             WHERE s.company_id = ? AND s.deleted_at IS NULL
             ORDER BY {$sort}
             LIMIT 1000",
            [$companyId]
        );
        return [
            'title'   => 'Supplier Payable',
            'columns' => ['code' => 'Code', 'name' => 'Supplier', 'phone' => 'Phone', 'payable' => 'Payable'],
            'rows' => $rows,
            'totals' => ['payable' => array_sum(array_map('floatval', array_column($rows, 'payable')))],
        ];
    }

    /* ------------------------------------------------------------------
     * Stock reports
     * ------------------------------------------------------------------ */

    public static function stockBalanceReport(int $companyId, array $f): array
    {
        $sort = self::sort($f, 'item_code', ['item_code', 'name', 'qty', 'value']);
        $rows = Database::query(
            "SELECT i.item_code, i.name, i.barcode,
                    COALESCE((SELECT SUM(l.qty_remaining) FROM stock_layers l WHERE l.item_id = i.id), 0) AS qty,
                    COALESCE((SELECT SUM(l.qty_remaining * l.rate) FROM stock_layers l WHERE l.item_id = i.id), 0) AS value,
                    COALESCE((SELECT rate FROM stock_layers l WHERE l.item_id = i.id AND l.qty_remaining > 0
                              ORDER BY l.layer_date DESC, l.id DESC LIMIT 1), 0) AS avg_rate
             FROM items i
             WHERE i.company_id = ? AND i.deleted_at IS NULL
             ORDER BY {$sort}
             LIMIT 2000",
            [$companyId]
        );
        return [
            'title'   => 'Stock Balance (all warehouses)',
            'columns' => ['item_code' => 'Code', 'name' => 'Item', 'barcode' => 'Barcode', 'qty' => 'On hand', 'avg_rate' => 'Avg rate', 'value' => 'Value'],
            'rows' => $rows,
            'totals' => ['value' => array_sum(array_map('floatval', array_column($rows, 'value')))],
        ];
    }

    /* ------------------------------------------------------------------
     * Accounting reports — all from account_ledger (spec §63)
     * ------------------------------------------------------------------ */

    public static function trialBalance(int $companyId, array $f): array
    {
        $where = 'WHERE al.company_id = ?';
        $params = [$companyId];
        self::dateRange($where, $params, 'al.entry_date', $f);

        $rows = Database::query(
            "SELECT a.code, a.name, a.account_type,
                    COALESCE(SUM(al.debit),0) AS debit, COALESCE(SUM(al.credit),0) AS credit
             FROM account_ledger al
             JOIN accounts a ON a.id = al.account_id
             {$where}
             GROUP BY a.code, a.name, a.account_type
             ORDER BY a.code",
            $params
        );

        return [
            'title'   => 'Trial Balance',
            'columns' => ['code' => 'Code', 'name' => 'Account', 'account_type' => 'Type', 'debit' => 'Debit', 'credit' => 'Credit'],
            'rows' => $rows,
            'totals' => ['debit' => array_sum(array_column($rows, 'debit')), 'credit' => array_sum(array_column($rows, 'credit'))],
        ];
    }

    public static function profitAndLoss(int $companyId, array $f): array
    {
        $where = 'WHERE al.company_id = ?';
        $params = [$companyId];
        self::dateRange($where, $params, 'al.entry_date', $f);

        $rows = Database::query(
            "SELECT a.code, a.name, a.account_type,
                    COALESCE(SUM(al.credit),0) - COALESCE(SUM(al.debit),0) AS net
             FROM account_ledger al
             JOIN accounts a ON a.id = al.account_id
             WHERE al.company_id = ? AND a.account_type IN ('income','expense')
             AND al.entry_date >= ? AND al.entry_date <= ?
             GROUP BY a.code, a.name, a.account_type
             ORDER BY a.code",
            [$companyId, $f['from'] ?? '1900-01-01', $f['to'] ?? date('Y-m-d')]
        );

        $income = [];
        $expenses = [];
        $totalIncome = 0.0;
        $totalExpense = 0.0;
        foreach ($rows as $row) {
            if ($row['account_type'] === 'income') {
                $income[] = $row;
                $totalIncome += (float) $row['net'];
            } else {
                $expenses[] = $row;
                $totalExpense += (float) $row['net'];
            }
        }

        return [
            'title'   => 'Profit & Loss',
            'columns' => ['code' => 'Code', 'name' => 'Account', 'net' => 'Amount'],
            'income'  => $income,
            'expenses' => $expenses,
            'total_income' => round($totalIncome, 2),
            'total_expense' => round($totalExpense, 2),
            'net_profit' => round($totalIncome + $totalExpense, 2), // expense net is negative
            'rows' => $rows,
            'totals' => ['net' => round($totalIncome + $totalExpense, 2)],
        ];
    }

    public static function balanceSheet(int $companyId, array $f): array
    {
        $asOf = $f['to'] ?? date('Y-m-d');

        $rows = Database::query(
            "SELECT a.code, a.name, a.account_type, a.is_group, a.parent_id,
                    COALESCE((SELECT SUM(al.debit) - SUM(al.credit) FROM account_ledger al
                              WHERE al.account_id = a.id AND al.entry_date <= ?), 0) AS balance
             FROM accounts a
             WHERE a.company_id = ? AND a.deleted_at IS NULL AND a.is_group = 0
             ORDER BY a.code",
            [$asOf, $companyId]
        );

        $assets = [];
        $liabilities = [];
        $equity = [];
        $totalAssets = 0.0;
        $totalLiab = 0.0;
        $totalEquity = 0.0;

        foreach ($rows as $row) {
            $bal = (float) $row['balance'];
            switch ($row['account_type']) {
                case 'asset':
                    $assets[] = ['code' => $row['code'], 'name' => $row['name'], 'balance' => $bal];
                    $totalAssets += $bal;
                    break;
                case 'liability':
                    // credit-normal: present as positive amounts
                    $liabilities[] = ['code' => $row['code'], 'name' => $row['name'], 'balance' => -$bal];
                    $totalLiab += -$bal;
                    break;
                case 'equity':
                    $equity[] = ['code' => $row['code'], 'name' => $row['name'], 'balance' => -$bal];
                    $totalEquity += -$bal;
                    break;
            }
        }

        // Current-period profit/loss closes into equity so the sheet balances
        // (income is credit-normal: net = credits - debits; expenses negative).
        $pl = self::profitAndLoss($companyId, ['to' => $asOf]);
        $currentProfit = round($pl['total_income'] + $pl['total_expense'], 2);
        if (abs($currentProfit) > 0.001) {
            $equity[] = ['code' => 'PL', 'name' => 'Current Period Profit / (Loss)', 'balance' => $currentProfit];
            $totalEquity += $currentProfit;
        }

        return [
            'title'   => 'Balance Sheet',
            'columns' => ['code' => 'Code', 'name' => 'Account', 'balance' => 'Amount'],
            'assets'  => $assets,
            'liabilities' => $liabilities,
            'equity'  => $equity,
            'total_assets' => round($totalAssets, 2),
            'total_liab_equity' => round($totalLiab + $totalEquity, 2),
            'as_of'   => $asOf,
            'rows'    => [],
            'totals'  => [],
        ];
    }

    public static function cashBook(int $companyId, array $f): array
    {
        $accountId = (int) AccountDefaultService::get($companyId, 'cash');
        return self::accountBook($companyId, $accountId, 'Cash Book', $f);
    }

    public static function bankBook(int $companyId, array $f): array
    {
        $accountId = (int) AccountDefaultService::get($companyId, 'bank');
        return self::accountBook($companyId, $accountId, 'Bank Book', $f);
    }

    private static function accountBook(int $companyId, int $accountId, string $title, array $f): array
    {
        $where = 'WHERE al.account_id = ?';
        $params = [$accountId];
        self::dateRange($where, $params, 'al.entry_date', $f);
        $sort = self::sort($f, 'al.entry_date', ['al.entry_date', 'al.voucher_no']);

        $rows = Database::query(
            "SELECT al.entry_date, al.voucher_no, al.voucher_type, al.debit, al.credit, al.balance,
                    al.source_document_type, al.source_document_id
             FROM account_ledger al
             {$where}
             ORDER BY {$sort}
             LIMIT 2000",
            $params
        );

        return [
            'title'   => $title,
            'columns' => ['entry_date' => 'Date', 'voucher_no' => 'Voucher No.', 'voucher_type' => 'Type', 'debit' => 'Debit', 'credit' => 'Credit', 'balance' => 'Balance'],
            'rows' => $rows,
            'totals' => ['debit' => array_sum(array_column($rows, 'debit')), 'credit' => array_sum(array_column($rows, 'credit'))],
        ];
    }

    /* ------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------ */

    private static function dateRange(string &$where, array &$params, string $col, array $f): void
    {
        if (!empty($f['from']) && \app\Core\Validator::isValidDate($f['from'])) {
            $where .= " AND {$col} >= ?";
            $params[] = $f['from'];
        }
        if (!empty($f['to']) && \app\Core\Validator::isValidDate($f['to'])) {
            $where .= " AND {$col} <= ?";
            $params[] = $f['to'];
        }
    }

    private static function sort(array $f, string $default, array $allowed): string
    {
        $col = $f['sort'] ?? '';
        $dir = strtoupper($f['dir'] ?? '') === 'DESC' ? 'DESC' : 'ASC';
        if ($col !== '' && in_array($col, $allowed, true)) {
            return $col . ' ' . $dir;
        }
        // allow "col DESC" style defaults
        if (str_contains($default, ' ')) {
            return $default;
        }
        return $default . ' ASC';
    }

    /** The report catalogue for the UI. */
    public static function catalogue(): array
    {
        return [
            'sales' => [
                'sales_register'    => ['Sales Register', 'salesRegister'],
                'sales_by_customer' => ['Sales by Customer', 'salesByCustomer'],
                'sales_by_item'     => ['Sales by Item', 'salesByItem'],
            ],
            'purchase' => [
                'purchase_register'    => ['Purchase Register', 'purchaseRegister'],
                'purchase_by_supplier' => ['Purchase by Supplier', 'purchaseBySupplier'],
            ],
            'parties' => [
                'customer_outstanding' => ['Customer Outstanding', 'customerOutstanding'],
                'supplier_payable'     => ['Supplier Payable', 'supplierPayable'],
            ],
            'stock' => [
                'stock_balance' => ['Stock Balance', 'stockBalanceReport'],
            ],
            'accounting' => [
                'trial_balance' => ['Trial Balance', 'trialBalance'],
                'profit_loss'   => ['Profit & Loss', 'profitAndLoss'],
                'balance_sheet' => ['Balance Sheet', 'balanceSheet'],
                'cash_book'     => ['Cash Book', 'cashBook'],
                'bank_book'     => ['Bank Book', 'bankBook'],
            ],
        ];
    }
}
