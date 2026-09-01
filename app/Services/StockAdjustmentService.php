<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Stock adjustment service — increase / decrease with reason.
 *
 * Every adjustment:
 *   * moves stock through the InventoryEngine (stock ledger)
 *   * posts a REAL balanced accounting entry through AccountingEngine:
 *       increase → Dr Inventory / Cr Stock Adjustment
 *       decrease → Dr Stock Adjustment / Cr Inventory
 *   (account resolution: item → item group → company default)
 */
final class StockAdjustmentService
{
    /**
     * Create and post an adjustment.
     * $lines: list of ['item_id', 'warehouse_id', 'uom_id', 'quantity' (signed,
     *                  in selected uom), 'rate' (per selected uom; required for +)]
     * @return int adjustment id
     */
    public static function create(int $companyId, array $data): int
    {
        if (empty($data['lines'])) {
            throw new \RuntimeException('Add at least one item to adjust.');
        }

        $adjustmentNo = DocumentNumberService::next('stock_adjustment', $companyId, $data['branch_id'] ?? null);
        $entryDate = (string) $data['adjustment_date'];
        ClosingPeriodService::guardDate($entryDate, 'create stock adjustment', 'inventory');
        $now = date('Y-m-d H:i:s');
        $userId = AuthService::id();

        return (int) Database::transaction(function () use ($companyId, $data, $adjustmentNo, $entryDate, $now, $userId) {
            Database::execute(
                'INSERT INTO stock_adjustments (company_id, branch_id, adjustment_no, adjustment_date, reason, status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, \'posted\', ?, ?, ?)',
                [$companyId, $data['branch_id'] ?? null, $adjustmentNo, $entryDate, $data['reason'] ?? '', $userId, $now, $now]
            );
            $adjustmentId = (int) Database::lastInsertId();

            // Pre-validate all lines so we never post half a document
            $prepared = [];
            foreach ($data['lines'] as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                $warehouseId = (int) ($line['warehouse_id'] ?? 0);
                $uomId = (int) ($line['uom_id'] ?? 0);
                $factor = $uomId ? UomService::factor($itemId, $uomId) : 1.0;
                $qty = (float) ($line['quantity'] ?? 0);
                if ($itemId <= 0 || $warehouseId <= 0 || $qty == 0) {
                    throw new \RuntimeException('Each adjustment line needs an item, a warehouse and a non-zero quantity.');
                }
                $baseQty = UomService::baseQty(abs($qty), $factor);
                $prepared[] = [
                    'item_id'     => $itemId,
                    'warehouse_id'=> $warehouseId,
                    'uom_id'      => $uomId,
                    'qty'         => $qty,      // signed
                    'base_qty'    => $baseQty,
                    'rate'        => (float) ($line['rate'] ?? 0),
                ];
            }

            $stmt = Database::pdo()->prepare(
                'INSERT INTO stock_adjustment_items (adjustment_id, item_id, warehouse_id, uom_id, quantity, base_qty, rate)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            $accountingLines = [];

            foreach ($prepared as $line) {
                $itemId = $line['item_id'];
                $warehouseId = $line['warehouse_id'];
                $baseQty = $line['base_qty'];
                $isIncrease = $line['qty'] > 0;

                $inventoryAccountId = (int) AccountDefaultService::forItem($companyId, $itemId, 'inventory');
                $adjustmentAccountId = (int) AccountDefaultService::forItem($companyId, $itemId, 'stock_adjustment');
                if ($inventoryAccountId <= 0 || $adjustmentAccountId <= 0) {
                    throw new \RuntimeException('Inventory or Stock Adjustment account is not configured.');
                }

                if ($isIncrease) {
                    $rate = $line['rate'] > 0 ? $line['rate'] : InventoryEngine::averageRate($itemId, $warehouseId);
                    if ($rate <= 0) {
                        throw new \RuntimeException('Enter a rate for the increase (no existing stock value to value it at).');
                    }
                    InventoryEngine::receive([
                        'company_id'    => $companyId,
                        'item_id'       => $itemId,
                        'warehouse_id'  => $warehouseId,
                        'entry_date'    => date('Y-m-d'),
                        'document_type' => 'stock_adjustment',
                        'document_id'   => $adjustmentId,
                        'document_no'   => $adjustmentNo,
                        'qty'           => $baseQty,
                        'rate'          => $rate,
                        'uom_id'        => $line['uom_id'] ?: null,
                        'created_by'    => $userId,
                    ]);
                    $amount = round($baseQty * $rate, 2);
                    $accountingLines[] = ['account_id' => $inventoryAccountId, 'debit' => $amount, 'credit' => 0];
                    $accountingLines[] = ['account_id' => $adjustmentAccountId, 'debit' => 0, 'credit' => $amount];
                    $stmt->execute([$adjustmentId, $itemId, $warehouseId, $line['uom_id'] ?: null, $line['qty'], $baseQty, $rate]);
                } else {
                    $issue = InventoryEngine::issue([
                        'company_id'    => $companyId,
                        'item_id'       => $itemId,
                        'warehouse_id'  => $warehouseId,
                        'entry_date'    => date('Y-m-d'),
                        'document_type' => 'stock_adjustment',
                        'document_id'   => $adjustmentId,
                        'document_no'   => $adjustmentNo,
                        'qty'           => $baseQty,
                        'uom_id'        => $line['uom_id'] ?: null,
                        'created_by'    => $userId,
                    ]);
                    $rate = $issue['rate'];
                    $amount = round($issue['cogs'], 2);
                    $accountingLines[] = ['account_id' => $adjustmentAccountId, 'debit' => $amount, 'credit' => 0];
                    $accountingLines[] = ['account_id' => $inventoryAccountId, 'debit' => 0, 'credit' => $amount];
                    $stmt->execute([$adjustmentId, $itemId, $warehouseId, $line['uom_id'] ?: null, $line['qty'], $baseQty, $rate]);
                }
            }

            if ($accountingLines !== []) {
                AccountingEngine::post([
                    'company_id' => $companyId,
                    'branch_id'  => $data['branch_id'] ?? null,
                    'entry_date' => $entryDate,
                    'voucher_type' => 'stock_adjustment',
                    'entry_no'   => $adjustmentNo,
                    'source_document_type' => 'stock_adjustment',
                    'source_document_id'   => $adjustmentId,
                    'narration'  => 'Stock adjustment ' . $adjustmentNo . ($data['reason'] ? ' — ' . $data['reason'] : ''),
                    'lines'      => $accountingLines,
                ]);
            }

            return $adjustmentId;
        });
    }

    public static function paginate(int $companyId, int $page = 1, int $perPage = 20): array
    {
        $total = (int) Database::value('SELECT COUNT(*) FROM stock_adjustments WHERE company_id = ?', [$companyId]);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::query(
            'SELECT a.*, u.name AS created_name
             FROM stock_adjustments a
             LEFT JOIN users u ON u.id = a.created_by
             WHERE a.company_id = ?
             ORDER BY a.id DESC
             LIMIT ? OFFSET ?',
            [$companyId, $perPage, $offset]
        );
        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage)), 'page' => $page];
    }
}
