<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Item-specific UOM conversion service.
 *
 * UOMs belong to the ITEM (spec §21): the dropdown in every transaction
 * shows ONLY the UOMs configured for the selected item, and conversion is:
 *
 *   base_qty          = entered_qty × factor        (stock units)
 *   rate_per_stock    = entered_rate ÷ factor       (price per stock unit)
 *   amount            = base_qty × rate_per_stock   (invariant under conversion)
 */
final class UomService
{
    /** All UOMs configured for an item (stock UOM first). */
    public static function itemUoms(int $itemId): array
    {
        return Database::query(
            'SELECT iu.*, u.name AS uom_name, u.code AS uom_code
             FROM item_uoms iu
             JOIN uoms u ON u.id = iu.uom_id AND u.deleted_at IS NULL AND u.is_active = 1
             WHERE iu.item_id = ?
             ORDER BY iu.is_stock_uom DESC, iu.id',
            [$itemId]
        );
    }

    public static function stockUomId(int $itemId): ?int
    {
        $id = Database::value(
            'SELECT uom_id FROM item_uoms WHERE item_id = ? AND is_stock_uom = 1 LIMIT 1',
            [$itemId]
        );
        return $id !== null ? (int) $id : null;
    }

    /** Conversion factor for a uom on an item (1 for the stock uom). */
    public static function factor(int $itemId, int $uomId): float
    {
        $f = Database::value(
            'SELECT conversion_factor FROM item_uoms WHERE item_id = ? AND uom_id = ? LIMIT 1',
            [$itemId, $uomId]
        );
        if ($f === null) {
            throw new \RuntimeException('This UOM is not configured for the selected item.');
        }
        return (float) $f;
    }

    public static function baseQty(float $qty, float $factor): float
    {
        return round($qty * $factor, 4);
    }

    public static function ratePerStock(float $rate, float $factor): float
    {
        return round($rate / $factor, 4);
    }

    public static function displayQty(float $stockQty, float $factor): float
    {
        return round($stockQty / $factor, 4);
    }

    /** Rebuild the item's UOM rows (stock uom + alternatives). */
    public static function syncItemUoms(int $itemId, int $stockUomId, array $alternatives): void
    {
        // alternatives: list of ['uom_id' => int, 'factor' => float]
        Database::execute('DELETE FROM item_uoms WHERE item_id = ?', [$itemId]);

        $now = date('Y-m-d H:i:s');
        Database::execute(
            'INSERT INTO item_uoms (item_id, uom_id, is_stock_uom, conversion_factor, created_at)
             VALUES (?, ?, 1, 1.0000, ?)',
            [$itemId, $stockUomId, $now]
        );

        foreach ($alternatives as $alt) {
            $uomId = (int) ($alt['uom_id'] ?? 0);
            $factor = (float) ($alt['factor'] ?? 1);
            if ($uomId <= 0 || $factor <= 0 || $uomId === (int) $stockUomId) {
                continue;
            }
            Database::execute(
                'INSERT INTO item_uoms (item_id, uom_id, is_stock_uom, conversion_factor, created_at)
                 VALUES (?, ?, 0, ?, ?)',
                [$itemId, $uomId, $factor, $now]
            );
        }
    }
}
