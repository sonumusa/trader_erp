<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Purchase return service.
 *
 * A return reverses the purchase effect:
 *   stock OUT (inventory engine issue — returns goods to the supplier)
 *   accounting reversal:
 *        Accounts Payable   Dr  (party-tagged, supplier)
 *              Inventory          Cr  (net)
 *              Tax Receivable     Cr  (input tax reversed)
 * The original invoice may be referenced (optional; returns without a
 * reference are allowed).
 */
final class PurchaseReturnService
{
    public static function create(int $companyId, array $data): int
    {
        $supplierId = (int) ($data['supplier_id'] ?? 0);
        $supplier = Database::row('SELECT * FROM suppliers WHERE id = ? AND company_id = ? AND deleted_at IS NULL', [$supplierId, $companyId]);
        if (!$supplier) {
            throw new \RuntimeException('Select a valid supplier.');
        }

        $referenceInvoiceId = !empty($data['reference_invoice_id']) ? (int) $data['reference_invoice_id'] : null;
        if ($referenceInvoiceId !== null) {
            $ref = Database::row(
                'SELECT * FROM purchase_invoices WHERE id = ? AND company_id = ? AND status = \'posted\'',
                [$referenceInvoiceId, $companyId]
            );
            if (!$ref) {
                throw new \RuntimeException('The referenced purchase invoice is not valid.');
            }
            if ((int) $ref['supplier_id'] !== $supplierId) {
                throw new \RuntimeException('The referenced invoice belongs to a different supplier.');
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

        $returnNo = DocumentNumberService::next('purchase_return', $companyId, $data['branch_id'] ?? null);
        $entryDate = (string) $data['return_date'];
        ClosingPeriodService::guardDate($entryDate, 'create purchase return', 'purchase');
        $userId = AuthService::id();
        $now = date('Y-m-d H:i:s');

        return (int) Database::transaction(function () use ($companyId, $data, $supplier, $lines, $totals, $returnNo, $entryDate, $userId, $now, $referenceInvoiceId) {
            Database::execute(
                'INSERT INTO purchase_returns
                    (company_id, branch_id, return_no, return_date, supplier_id, reference_invoice_id,
                     warehouse_id, subtotal, discount_total, tax_total, total, narration,
                     status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'posted\', ?, ?, ?)',
                [
                    $companyId,
                    $data['branch_id'] ?? null,
                    $returnNo,
                    $entryDate,
                    (int) $supplier['id'],
                    $referenceInvoiceId,
                    (int) ($data['warehouse_id'] ?? self::defaultWarehouse($companyId)),
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
                'INSERT INTO purchase_return_items
                    (return_id, item_id, uom_id, quantity, base_qty, rate, discount, tax_id, tax_rate, tax_amount, amount)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );

            $accountingLines = [];

            foreach ($lines as $line) {
                $itemId = (int) $line['item_id'];

                // Stock out (return to supplier) — COGS-driven issue
                $issue = InventoryEngine::issue([
                    'company_id'    => $companyId,
                    'item_id'       => $itemId,
                    'warehouse_id'  => (int) ($data['warehouse_id'] ?? self::defaultWarehouse($companyId)),
                    'entry_date'    => $entryDate,
                    'document_type' => 'purchase_return',
                    'document_id'   => $returnId,
                    'document_no'   => $returnNo,
                    'qty'           => $line['base_qty'],
                    'uom_id'        => $line['uom_id'] ?: null,
                    'batch'         => $line['batch'] ?? null,
                    'serials'       => $line['serials'] ?? [],
                    'created_by'    => $userId,
                ]);

                // Accounting: AP Dr, Inventory Cr (net), Tax Receivable Cr
                $inventoryAccountId = (int) AccountDefaultService::forItem($companyId, $itemId, 'inventory');
                $net = round($line['amount'] - $line['tax_amount'], 2);
                $accountingLines[] = ['account_id' => $inventoryAccountId, 'debit' => 0, 'credit' => $net, 'narration' => $line['item']['name']];
                if ($line['tax_amount'] > 0) {
                    $taxRecvId = (int) AccountDefaultService::get($companyId, 'tax_receivable');
                    if ($taxRecvId > 0) {
                        $accountingLines[] = ['account_id' => $taxRecvId, 'debit' => 0, 'credit' => $line['tax_amount'], 'narration' => 'Input tax reversal'];
                    }
                }

                $stmtItem->execute([
                    $returnId, $itemId, $line['uom_id'] ?: null, $line['quantity'], $line['base_qty'],
                    $line['rate'], $line['discount'], $line['tax_id'], $line['tax_rate'], $line['tax_amount'], $line['amount'],
                ]);
            }

            $apId = (int) AccountDefaultService::get($companyId, 'payable');
            $accountingLines[] = [
                'account_id' => $apId,
                'debit'      => $totals['total'],
                'credit'     => 0,
                'party_type' => 'supplier',
                'party_id'   => (int) $supplier['id'],
                'narration'  => 'Purchase return ' . $returnNo,
            ];

            AccountingEngine::post([
                'company_id' => $companyId,
                'branch_id'  => $data['branch_id'] ?? null,
                'entry_date' => $entryDate,
                'voucher_type' => 'purchase_return',
                'entry_no'   => $returnNo,
                'source_document_type' => 'purchase_return',
                'source_document_id'   => $returnId,
                'narration'  => 'Return to ' . $supplier['name'] . ' — ' . $returnNo,
                'lines'      => $accountingLines,
            ]);

            return $returnId;
        });
    }

    public static function cancel(int $returnId, string $reason): void
    {
        $return = Database::row('SELECT * FROM purchase_returns WHERE id = ?', [$returnId]);
        if (!$return || $return['status'] === 'cancelled') {
            throw new \RuntimeException('Return not found or already cancelled.');
        }

        ClosingPeriodService::guardDate((string) $return['return_date'], 'cancel purchase return', 'purchase');
        Database::transaction(function () use ($return, $returnId, $reason) {
            $userId = AuthService::id();
            $lines = Database::query('SELECT * FROM purchase_return_items WHERE return_id = ?', [$returnId]);

            foreach ($lines as $line) {
                InventoryEngine::receive([
                    'company_id'    => (int) $return['company_id'],
                    'item_id'       => (int) $line['item_id'],
                    'warehouse_id'  => (int) ($return['warehouse_id'] ?? 0),
                    'entry_date'    => date('Y-m-d'),
                    'document_type' => 'purchase_return',
                    'document_id'   => $returnId,
                    'document_no'   => $return['return_no'] . ' (R)',
                    'qty'           => (float) $line['base_qty'],
                    'rate'          => (float) $line['rate'],
                    'uom_id'        => $line['uom_id'] ? (int) $line['uom_id'] : null,
                    'created_by'    => $userId,
                ]);
            }

            $entries = Database::query(
                'SELECT id FROM journal_entries WHERE source_document_type = \'purchase_return\' AND source_document_id = ? AND status = \'posted\' ORDER BY id',
                [$returnId]
            );
            foreach ($entries as $entry) {
                AccountingEngine::reverse((int) $entry['id'], 'Cancel ' . $return['return_no'] . ': ' . $reason, $userId);
            }

            Database::execute(
                'UPDATE purchase_returns SET status = \'cancelled\', cancelled_by = ?, cancelled_at = ?, cancelled_reason = ?, updated_at = ? WHERE id = ?',
                [$userId, date('Y-m-d H:i:s'), mb_substr($reason, 0, 255), date('Y-m-d H:i:s'), $returnId]
            );
        });
    }

    public static function paginate(int $companyId, int $page = 1, int $perPage = 20): array
    {
        $total = (int) Database::value('SELECT COUNT(*) FROM purchase_returns WHERE company_id = ?', [$companyId]);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::query(
            'SELECT pr.*, s.name AS supplier_name
             FROM purchase_returns pr
             JOIN suppliers s ON s.id = pr.supplier_id
             WHERE pr.company_id = ?
             ORDER BY pr.id DESC
             LIMIT ? OFFSET ?',
            [$companyId, $perPage, $offset]
        );
        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage)), 'page' => $page];
    }

    private static function defaultWarehouse(int $companyId): int
    {
        $id = (int) Database::value('SELECT id FROM warehouses WHERE company_id = ? AND is_default = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1', [$companyId]);
        if ($id <= 0) {
            $id = (int) Database::value('SELECT id FROM warehouses WHERE company_id = ? AND deleted_at IS NULL ORDER BY id LIMIT 1', [$companyId]);
        }
        return $id > 0 ? $id : throw new \RuntimeException('No warehouse is available.');
    }
}
