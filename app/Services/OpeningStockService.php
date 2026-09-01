<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Opening stock service.
 *
 * Records opening stock through the InventoryEngine (document type
 * 'opening_stock', number OPENING) AND posts the total inventory value to
 * the accounting ledger (Dr Inventory / Cr Opening Balance Equity) so stock
 * and books stay consistent from day one. Runs atomically.
 */
final class OpeningStockService
{
    /**
     * @param array $lines list of ['item_id', 'warehouse_id', 'uom_id', 'quantity', 'rate']
     * @return array{posted:int, value:float}
     */
    public static function post(int $companyId, array $lines): array
    {
        if ($lines === []) {
            throw new \RuntimeException('Add at least one opening stock line.');
        }

        $inventoryAccountId = (int) AccountDefaultService::get($companyId, 'inventory');
        $openingAccountId = (int) AccountDefaultService::get($companyId, 'opening_balance');
        if ($inventoryAccountId <= 0 || $openingAccountId <= 0) {
            throw new \RuntimeException('Inventory and Opening Balance accounts must be configured first.');
        }

        return Database::transaction(function () use ($companyId, $lines, $inventoryAccountId, $openingAccountId) {
            $userId = AuthService::id();
            $totalValue = 0.0;
            $posted = 0;

            foreach ($lines as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                $warehouseId = (int) ($line['warehouse_id'] ?? 0);
                $uomId = (int) ($line['uom_id'] ?? 0);
                $qty = (float) ($line['quantity'] ?? 0);
                $rate = (float) ($line['rate'] ?? 0);

                if ($itemId <= 0 || $warehouseId <= 0 || $qty <= 0 || $rate <= 0) {
                    throw new \RuntimeException('Each opening stock line needs an item, warehouse, quantity and rate.');
                }

                $factor = $uomId ? UomService::factor($itemId, $uomId) : 1.0;
                $baseQty = UomService::baseQty($qty, $factor);
                $ratePerStock = UomService::ratePerStock($rate, $factor);

                InventoryEngine::receive([
                    'company_id'    => $companyId,
                    'item_id'       => $itemId,
                    'warehouse_id'  => $warehouseId,
                    'entry_date'    => date('Y-m-d'),
                    'document_type' => 'opening_stock',
                    'document_id'   => null,
                    'document_no'   => 'OPENING',
                    'qty'           => $baseQty,
                    'rate'          => $ratePerStock,
                    'uom_id'        => $uomId,
                    'batch'         => $line['batch'] ?? null,
                    'serials'       => $line['serials'] ?? [],
                    'created_by'    => $userId,
                ]);

                $totalValue += round($baseQty * $ratePerStock, 2);
                $posted++;
            }

            if ($totalValue > 0) {
                AccountingEngine::post([
                    'company_id' => $companyId,
                    'entry_date' => date('Y-m-d'),
                    'voucher_type' => 'opening_stock',
                    'entry_no'   => 'OPENING',
                    'source_document_type' => 'opening_stock',
                    'source_document_id'   => null,
                    'narration'  => 'Opening stock value',
                    'lines'      => [
                        ['account_id' => $inventoryAccountId, 'debit' => $totalValue, 'credit' => 0],
                        ['account_id' => $openingAccountId, 'debit' => 0, 'credit' => $totalValue],
                    ],
                ]);
            }

            return ['posted' => $posted, 'value' => round($totalValue, 2)];
        });
    }
}
