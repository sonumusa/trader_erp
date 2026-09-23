<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Shared purchase logic — line normalization, tax computation, totals.
 * Used by quotations, orders, invoices and returns so every document type
 * calculates identically (spec §60: deterministic, traceable rules).
 */
final class PurchaseService
{
    /**
     * Normalize + calculate one line.
     * Returns the line with base_qty, rate_stock, gross, discount, net,
     * tax_rate, tax_amount, amount (money rounded to 2).
     *
     * @param array $line item_id, uom_id, quantity, rate (per selected uom), discount
     * @param array|null $item preloaded item row (optional, avoids re-query)
     */
    public static function calculateLine(array $line, ?array $item = null): array
    {
        $itemId = (int) ($line['item_id'] ?? 0);
        $item = $item ?? Database::row('SELECT * FROM items WHERE id = ? AND deleted_at IS NULL', [$itemId]);
        if (!$item) {
            throw new \RuntimeException('A line references an item that does not exist.');
        }

        $uomId = (int) ($line['uom_id'] ?? 0);
        $factor = $uomId ? UomService::factor($itemId, $uomId) : 1.0;
        $qty = (float) ($line['quantity'] ?? 0);
        $rate = (float) ($line['rate'] ?? 0);
        if ($qty <= 0) {
            throw new \RuntimeException('Each purchase line needs a quantity greater than zero.');
        }
        if ($rate < 0) {
            throw new \RuntimeException('Rate cannot be negative.');
        }

        $baseQty = UomService::baseQty($qty, $factor);
        $rateStock = UomService::ratePerStock($rate, $factor);
        $gross = round($baseQty * $rateStock, 2);
        $discount = min(round((float) ($line['discount'] ?? 0), 2), $gross);
        $net = round($gross - $discount, 2);

        // Tax from the item's tax category
        $taxRate = 0.0;
        $taxAmount = 0.0;
        if ((int) ($item['tax_id'] ?? 0) > 0) {
            $taxDate = (string) ($line['transaction_date'] ?? date('Y-m-d'));
            $taxRate = (float) Database::value('SELECT rate FROM taxes WHERE id = ? AND is_active = 1 AND deleted_at IS NULL AND (effective_from IS NULL OR effective_from <= ?) AND (effective_to IS NULL OR effective_to >= ?)', [(int) $item['tax_id'], $taxDate, $taxDate]);
            $taxAmount = round($net * $taxRate / 100, 2);
        }
        $amount = round($net + $taxAmount, 2);

        return [
            'item_id'     => $itemId,
            'uom_id'      => $uomId,
            'quantity'    => $qty,
            'base_qty'    => $baseQty,
            'rate'        => $rate,          // per selected uom (as entered)
            'rate_stock'  => $rateStock,     // per stock uom
            'discount'    => $discount,
            'tax_id'      => (int) ($item['tax_id'] ?? 0) ?: null,
            'tax_rate'    => $taxRate,
            'tax_amount'  => $taxAmount,
            'amount'      => $amount,
            'item'        => $item,
        ];
    }

    /** Sum document totals from calculated lines. */
    public static function totals(array $lines): array
    {
        $subtotal = array_sum(array_column($lines, 'amount')) - array_sum(array_column($lines, 'tax_amount'));
        return [
            'subtotal'      => round($subtotal, 2),
            'discount_total' => round(array_sum(array_column($lines, 'discount')), 2),
            'tax_total'     => round(array_sum(array_column($lines, 'tax_amount')), 2),
            'total'         => round(array_sum(array_column($lines, 'amount')), 2),
        ];
    }

    /** Parse form lines (items[item_id][] etc.) — same shape as inventory forms. */
    public static function linesFromInput(array $raw): array
    {
        $items = $raw['item_id'] ?? [];
        if (!is_array($items)) {
            return [];
        }
        $lines = [];
        foreach ($items as $i => $idRaw) {
            $itemId = (int) $idRaw;
            if ($itemId <= 0) {
                continue;
            }
            $lines[] = [
                'item_id'  => $itemId,
                'uom_id'   => (int) ($raw['uom_id'][$i] ?? 0),
                'quantity' => (float) ($raw['quantity'][$i] ?? 0),
                'rate'     => (float) ($raw['rate'][$i] ?? 0),
                'discount' => (float) ($raw['discount'][$i] ?? 0),
                'batch'    => [
                    'batch_no'    => (string) ($raw['batch_no'][$i] ?? ''),
                    'mfg_date'    => (string) ($raw['mfg_date'][$i] ?? '') ?: null,
                    'expiry_date' => (string) ($raw['expiry_date'][$i] ?? '') ?: null,
                ],
                'serials'  => array_filter(array_map('trim', explode(',', (string) ($raw['serials'][$i] ?? '')))),
            ];
        }
        return $lines;
    }

    /** Items of a prior document (order/quotation) as prefill lines. */
    public static function linesFromDocument(string $table, int $documentId): array
    {
        $columnMap = [
            'purchase_quotation_items' => 'quotation_id',
            'purchase_order_items'     => 'order_id',
            'purchase_invoice_items'   => 'invoice_id',
            'purchase_return_items'    => 'return_id',
            'sales_quotation_items'    => 'quotation_id',
            'sales_order_items'        => 'order_id',
            'sales_invoice_items'      => 'invoice_id',
        ];
        $col = $columnMap[$table] ?? null;
        if ($col === null) {
            return [];
        }
        return Database::query(
            "SELECT * FROM {$table} WHERE {$col} = ? ORDER BY id",
            [$documentId]
        );
    }
}
