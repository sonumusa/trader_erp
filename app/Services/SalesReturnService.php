<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Sales return service.
 *
 * A return reverses a sale's effects:
 *   stock back IN (inventory engine receive — goods returned to stock)
 *   accounting reversal through the engine:
 *        Sales Returns        Dr  (net amount, contra-income)
 *        Tax Payable          Dr  (output tax reversed)
 *              Cash or Accounts Receivable  Cr  (party-tagged when customer)
 *        Inventory            Dr  (goods back at original COGS rate)
 *              COGS                 Cr  (COGS reversal)
 * The original invoice may be referenced (optional); returns without a
 * reference are allowed (walk-in refunds post to cash).
 */
final class SalesReturnService
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

        $referenceInvoiceId = !empty($data['reference_invoice_id']) ? (int) $data['reference_invoice_id'] : null;
        $referenceInvoice = null;
        if ($referenceInvoiceId !== null) {
            $referenceInvoice = Database::row(
                'SELECT * FROM sales_invoices WHERE id = ? AND company_id = ? AND status = \'posted\'',
                [$referenceInvoiceId, $companyId]
            );
            if (!$referenceInvoice) {
                throw new \RuntimeException('The referenced sales invoice is not valid (it must be posted).');
            }
            if ($customerId > 0 && (int) $referenceInvoice['customer_id'] !== $customerId) {
                throw new \RuntimeException('The referenced invoice belongs to a different customer.');
            }
        }

        $rawLines = $data['lines'] ?? [];
        if ($rawLines === []) {
            throw new \RuntimeException('Add at least one item to the return.');
        }

        $lines = [];
        foreach ($rawLines as $raw) {
            $lines[] = PurchaseService::calculateLine($raw);
        }
        $totals = PurchaseService::totals($lines);

        $returnNo = DocumentNumberService::next('sales_return', $companyId, $data['branch_id'] ?? null);
        $entryDate = (string) $data['return_date'];
        ClosingPeriodService::guardDate($entryDate, 'create sales return', 'sales');
        $userId = AuthService::id();
        $now = date('Y-m-d H:i:s');
        $warehouseId = (int) ($data['warehouse_id'] ?? self::defaultWarehouse($companyId));

        return (int) Database::transaction(function () use ($companyId, $data, $customer, $customerId, $lines, $totals, $returnNo, $entryDate, $userId, $now, $warehouseId, $referenceInvoiceId, $referenceInvoice) {
            Database::execute(
                'INSERT INTO sales_returns
                    (company_id, branch_id, return_no, return_date, customer_id, reference_invoice_id,
                     warehouse_id, subtotal, discount_total, tax_total, total, cogs_total, narration,
                     status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, \'posted\', ?, ?, ?)',
                [
                    $companyId,
                    $data['branch_id'] ?? null,
                    $returnNo,
                    $entryDate,
                    $customerId > 0 ? $customerId : null,
                    $referenceInvoiceId,
                    $warehouseId,
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
            $returnId = (int) Database::lastInsertId();

            $stmtItem = Database::pdo()->prepare(
                'INSERT INTO sales_return_items
                    (return_id, item_id, uom_id, quantity, base_qty, rate, discount, tax_id, tax_rate, tax_amount, amount, cogs_amount)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $accountingLines = [];
            $cogsTotal = 0.0;

            foreach ($lines as $line) {
                $itemId = (int) $line['item_id'];

                // COGS reversal rate: original invoice line if referenced,
                // otherwise the item's current average rate
                $cogsRate = 0.0;
                if ($referenceInvoice !== null) {
                    $origLine = Database::row(
                        'SELECT cogs_amount, base_qty FROM sales_invoice_items WHERE invoice_id = ? AND item_id = ? ORDER BY id LIMIT 1',
                        [$referenceInvoiceId, $itemId]
                    );
                    if ($origLine && (float) $origLine['base_qty'] > 0) {
                        $cogsRate = round((float) $origLine['cogs_amount'] / (float) $origLine['base_qty'], 4);
                    }
                }
                if ($cogsRate <= 0) {
                    $cogsRate = InventoryEngine::averageRate($itemId, $warehouseId);
                }
                $lineCogs = round($line['base_qty'] * $cogsRate, 2);
                $cogsTotal += $lineCogs;

                // Stock back in
                InventoryEngine::receive([
                    'company_id'    => $companyId,
                    'item_id'       => $itemId,
                    'warehouse_id'  => $warehouseId,
                    'entry_date'    => $entryDate,
                    'document_type' => 'sales_return',
                    'document_id'   => $returnId,
                    'document_no'   => $returnNo,
                    'qty'           => $line['base_qty'],
                    'rate'          => $cogsRate,
                    'uom_id'        => $line['uom_id'] ?: null,
                    'batch'         => $line['batch'] ?? null,
                    'serials'       => $line['serials'] ?? [],
                    'created_by'    => $userId,
                ]);

                // Accounting: Inventory Dr / COGS Cr (reversal)
                $inventoryAccountId = (int) AccountDefaultService::forItem($companyId, $itemId, 'inventory');
                $cogsAccountId = (int) AccountDefaultService::forItem($companyId, $itemId, 'cogs');
                if ($inventoryAccountId <= 0 || $cogsAccountId <= 0) {
                    throw new \RuntimeException('Inventory and COGS accounts must be configured for item ' . $line['item']['name'] . '.');
                }
                if ($lineCogs > 0) {
                    $accountingLines[] = ['account_id' => $inventoryAccountId, 'debit' => $lineCogs, 'credit' => 0, 'narration' => 'Return to stock — ' . $line['item']['name']];
                    $accountingLines[] = ['account_id' => $cogsAccountId, 'debit' => 0, 'credit' => $lineCogs, 'narration' => 'COGS reversal — ' . $line['item']['name']];
                }

                $stmtItem->execute([
                    $returnId, $itemId, $line['uom_id'] ?: null, $line['quantity'], $line['base_qty'],
                    $line['rate'], $line['discount'], $line['tax_id'], $line['tax_rate'], $line['tax_amount'], $line['amount'], $lineCogs,
                ]);
            }

            // Reverse-to account: AR for credit sales / customers, cash otherwise
            $wasCreditSale = false;
            if ($referenceInvoice !== null) {
                $wasCreditSale = (int) $referenceInvoice['customer_id'] > 0
                    && (float) $referenceInvoice['received_amount'] < (float) $referenceInvoice['total'] - 0.001;
            } elseif ($customerId > 0) {
                $wasCreditSale = true;
            }

            if ($wasCreditSale) {
                $arId = (int) AccountDefaultService::get($companyId, 'receivable');
                if ($arId <= 0) {
                    throw new \RuntimeException('No Accounts Receivable account is configured.');
                }
                $accountingLines[] = [
                    'account_id' => $arId,
                    'debit'      => 0,
                    'credit'     => $totals['total'],
                    'narration'  => 'Sales return ' . $returnNo,
                    'party_type' => 'customer',
                    'party_id'   => $customerId > 0 ? $customerId : (int) $referenceInvoice['customer_id'],
                ];
            } else {
                $cashAccountId = (int) AccountDefaultService::get($companyId, 'cash');
                if ($cashAccountId <= 0) {
                    throw new \RuntimeException('No cash account is configured for the refund.');
                }
                $accountingLines[] = ['account_id' => $cashAccountId, 'debit' => 0, 'credit' => $totals['total'], 'narration' => 'Sales return refund ' . $returnNo];
            }

            // Sales Returns Dr (net) + Tax Payable Dr
            $salesReturnId = (int) AccountDefaultService::get($companyId, 'sales_return');
            if ($salesReturnId <= 0) {
                throw new \RuntimeException('No Sales Return account is configured.');
            }
            $accountingLines[] = ['account_id' => $salesReturnId, 'debit' => $totals['subtotal'], 'credit' => 0, 'narration' => 'Sales return ' . $returnNo];
            if ($totals['tax_total'] > 0) {
                $taxPayableId = (int) AccountDefaultService::get($companyId, 'tax_payable');
                if ($taxPayableId <= 0) {
                    throw new \RuntimeException('No Tax Payable account is configured.');
                }
                $accountingLines[] = ['account_id' => $taxPayableId, 'debit' => $totals['tax_total'], 'credit' => 0, 'narration' => 'Output tax reversal ' . $returnNo];
            }

            AccountingEngine::post([
                'company_id' => $companyId,
                'branch_id'  => $data['branch_id'] ?? null,
                'entry_date' => $entryDate,
                'voucher_type' => 'sales_return',
                'entry_no'   => $returnNo,
                'source_document_type' => 'sales_return',
                'source_document_id'   => $returnId,
                'narration'  => 'Sales return from ' . ($customer['name'] ?? 'Walk-in customer') . ' — ' . $returnNo,
                'lines'      => $accountingLines,
            ]);

            Database::execute('UPDATE sales_returns SET cogs_total = ? WHERE id = ?', [$cogsTotal, $returnId]);

            return $returnId;
        });
    }

    /** Cancel: reverse the return (issue stock back out) + reverse accounting. */
    public static function cancel(int $returnId, string $reason): void
    {
        $return = Database::row('SELECT * FROM sales_returns WHERE id = ?', [$returnId]);
        if (!$return || $return['status'] === 'cancelled') {
            throw new \RuntimeException('Return not found or already cancelled.');
        }

        ClosingPeriodService::guardDate((string) $return['return_date'], 'cancel sales return', 'sales');
        Database::transaction(function () use ($return, $returnId, $reason) {
            $userId = AuthService::id();
            $lines = Database::query('SELECT * FROM sales_return_items WHERE return_id = ?', [$returnId]);

            foreach ($lines as $line) {
                InventoryEngine::issue([
                    'company_id'    => (int) $return['company_id'],
                    'item_id'       => (int) $line['item_id'],
                    'warehouse_id'  => (int) $return['warehouse_id'],
                    'entry_date'    => date('Y-m-d'),
                    'document_type' => 'sales_return',
                    'document_id'   => $returnId,
                    'document_no'   => $return['return_no'] . ' (R)',
                    'qty'           => (float) $line['base_qty'],
                    'uom_id'        => $line['uom_id'] ? (int) $line['uom_id'] : null,
                    'created_by'    => $userId,
                ]);
            }

            $entries = Database::query(
                'SELECT id FROM journal_entries WHERE source_document_type = \'sales_return\' AND source_document_id = ? AND status = \'posted\' ORDER BY id',
                [$returnId]
            );
            foreach ($entries as $entry) {
                AccountingEngine::reverse((int) $entry['id'], 'Cancel ' . $return['return_no'] . ': ' . $reason, $userId);
            }

            Database::execute(
                'UPDATE sales_returns SET status = \'cancelled\', cancelled_by = ?, cancelled_at = ?, cancelled_reason = ?, updated_at = ? WHERE id = ?',
                [$userId, date('Y-m-d H:i:s'), mb_substr($reason, 0, 255), date('Y-m-d H:i:s'), $returnId]
            );
        });
    }

    public static function paginate(int $companyId, int $page = 1, int $perPage = 20): array
    {
        $total = (int) Database::value('SELECT COUNT(*) FROM sales_returns WHERE company_id = ?', [$companyId]);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::query(
            'SELECT sr.*, c.name AS customer_name
             FROM sales_returns sr
             LEFT JOIN customers c ON c.id = sr.customer_id
             WHERE sr.company_id = ?
             ORDER BY sr.id DESC
             LIMIT ? OFFSET ?',
            [$companyId, $perPage, $offset]
        );
        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage)), 'page' => $page];
    }

    public static function find(int $id): ?array
    {
        $return = Database::row('SELECT * FROM sales_returns WHERE id = ?', [$id]);
        if (!$return) {
            return null;
        }
        $return['customer'] = $return['customer_id']
            ? Database::row('SELECT * FROM customers WHERE id = ?', [(int) $return['customer_id']])
            : null;
        $return['items'] = Database::query(
            'SELECT sri.*, i.item_code, i.name AS item_name, u.code AS uom_code
             FROM sales_return_items sri
             JOIN items i ON i.id = sri.item_id
             LEFT JOIN uoms u ON u.id = sri.uom_id
             WHERE sri.return_id = ?
             ORDER BY sri.id',
            [$id]
        );
        return $return;
    }

    private static function defaultWarehouse(int $companyId): int
    {
        $id = (int) Database::value('SELECT id FROM warehouses WHERE company_id = ? AND is_default = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1', [$companyId]);
        if ($id <= 0) {
            $id = (int) Database::value('SELECT id FROM warehouses WHERE company_id = ? AND deleted_at IS NULL ORDER BY id LIMIT 1', [$companyId]);
        }
        if ($id <= 0) {
            throw new \RuntimeException('No warehouse is available.');
        }
        return $id;
    }
}
