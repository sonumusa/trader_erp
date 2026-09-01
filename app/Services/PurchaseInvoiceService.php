<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Purchase invoice service.
 *
 * Saving a purchase invoice immediately:
 *   1. receives stock (inventory engine) at the net purchase rate
 *   2. posts balanced accounting through the accounting engine:
 *        Inventory       Dr   (item → group → company default)
 *        Tax Receivable  Dr   (when the item has a tax category)
 *              Accounts Payable  Cr  (party-tagged, supplier)
 *   3. when a payment mode + paid amount are given, posts the payment
 *      leg (AP Dr / Cash-or-Bank Cr) as its own balanced voucher
 * All inside one DB transaction — stock can never be updated without the
 * books (and vice versa).
 */
final class PurchaseInvoiceService
{
    /**
     * @return int purchase_invoice id
     */
    public static function create(int $companyId, array $data): int
    {
        $supplierId = (int) ($data['supplier_id'] ?? 0);
        $supplier = Database::row('SELECT * FROM suppliers WHERE id = ? AND company_id = ? AND deleted_at IS NULL', [$supplierId, $companyId]);
        if (!$supplier) {
            throw new \RuntimeException('Select a valid supplier.');
        }

        $rawLines = $data['lines'] ?? [];
        if ($rawLines === []) {
            throw new \RuntimeException('Add at least one item to the invoice.');
        }

        // Calculate lines + totals first (validates everything up front)
        $lines = [];
        foreach ($rawLines as $raw) {
            $lines[] = PurchaseService::calculateLine($raw);
        }
        $totals = PurchaseService::totals($lines);

        $paidAmount = round((float) ($data['paid_amount'] ?? 0), 2);
        $paymentModeId = (int) ($data['payment_mode_id'] ?? 0);
        if ($paidAmount > 0 && $paymentModeId <= 0) {
            throw new \RuntimeException('Select a payment mode for the amount paid.');
        }
        if ($paidAmount < 0 || $paidAmount > $totals['total'] + 0.001) {
            throw new \RuntimeException('The paid amount cannot exceed the invoice total.');
        }

        $invoiceNo = DocumentNumberService::next('purchase_invoice', $companyId, $data['branch_id'] ?? null);
        $entryDate = (string) $data['invoice_date'];
        ClosingPeriodService::guardDate($entryDate, 'create purchase invoice', 'purchase');
        $userId = AuthService::id();
        $now = date('Y-m-d H:i:s');

        return (int) Database::transaction(function () use ($companyId, $data, $supplier, $lines, $totals, $paidAmount, $paymentModeId, $invoiceNo, $entryDate, $userId, $now) {
            Database::execute(
                'INSERT INTO purchase_invoices
                    (company_id, branch_id, invoice_no, invoice_date, supplier_id, reference_order_id,
                     warehouse_id, payment_mode_id, paid_amount, subtotal, discount_total, tax_total, total, narration,
                     status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'posted\', ?, ?, ?)',
                [
                    $companyId,
                    $data['branch_id'] ?? null,
                    $invoiceNo,
                    $entryDate,
                    (int) $supplier['id'],
                    !empty($data['reference_order_id']) ? (int) $data['reference_order_id'] : null,
                    (int) ($data['warehouse_id'] ?? self::defaultWarehouse($companyId)),
                    $paymentModeId > 0 ? $paymentModeId : null,
                    $paidAmount,
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
                'INSERT INTO purchase_invoice_items
                    (invoice_id, item_id, uom_id, quantity, base_qty, rate, discount, tax_id, tax_rate, tax_amount, amount)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $accountingLines = [];

            foreach ($lines as $line) {
                $itemId = (int) $line['item_id'];
                $uomId = $line['uom_id'];

                // Stock in at net-of-discount rate per stock unit
                $rateStock = round(($line['amount'] - $line['tax_amount']) / max($line['base_qty'], 0.0001), 4);
                if ($rateStock < 0) {
                    $rateStock = 0.0;
                }

                InventoryEngine::receive([
                    'company_id'    => $companyId,
                    'item_id'       => $itemId,
                    'warehouse_id'  => (int) ($data['warehouse_id'] ?? self::defaultWarehouse($companyId)),
                    'entry_date'    => $entryDate,
                    'document_type' => 'purchase_invoice',
                    'document_id'   => $invoiceId,
                    'document_no'   => $invoiceNo,
                    'qty'           => $line['base_qty'],
                    'rate'          => $rateStock,
                    'uom_id'        => $uomId ?: null,
                    'batch'         => $line['batch'] ?? null,
                    'serials'       => $line['serials'] ?? [],
                    'created_by'    => $userId,
                ]);

                // Accounting: inventory Dr (net), tax receivable Dr, AP Cr (party)
                $inventoryAccountId = (int) AccountDefaultService::forItem($companyId, $itemId, 'inventory');
                if ($inventoryAccountId <= 0) {
                    throw new \RuntimeException('No Inventory account is configured for item ' . $line['item']['name'] . '.');
                }
                $net = round($line['amount'] - $line['tax_amount'], 2);
                $accountingLines[] = ['account_id' => $inventoryAccountId, 'debit' => $net, 'credit' => 0, 'narration' => $line['item']['name']];

                if ($line['tax_amount'] > 0) {
                    $taxRecvId = (int) AccountDefaultService::get($companyId, 'tax_receivable');
                    if ($taxRecvId > 0) {
                        $accountingLines[] = ['account_id' => $taxRecvId, 'debit' => $line['tax_amount'], 'credit' => 0, 'narration' => 'Input tax — ' . $line['item']['name']];
                    }
                }

                $stmtItem->execute([
                    $invoiceId, $itemId, $uomId ?: null, $line['quantity'], $line['base_qty'],
                    $line['rate'], $line['discount'], $line['tax_id'], $line['tax_rate'], $line['tax_amount'], $line['amount'],
                ]);
            }

            // AP side (single party line)
            $apId = (int) AccountDefaultService::get($companyId, 'payable');
            if ($apId <= 0) {
                throw new \RuntimeException('No Accounts Payable account is configured.');
            }
            $accountingLines[] = [
                'account_id' => $apId,
                'debit'      => 0,
                'credit'     => $totals['total'],
                'party_type' => 'supplier',
                'party_id'   => (int) $supplier['id'],
                'narration'  => 'Purchase invoice ' . $invoiceNo,
            ];

            AccountingEngine::post([
                'company_id' => $companyId,
                'branch_id'  => $data['branch_id'] ?? null,
                'entry_date' => $entryDate,
                'voucher_type' => 'purchase_invoice',
                'entry_no'   => $invoiceNo,
                'source_document_type' => 'purchase_invoice',
                'source_document_id'   => $invoiceId,
                'narration'  => 'Purchase from ' . $supplier['name'] . ' — ' . $invoiceNo,
                'lines'      => $accountingLines,
            ]);

            // Payment leg (cash purchase)
            if ($paidAmount > 0) {
                $mode = PaymentModeService::get($paymentModeId);
                if (!$mode || (int) $mode['company_id'] !== $companyId) {
                    throw new \RuntimeException('Invalid payment mode.');
                }
                $cashAccountId = (int) ($mode['account_id'] ?? 0);
                if ($cashAccountId <= 0) {
                    $cashAccountId = (int) AccountDefaultService::get($companyId, 'cash');
                }
                if ($cashAccountId <= 0) {
                    throw new \RuntimeException('No cash/bank account is linked to the payment mode.');
                }

                $paymentNo = DocumentNumberService::next(
                    (int) $mode['is_cash'] === 1 ? 'cash_payment' : 'bank_payment',
                    $companyId,
                    $data['branch_id'] ?? null
                );

                AccountingEngine::post([
                    'company_id' => $companyId,
                    'branch_id'  => $data['branch_id'] ?? null,
                    'entry_date' => $entryDate,
                    'voucher_type' => (int) $mode['is_cash'] === 1 ? 'cash_payment' : 'bank_payment',
                    'entry_no'   => $paymentNo,
                    'source_document_type' => 'purchase_invoice',
                    'source_document_id'   => $invoiceId,
                    'narration'  => 'Payment for ' . $invoiceNo . ' via ' . $mode['name'],
                    'lines'      => [
                        ['account_id' => $apId, 'debit' => $paidAmount, 'credit' => 0, 'party_type' => 'supplier', 'party_id' => (int) $supplier['id']],
                        ['account_id' => $cashAccountId, 'debit' => 0, 'credit' => $paidAmount],
                    ],
                ]);
            }

            return $invoiceId;
        });
    }

    /** Cancel: reverse stock + reverse accounting, keep the document. */
    public static function cancel(int $invoiceId, string $reason): void
    {
        $invoice = Database::row('SELECT * FROM purchase_invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice || $invoice['status'] === 'cancelled') {
            throw new \RuntimeException('Invoice not found or already cancelled.');
        }

        ClosingPeriodService::guardDate((string) $invoice['invoice_date'], 'cancel purchase invoice', 'purchase');
        Database::transaction(function () use ($invoice, $invoiceId, $reason) {
            $userId = AuthService::id();
            $lines = Database::query('SELECT * FROM purchase_invoice_items WHERE invoice_id = ?', [$invoiceId]);

            foreach ($lines as $line) {
                // Reverse the stock receipt (issue back out)
                InventoryEngine::issue([
                    'company_id'    => (int) $invoice['company_id'],
                    'item_id'       => (int) $line['item_id'],
                    'warehouse_id'  => (int) ($invoice['warehouse_id'] ?? 0),
                    'entry_date'    => date('Y-m-d'),
                    'document_type' => 'purchase_invoice',
                    'document_id'   => $invoiceId,
                    'document_no'   => $invoice['invoice_no'] . ' (R)',
                    'qty'           => (float) $line['base_qty'],
                    'uom_id'        => $line['uom_id'] ? (int) $line['uom_id'] : null,
                    'created_by'    => $userId,
                ]);
            }

            // Reverse the accounting entries (invoice JE + payment JE)
            $entries = Database::query(
                'SELECT id FROM journal_entries WHERE source_document_type = \'purchase_invoice\' AND source_document_id = ? AND status = \'posted\' ORDER BY id',
                [$invoiceId]
            );
            foreach ($entries as $entry) {
                AccountingEngine::reverse((int) $entry['id'], 'Cancel ' . $invoice['invoice_no'] . ': ' . $reason, $userId);
            }

            Database::execute(
                'UPDATE purchase_invoices SET status = \'cancelled\', cancelled_by = ?, cancelled_at = ?, cancelled_reason = ?, updated_at = ? WHERE id = ?',
                [$userId, date('Y-m-d H:i:s'), mb_substr($reason, 0, 255), date('Y-m-d H:i:s'), $invoiceId]
            );
        });
    }

    public static function paginate(int $companyId, int $page = 1, int $perPage = 20, string $search = '', ?string $from = null, ?string $to = null): array
    {
        $where = 'WHERE pi.company_id = ?';
        $params = [$companyId];
        if ($search !== '') {
            $where .= ' AND (pi.invoice_no LIKE ? OR s.name LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($from !== null) {
            $where .= ' AND pi.invoice_date >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $where .= ' AND pi.invoice_date <= ?';
            $params[] = $to;
        }

        $total = (int) Database::value("SELECT COUNT(*) FROM purchase_invoices pi JOIN suppliers s ON s.id = pi.supplier_id {$where}", $params);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::query(
            "SELECT pi.*, s.name AS supplier_name, pm.name AS payment_mode_name
             FROM purchase_invoices pi
             JOIN suppliers s ON s.id = pi.supplier_id
             LEFT JOIN payment_modes pm ON pm.id = pi.payment_mode_id
             {$where}
             ORDER BY pi.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage)), 'page' => $page];
    }

    public static function find(int $id): ?array
    {
        $invoice = Database::row('SELECT * FROM purchase_invoices WHERE id = ?', [$id]);
        if (!$invoice) {
            return null;
        }
        $invoice['supplier'] = Database::row('SELECT * FROM suppliers WHERE id = ?', [(int) $invoice['supplier_id']]);
        $invoice['items'] = Database::query(
            'SELECT pii.*, i.item_code, i.name AS item_name, u.code AS uom_code
             FROM purchase_invoice_items pii
             JOIN items i ON i.id = pii.item_id
             LEFT JOIN uoms u ON u.id = pii.uom_id
             WHERE pii.invoice_id = ?
             ORDER BY pii.id',
            [$id]
        );
        $invoice['payment_mode'] = $invoice['payment_mode_id']
            ? Database::row('SELECT * FROM payment_modes WHERE id = ?', [(int) $invoice['payment_mode_id']])
            : null;
        return $invoice;
    }

    private static function defaultWarehouse(int $companyId): int
    {
        $id = (int) Database::value(
            'SELECT id FROM warehouses WHERE company_id = ? AND is_default = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1',
            [$companyId]
        );
        if ($id <= 0) {
            $id = (int) Database::value(
                'SELECT id FROM warehouses WHERE company_id = ? AND deleted_at IS NULL ORDER BY id LIMIT 1',
                [$companyId]
            );
        }
        if ($id <= 0) {
            throw new \RuntimeException('No warehouse is available for stock receipt.');
        }
        return $id;
    }
}
