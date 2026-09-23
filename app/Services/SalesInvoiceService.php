<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Sales invoice service.
 *
 * Saving a sales invoice immediately:
 *   1. issues stock (inventory engine) → COGS from the cost layers
 *   2. posts balanced accounting through the accounting engine:
 *        Cash / Accounts Receivable  Dr  (party-tagged when a customer is set)
 *              Sales                        Cr  (net of discounts)
 *              Tax Payable                  Cr  (output tax)
 *        COGS  Dr  /  Inventory  Cr        (cost of goods sold)
 *   3. optional payment mode + received amount → immediate receipt voucher
 *      (Cash Receipt / Bank Receipt) in the same transaction
 */
final class SalesInvoiceService
{
    public static function create(int $companyId, array $data): int
    {
        $customerId = (int) ($data['customer_id'] ?? 0);
        $customer = null;
        if ($customerId > 0) {
            $customer = Database::row('SELECT * FROM customers WHERE id = ? AND company_id = ? AND deleted_at IS NULL', [$customerId, $companyId]);
            if (!$customer) {
                throw new \RuntimeException('Select a valid customer.');
            }
        }

        $rawLines = $data['lines'] ?? [];
        if ($rawLines === []) {
            throw new \RuntimeException('Add at least one item to the invoice.');
        }

        $lines = [];
        foreach ($rawLines as $raw) {
            $raw['transaction_date'] = (string) ($data['invoice_date'] ?? date('Y-m-d'));
            $lines[] = PurchaseService::calculateLine($raw);
        }
        PurchaseService::validateOrderRemaining('sales', (int) ($data['reference_order_id'] ?? 0), $lines, $companyId);
        $totals = PurchaseService::totals($lines);

        $receivedAmount = round((float) ($data['received_amount'] ?? 0), 2);
        $paymentModeId = (int) ($data['payment_mode_id'] ?? 0);
        if ($receivedAmount > 0 && $paymentModeId <= 0) {
            throw new \RuntimeException('Select a payment mode for the amount received.');
        }
        if ($receivedAmount < 0 || $receivedAmount > $totals['total'] + 0.001) {
            throw new \RuntimeException('The received amount cannot exceed the invoice total.');
        }

        $invoiceNo = DocumentNumberService::next('sales_invoice', $companyId, $data['branch_id'] ?? null);
        $entryDate = (string) $data['invoice_date'];
        ClosingPeriodService::guardDate($entryDate, 'create sales invoice', 'sales');
        $userId = AuthService::id();
        $now = date('Y-m-d H:i:s');
        $warehouseId = (int) ($data['warehouse_id'] ?? self::defaultWarehouse($companyId));

        return (int) Database::transaction(function () use ($companyId, $data, $customer, $customerId, $lines, $totals, $receivedAmount, $paymentModeId, $invoiceNo, $entryDate, $userId, $now, $warehouseId) {
            Database::execute(
                'INSERT INTO sales_invoices
                    (company_id, branch_id, invoice_no, invoice_date, customer_id, reference_order_id,
                     warehouse_id, payment_mode_id, received_amount, subtotal, discount_total, tax_total, total, cogs_total, narration,
                     status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, \'posted\', ?, ?, ?)',
                [
                    $companyId,
                    $data['branch_id'] ?? null,
                    $invoiceNo,
                    $entryDate,
                    $customerId > 0 ? $customerId : null,
                    !empty($data['reference_order_id']) ? (int) $data['reference_order_id'] : null,
                    $warehouseId,
                    $paymentModeId > 0 ? $paymentModeId : null,
                    $receivedAmount,
                    $totals['subtotal'],
                    $totals['discount_total'],
                    $totals['tax_total'],
                    $totals['total'],
                    $data['narration'] ?? '',
                    $userId,
                    $now,
                    $now,
                ]
            );
            $invoiceId = (int) Database::lastInsertId();

            $stmtItem = Database::pdo()->prepare(
                'INSERT INTO sales_invoice_items
                    (invoice_id, item_id, uom_id, quantity, base_qty, rate, discount, tax_id, tax_rate, tax_amount, amount, cogs_amount)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $accountingLines = [];
            $cogsTotal = 0.0;

            foreach ($lines as $line) {
                $itemId = (int) $line['item_id'];

                // Stock out → COGS
                $issue = InventoryEngine::issue([
                    'company_id'    => $companyId,
                    'item_id'       => $itemId,
                    'warehouse_id'  => $warehouseId,
                    'entry_date'    => $entryDate,
                    'document_type' => 'sales_invoice',
                    'document_id'   => $invoiceId,
                    'document_no'   => $invoiceNo,
                    'qty'           => $line['base_qty'],
                    'uom_id'        => $line['uom_id'] ?: null,
                    'batch'         => $line['batch'] ?? null,
                    'serials'       => $line['serials'] ?? [],
                    'created_by'    => $userId,
                ]);
                $cogs = round($issue['cogs'], 2);
                $cogsTotal += $cogs;

                // Accounting: COGS Dr / Inventory Cr
                $cogsAccountId = (int) AccountDefaultService::forItem($companyId, $itemId, 'cogs');
                $inventoryAccountId = (int) AccountDefaultService::forItem($companyId, $itemId, 'inventory');
                if ($cogsAccountId <= 0 || $inventoryAccountId <= 0) {
                    throw new \RuntimeException('COGS and Inventory accounts must be configured for item ' . $line['item']['name'] . '.');
                }
                if ($cogs > 0) {
                    $accountingLines[] = ['account_id' => $cogsAccountId, 'debit' => $cogs, 'credit' => 0, 'narration' => 'COGS — ' . $line['item']['name']];
                    $accountingLines[] = ['account_id' => $inventoryAccountId, 'debit' => 0, 'credit' => $cogs, 'narration' => 'Stock out — ' . $line['item']['name']];
                }

                $stmtItem->execute([
                    $invoiceId, $itemId, $line['uom_id'] ?: null, $line['quantity'], $line['base_qty'],
                    $line['rate'], $line['discount'], $line['tax_id'], $line['tax_rate'], $line['tax_amount'], $line['amount'], $cogs,
                ]);
            }

            // Sales / receivable side
            $salesAccountId = (int) AccountDefaultService::get($companyId, 'sales');
            if ($salesAccountId <= 0) {
                throw new \RuntimeException('No Sales account is configured.');
            }

            // Dr side: cash (settled / walk-in) or AR (credit to a customer).
            // The party tag goes ONLY on receivable lines so a customer's
            // outstanding stays correct (fully-paid cash sales are not
            // receivable movements).
            $receivedByMode = $paymentModeId > 0 ? PaymentModeService::get($paymentModeId) : null;
            $fullSettlement = $receivedAmount >= $totals['total'] - 0.001;
            $isCreditSale = $customerId > 0 && !$fullSettlement;

            if ($isCreditSale) {
                $arId = (int) AccountDefaultService::get($companyId, 'receivable');
                if ($arId <= 0) {
                    throw new \RuntimeException('No Accounts Receivable account is configured.');
                }
                $accountingLines[] = [
                    'account_id' => $arId,
                    'debit'      => $totals['total'],
                    'credit'     => 0,
                    'narration'  => 'Sales ' . $invoiceNo,
                    'party_type' => 'customer',
                    'party_id'   => $customerId,
                ];
            } else {
                $cashAccountId = (int) ($receivedByMode['account_id'] ?? 0);
                if ($cashAccountId <= 0) {
                    $cashAccountId = (int) AccountDefaultService::get($companyId, 'cash');
                }
                if ($cashAccountId <= 0) {
                    throw new \RuntimeException('No cash/bank account is linked to the payment mode.');
                }
                $accountingLines[] = ['account_id' => $cashAccountId, 'debit' => $totals['total'], 'credit' => 0, 'narration' => 'Sales ' . $invoiceNo];
            }

            // Sales Cr (net) + Tax Payable Cr
            $accountingLines[] = ['account_id' => $salesAccountId, 'debit' => 0, 'credit' => $totals['subtotal'], 'narration' => 'Sales ' . $invoiceNo];
            if ($totals['tax_total'] > 0) {
                $taxPayableId = (int) AccountDefaultService::get($companyId, 'tax_payable');
                if ($taxPayableId <= 0) {
                    throw new \RuntimeException('No Tax Payable account is configured.');
                }
                $accountingLines[] = ['account_id' => $taxPayableId, 'debit' => 0, 'credit' => $totals['tax_total'], 'narration' => 'Output tax — ' . $invoiceNo];
            }

            AccountingEngine::post([
                'company_id' => $companyId,
                'branch_id'  => $data['branch_id'] ?? null,
                'entry_date' => $entryDate,
                'voucher_type' => 'sales_invoice',
                'entry_no'   => $invoiceNo,
                'source_document_type' => 'sales_invoice',
                'source_document_id'   => $invoiceId,
                'narration'  => 'Sale to ' . ($customer['name'] ?? 'Walk-in customer') . ' — ' . $invoiceNo,
                'lines'      => $accountingLines,
            ]);

            // Update COGS total on the invoice
            Database::execute('UPDATE sales_invoices SET cogs_total = ? WHERE id = ?', [$cogsTotal, $invoiceId]);

            // Receipt leg (partial payment on a credit sale)
            if ($receivedAmount > 0 && $isCreditSale) {
                $mode = $receivedByMode;
                if (!$mode || (int) $mode['company_id'] !== $companyId) {
                    throw new \RuntimeException('Invalid payment mode.');
                }
                $cashAccountId = (int) ($mode['account_id'] ?? 0);
                if ($cashAccountId <= 0) {
                    $cashAccountId = (int) AccountDefaultService::get($companyId, 'cash');
                }
                $arId = (int) AccountDefaultService::get($companyId, 'receivable');

                $receiptNo = DocumentNumberService::next(
                    (int) $mode['is_cash'] === 1 ? 'cash_receipt' : 'bank_receipt',
                    $companyId,
                    $data['branch_id'] ?? null
                );

                AccountingEngine::post([
                    'company_id' => $companyId,
                    'branch_id'  => $data['branch_id'] ?? null,
                    'entry_date' => $entryDate,
                    'voucher_type' => (int) $mode['is_cash'] === 1 ? 'cash_receipt' : 'bank_receipt',
                    'entry_no'   => $receiptNo,
                    'source_document_type' => 'sales_invoice',
                    'source_document_id'   => $invoiceId,
                    'narration'  => 'Receipt for ' . $invoiceNo . ' via ' . $mode['name'],
                    'lines'      => [
                        ['account_id' => $cashAccountId, 'debit' => $receivedAmount, 'credit' => 0],
                        ['account_id' => $arId, 'debit' => 0, 'credit' => $receivedAmount, 'party_type' => 'customer', 'party_id' => $customerId],
                    ],
                ]);
            }

            return $invoiceId;
        });
    }

    /** Cancel: reverse stock (restock) + reverse accounting; keep the document. */
    public static function cancel(int $invoiceId, string $reason): void
    {
        $invoice = Database::row('SELECT * FROM sales_invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice || $invoice['status'] === 'cancelled') {
            throw new \RuntimeException('Invoice not found or already cancelled.');
        }

        ClosingPeriodService::guardDate((string) $invoice['invoice_date'], 'cancel sales invoice', 'sales');
        Database::transaction(function () use ($invoice, $invoiceId, $reason) {
            $userId = AuthService::id();
            $lines = Database::query('SELECT * FROM sales_invoice_items WHERE invoice_id = ?', [$invoiceId]);

            foreach ($lines as $line) {
                InventoryEngine::receive([
                    'company_id'    => (int) $invoice['company_id'],
                    'item_id'       => (int) $line['item_id'],
                    'warehouse_id'  => (int) $invoice['warehouse_id'],
                    'entry_date'    => date('Y-m-d'),
                    'document_type' => 'sales_invoice',
                    'document_id'   => $invoiceId,
                    'document_no'   => $invoice['invoice_no'] . ' (R)',
                    'qty'           => (float) $line['base_qty'],
                    'rate'          => (float) ($line['cogs_amount'] > 0 ? $line['cogs_amount'] / max($line['base_qty'], 0.0001) : 0),
                    'uom_id'        => $line['uom_id'] ? (int) $line['uom_id'] : null,
                    'created_by'    => $userId,
                ]);
            }

            $entries = Database::query(
                'SELECT id FROM journal_entries WHERE source_document_type = \'sales_invoice\' AND source_document_id = ? AND status = \'posted\' ORDER BY id',
                [$invoiceId]
            );
            foreach ($entries as $entry) {
                AccountingEngine::reverse((int) $entry['id'], 'Cancel ' . $invoice['invoice_no'] . ': ' . $reason, $userId);
            }

            Database::execute(
                'UPDATE sales_invoices SET status = \'cancelled\', cancelled_by = ?, cancelled_at = ?, cancelled_reason = ?, updated_at = ? WHERE id = ?',
                [$userId, date('Y-m-d H:i:s'), mb_substr($reason, 0, 255), date('Y-m-d H:i:s'), $invoiceId]
            );
        });
    }

    public static function paginate(int $companyId, int $page = 1, int $perPage = 20, string $search = '', ?string $from = null, ?string $to = null): array
    {
        $where = 'WHERE si.company_id = ?';
        $params = [$companyId];
        if ($search !== '') {
            $where .= ' AND (si.invoice_no LIKE ? OR COALESCE(c.name,\'\') LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($from !== null) {
            $where .= ' AND si.invoice_date >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $where .= ' AND si.invoice_date <= ?';
            $params[] = $to;
        }

        $total = (int) Database::value("SELECT COUNT(*) FROM sales_invoices si LEFT JOIN customers c ON c.id = si.customer_id {$where}", $params);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::query(
            "SELECT si.*, c.name AS customer_name, pm.name AS payment_mode_name
             FROM sales_invoices si
             LEFT JOIN customers c ON c.id = si.customer_id
             LEFT JOIN payment_modes pm ON pm.id = si.payment_mode_id
             {$where}
             ORDER BY si.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage)), 'page' => $page];
    }

    public static function find(int $id): ?array
    {
        $invoice = Database::row('SELECT * FROM sales_invoices WHERE id = ?', [$id]);
        if (!$invoice) {
            return null;
        }
        $invoice['customer'] = $invoice['customer_id']
            ? Database::row('SELECT * FROM customers WHERE id = ?', [(int) $invoice['customer_id']])
            : null;
        $invoice['items'] = Database::query(
            'SELECT sii.*, i.item_code, i.name AS item_name, u.code AS uom_code
             FROM sales_invoice_items sii
             JOIN items i ON i.id = sii.item_id
             LEFT JOIN uoms u ON u.id = sii.uom_id
             WHERE sii.invoice_id = ?
             ORDER BY sii.id',
            [$id]
        );
        $invoice['payment_mode'] = $invoice['payment_mode_id']
            ? Database::row('SELECT * FROM payment_modes WHERE id = ?', [(int) $invoice['payment_mode_id']])
            : null;
        return $invoice;
    }

    private static function defaultWarehouse(int $companyId): int
    {
        $id = (int) Database::value('SELECT id FROM warehouses WHERE company_id = ? AND is_default = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1', [$companyId]);
        if ($id <= 0) {
            $id = (int) Database::value('SELECT id FROM warehouses WHERE company_id = ? AND deleted_at IS NULL ORDER BY id LIMIT 1', [$companyId]);
        }
        if ($id <= 0) {
            throw new \RuntimeException('No warehouse is available for stock issue.');
        }
        return $id;
    }
}
