<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Database;
use app\Services\AccountDefaultService;
use app\Services\AccountService;
use app\Services\AuditService;
use app\Services\CompanyContextService;
use app\Services\FeatureService;
use app\Services\InventoryEngine;
use app\Services\PartyLedgerService;

/**
 * Dashboard — compact cards and tables. No charts (by design).
 * Every figure comes from the accounting ledger, stock ledger or documents.
 */
final class DashboardController extends Controller
{
    public function index(): string
    {
        $company = CompanyContextService::currentCompany();
        $companyId = $company['id'] ?? null;

        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        $counts = [
            'users'     => (int) Database::value('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'),
            'customers' => (int) Database::value('SELECT COUNT(*) FROM customers WHERE company_id = ? AND deleted_at IS NULL', [$companyId ?? 0]),
            'suppliers' => (int) Database::value('SELECT COUNT(*) FROM suppliers WHERE company_id = ? AND deleted_at IS NULL', [$companyId ?? 0]),
            'items'     => (int) Database::value('SELECT COUNT(*) FROM items WHERE company_id = ? AND deleted_at IS NULL', [$companyId ?? 0]),
        ];

        $data = [
            'title'         => 'Dashboard',
            'company'       => $company,
            'counts'        => $counts,
            'today'         => $today,
            'financeCards'  => $this->financeCards($companyId, $today, $monthStart),
            'recentUsers'   => Database::query(
                'SELECT u.name, u.email, u.status, r.name AS role_name
                 FROM users u JOIN roles r ON r.id = u.role_id
                 WHERE u.deleted_at IS NULL ORDER BY u.id DESC LIMIT 6'
            ),
            'recentActivity' => AuditService::recent(6),
            'recentSales'   => $companyId ? Database::query(
                'SELECT si.invoice_no, si.invoice_date, si.total, COALESCE(c.name, \'Walk-in\') AS customer
                 FROM sales_invoices si LEFT JOIN customers c ON c.id = si.customer_id
                 WHERE si.company_id = ? ORDER BY si.id DESC LIMIT 6',
                [$companyId]
            ) : [],
            'lowStock'      => $companyId ? array_slice(InventoryEngine::lowStock($companyId), 0, 6) : [],
            'topCustomers'  => $companyId ? $this->topCustomers($companyId) : [],
            'topSuppliers'  => $companyId ? $this->topSuppliers($companyId) : [],
        ];

        return $this->view('dashboard/index', $data)->render();
    }

    /** Top outstanding customers (from the AR ledger). */
    private function topCustomers(int $companyId): array
    {
        return Database::query(
            'SELECT c.name, c.code,
                    (SELECT COALESCE(SUM(al.debit),0) - COALESCE(SUM(al.credit),0)
                     FROM account_ledger al WHERE al.party_type = \'customer\' AND al.party_id = c.id) AS outstanding
             FROM customers c
             WHERE c.company_id = ? AND c.deleted_at IS NULL AND c.status = \'active\'
             HAVING outstanding > 0
             ORDER BY outstanding DESC LIMIT 6',
            [$companyId]
        );
    }

    /** Top payable suppliers (from the AP ledger). */
    private function topSuppliers(int $companyId): array
    {
        return Database::query(
            'SELECT s.name, s.code,
                    (SELECT COALESCE(SUM(al.credit),0) - COALESCE(SUM(al.debit),0)
                     FROM account_ledger al WHERE al.party_type = \'supplier\' AND al.party_id = s.id) AS payable
             FROM suppliers s
             WHERE s.company_id = ? AND s.deleted_at IS NULL AND s.status = \'active\'
             HAVING payable > 0
             ORDER BY payable DESC LIMIT 6',
            [$companyId]
        );
    }

    /** Finance cards — every figure computed from the ledger (no fabricated numbers). */
    private function financeCards(?int $companyId, string $today, string $monthStart): array
    {
        if ($companyId === null) {
            return $this->emptyCards();
        }

        $salesToday = (float) Database::value(
            "SELECT COALESCE(SUM(total),0) FROM sales_invoices WHERE company_id = ? AND status = 'posted' AND invoice_date = ?",
            [$companyId, $today]
        );
        $purchaseToday = (float) Database::value(
            "SELECT COALESCE(SUM(total),0) FROM purchase_invoices WHERE company_id = ? AND status = 'posted' AND invoice_date = ?",
            [$companyId, $today]
        );

        // Receipts/payments today from cash+bank ledger
        $cbIds = array_map('intval', array_column(Database::query(
            'SELECT id FROM accounts WHERE company_id = ? AND deleted_at IS NULL AND (is_cash = 1 OR is_bank = 1)',
            [$companyId]
        ), 'id'));
        $cbPlaceholder = $cbIds ? implode(',', $cbIds) : '0';
        $receiptsToday = $cbIds ? (float) Database::value(
            "SELECT COALESCE(SUM(debit),0) FROM account_ledger WHERE account_id IN ({$cbPlaceholder}) AND entry_date = ?",
            [$today]
        ) : 0.0;
        $paymentsToday = $cbIds ? (float) Database::value(
            "SELECT COALESCE(SUM(credit),0) FROM account_ledger WHERE account_id IN ({$cbPlaceholder}) AND entry_date = ?",
            [$today]
        ) : 0.0;

        $receivables = max(0.0, AccountService::balance((int) AccountDefaultService::get($companyId, 'receivable')));
        $payables = max(0.0, abs(AccountService::balance((int) AccountDefaultService::get($companyId, 'payable'))));
        $stockValue = AccountService::balance((int) AccountDefaultService::get($companyId, 'inventory'));

        $tb = AccountService::trialBalanceSums($companyId, $monthStart, $today);
        $income = 0.0;
        $expenses = 0.0;
        foreach ($tb as $row) {
            $net = (float) $row['credit'] - (float) $row['debit'];
            if ($row['account_type'] === 'income') {
                $income += $net;
            } elseif ($row['account_type'] === 'expense') {
                $expenses += $net;
            }
        }

        return [
            ['key' => 'sales',       'label' => "Today's Sales",     'icon' => 'cart',        'value' => $salesToday],
            ['key' => 'purchase',    'label' => "Today's Purchase",  'icon' => 'bag',         'value' => $purchaseToday],
            ['key' => 'receipts',    'label' => "Today's Receipts",  'icon' => 'arrow-down-circle', 'value' => $receiptsToday],
            ['key' => 'payments',    'label' => "Today's Payments",  'icon' => 'arrow-up-circle',   'value' => $paymentsToday],
            ['key' => 'receivables', 'label' => 'Receivables',       'icon' => 'people',      'value' => $receivables],
            ['key' => 'payables',    'label' => 'Payables',          'icon' => 'people',      'value' => $payables],
            ['key' => 'stock_value', 'label' => 'Stock Value',       'icon' => 'box-seam',    'value' => $stockValue],
            ['key' => 'profit',      'label' => 'Gross Profit (MTD)','icon' => 'graph-up',    'value' => $income - $expenses],
        ];
    }

    private function emptyCards(): array
    {
        $labels = [
            'sales' => "Today's Sales", 'purchase' => "Today's Purchase", 'receipts' => "Today's Receipts",
            'payments' => "Today's Payments", 'receivables' => 'Receivables', 'payables' => 'Payables',
            'stock_value' => 'Stock Value', 'profit' => 'Gross Profit (MTD)',
        ];
        $icons = ['cart', 'bag', 'arrow-down-circle', 'arrow-up-circle', 'people', 'people', 'box-seam', 'graph-up'];
        $out = [];
        foreach (array_keys($labels) as $i => $key) {
            $out[] = ['key' => $key, 'label' => $labels[$key], 'icon' => $icons[$i], 'value' => 0.0];
        }
        return $out;
    }
}
