<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Inventory engine — the single gateway for all stock movements.
 *
 * Every receive/issue goes through here; the stock_ledger is the only
 * inventory truth (running balance per item+warehouse computed in
 * entry_date,id order). Valuation comes from cost layers (moving average /
 * weighted average / FIFO / LIFO), rebuilt deterministically when the
 * costing method changes — so reports never maintain parallel totals.
 *
 * Batch/serial/expiry are OPTIONAL, per item. When enabled they provide
 * physical lot tracking; COGS always follows the selected costing method.
 */
final class InventoryEngine
{
    /* ------------------------------------------------------------------
     * Receipts (in) — purchase, opening stock, transfer-in, adjustment +,
     * sales return
     * ------------------------------------------------------------------ */

    /**
     * Receive stock into a warehouse.
     * $qty and $rate must already be in STOCK units (callers convert via UomService).
     *
     * @param array $opts company_id, item_id, warehouse_id, entry_date,
     *               document_type, document_id|null, document_no,
     *               qty (stock units), rate (per stock unit),
     *               uom_id|null, batch (array|null), serials (array|null),
     *               created_by|null
     * @return array{balance_qty:float, value:float}
     */
    public static function receive(array $opts): array
    {
        self::guardContext($opts);
        $qty = round((float) $opts['qty'], 4);
        $rate = round((float) $opts['rate'], 4);
        if ($qty <= 0) {
            throw new \RuntimeException('Received quantity must be greater than zero.');
        }
        if ($rate < 0) {
            throw new \RuntimeException('Rate cannot be negative.');
        }

        $itemId = (int) $opts['item_id'];
        $warehouseId = (int) $opts['warehouse_id'];
        $companyId = (int) $opts['company_id'];
        $entryDate = (string) $opts['entry_date'];
        $item = Database::row('SELECT * FROM items WHERE id = ? AND deleted_at IS NULL', [$itemId]);
        if (!$item) {
            throw new \RuntimeException('Item not found.');
        }

        return Database::transaction(function () use ($opts, $item, $itemId, $warehouseId, $companyId, $entryDate, $qty, $rate) {
            // Physical tracking: batch
            $batchId = null;
            if ((int) $item['batch_enabled'] === 1) {
                $batchId = self::receiveBatch($item, $warehouseId, $qty, $rate, $opts['batch'] ?? null);
            }

            // Cost layer
            $method = SettingsService::get('inventory_cost_method', 'moving_average');
            self::layerIn($companyId, $itemId, $warehouseId, $entryDate, $qty, $rate, $method, $batchId, $opts['document_type'] ?? null, $opts['document_id'] ?? null);

            // Serial registration
            if ((int) $item['serial_enabled'] === 1) {
                self::receiveSerials($companyId, $itemId, $warehouseId, $opts['serials'] ?? [], $batchId);
            }

            $balance = self::insertLedgerRow($opts, $qty, 0.0, $rate, $method, $batchId);
            return $balance;
        });
    }

    /* ------------------------------------------------------------------
     * Issues (out) — sale, transfer-out, adjustment −, purchase return
     * ------------------------------------------------------------------ */

    /**
     * Issue stock from a warehouse.
     * $qty in stock units. Returns COGS value + effective rate.
     *
     * @return array{cogs:float, rate:float, balance_qty:float}
     */
    public static function issue(array $opts): array
    {
        self::guardContext($opts);
        $qty = round((float) $opts['qty'], 4);
        if ($qty <= 0) {
            throw new \RuntimeException('Issued quantity must be greater than zero.');
        }

        $itemId = (int) $opts['item_id'];
        $warehouseId = (int) $opts['warehouse_id'];
        $companyId = (int) $opts['company_id'];
        $item = Database::row('SELECT * FROM items WHERE id = ? AND deleted_at IS NULL', [$itemId]);
        if (!$item) {
            throw new \RuntimeException('Item not found.');
        }

        // Physical: batch availability + expiry check
        $batchId = null;
        if ((int) $item['batch_enabled'] === 1) {
            $batchId = self::issueFromBatch($item, $warehouseId, $qty, $opts['entry_date'], $opts['batch'] ?? null);
        }

        // Physical: serials
        if ((int) $item['serial_enabled'] === 1) {
            self::issueSerials($companyId, $itemId, $warehouseId, $qty, $opts['serials'] ?? []);
        }

        // Negative stock guard (only for the real stock balance)
        $available = self::availableQty($itemId, $warehouseId);
        $allowNegative = SettingsService::get('allow_negative_stock', '0') === '1';
        if (!$allowNegative && $qty > $available + 0.0001) {
            throw new \RuntimeException(sprintf(
                'Not enough stock for %s in %s. Available: %s, requested: %s.',
                $item['name'],
                Database::value('SELECT name FROM warehouses WHERE id = ?', [$warehouseId]),
                rtrim(rtrim(number_format($available, 4), '0'), '.'),
                rtrim(rtrim(number_format($qty, 4), '0'), '.')
            ));
        }

        return Database::transaction(function () use ($opts, $item, $itemId, $warehouseId, $companyId, $qty, $allowNegative, $batchId) {
            $method = SettingsService::get('inventory_cost_method', 'moving_average');
            $consumption = self::layerOut($companyId, $itemId, $warehouseId, $qty, $method, $allowNegative, $batchId);

            $rate = $consumption['qty'] > 0 ? round($consumption['cogs'] / $consumption['qty'], 4) : 0.0;
            $balance = self::insertLedgerRow($opts, 0.0, $qty, $rate, $method, $batchId);

            return [
                'cogs'        => round($consumption['cogs'], 2),
                'rate'        => $rate,
                'balance_qty' => $balance['balance_qty'],
            ];
        });
    }

    /* ------------------------------------------------------------------
     * Queries
     * ------------------------------------------------------------------ */

    public static function availableQty(int $itemId, int $warehouseId): float
    {
        return (float) Database::value(
            'SELECT COALESCE(SUM(qty_in - qty_out), 0) FROM stock_ledger WHERE item_id = ? AND warehouse_id = ?',
            [$itemId, $warehouseId]
        );
    }

    /** Current valuation (cost of remaining stock) from layers. */
    public static function valuation(int $itemId, int $warehouseId): float
    {
        return (float) Database::value(
            'SELECT COALESCE(SUM(qty_remaining * rate), 0) FROM stock_layers WHERE item_id = ? AND warehouse_id = ?',
            [$itemId, $warehouseId]
        );
    }

    /** Current average rate of remaining stock. */
    public static function averageRate(int $itemId, int $warehouseId): float
    {
        $row = Database::row(
            'SELECT COALESCE(SUM(qty_remaining),0) AS qty, COALESCE(SUM(qty_remaining * rate),0) AS value
             FROM stock_layers WHERE item_id = ? AND warehouse_id = ?',
            [$itemId, $warehouseId]
        );
        return $row && (float) $row['qty'] > 0 ? round((float) $row['value'] / (float) $row['qty'], 4) : 0.0;
    }

    /** Paginated stock ledger for one item (all warehouses or one). */
    public static function ledger(int $itemId, ?int $warehouseId = null, int $page = 1, int $perPage = 50): array
    {
        $where = 'WHERE sl.item_id = ?';
        $params = [$itemId];
        if ($warehouseId !== null) {
            $where .= ' AND sl.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $total = (int) Database::value("SELECT COUNT(*) FROM stock_ledger sl {$where}", $params);
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::query(
            "SELECT sl.*, w.name AS warehouse_name, u.code AS uom_code,
                    (SELECT SUM(sl2.qty_in - sl2.qty_out) FROM stock_ledger sl2
                     WHERE sl2.item_id = sl.item_id AND sl2.warehouse_id = sl.warehouse_id
                       AND (sl2.entry_date < sl.entry_date OR (sl2.entry_date = sl.entry_date AND sl2.id <= sl.id))
                    ) AS balance
             FROM stock_ledger sl
             LEFT JOIN warehouses w ON w.id = sl.warehouse_id
             LEFT JOIN uoms u ON u.id = sl.uom_id
             {$where}
             ORDER BY sl.entry_date DESC, sl.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage)), 'page' => $page];
    }

    /** Stock balance across items/warehouses with live valuation. */
    public static function stockBalance(int $companyId, ?int $warehouseId = null, string $search = ''): array
    {
        $where = 'WHERE i.company_id = ? AND i.deleted_at IS NULL';
        $params = [$companyId];
        if ($warehouseId !== null) {
            $where .= ' AND sl.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        if ($search !== '') {
            $where .= ' AND (i.item_code LIKE ? OR i.name LIKE ? OR i.barcode LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        return Database::query(
            "SELECT i.id, i.item_code, i.name, i.barcode, i.default_sales_rate,
                    w.id AS warehouse_id, w.name AS warehouse_name,
                    COALESCE((SELECT SUM(sl2.qty_in - sl2.qty_out) FROM stock_ledger sl2
                              WHERE sl2.item_id = i.id AND sl2.warehouse_id = w.id), 0) AS qty,
                    COALESCE((SELECT SUM(l.qty_remaining * l.rate) FROM stock_layers l
                              WHERE l.item_id = i.id AND l.warehouse_id = w.id), 0) AS value
             FROM items i
             CROSS JOIN warehouses w
             LEFT JOIN stock_ledger sl ON sl.item_id = i.id AND sl.warehouse_id = w.id
             {$where}
             GROUP BY i.id, w.id
             HAVING qty != 0 OR ? = 1
             ORDER BY i.item_code",
            array_merge($params, [$warehouseId === null ? 1 : 0])
        );
    }

    /** Items below their reorder level (low stock). */
    public static function lowStock(int $companyId): array
    {
        return Database::query(
            'SELECT i.id, i.item_code, i.name, i.min_stock, i.reorder_level, w.id AS warehouse_id, w.name AS warehouse_name,
                    COALESCE((SELECT SUM(sl.qty_in - sl.qty_out) FROM stock_ledger sl
                              WHERE sl.item_id = i.id AND sl.warehouse_id = w.id), 0) AS qty
             FROM items i
             CROSS JOIN warehouses w
             WHERE i.company_id = ? AND i.deleted_at IS NULL AND w.deleted_at IS NULL AND w.status = \'active\'
             GROUP BY i.id, w.id
             HAVING qty < i.reorder_level
             ORDER BY qty ASC',
            [$companyId]
        );
    }

    /* ------------------------------------------------------------------
     * Costing method change — controlled revaluation
     * ------------------------------------------------------------------ */

    /**
     * Rebuild all cost layers from the stock_ledger history using the
     * selected method. Deterministic; called by the authorized settings
     * change flow so historical valuation stays consistent.
     */
    public static function revalueAll(int $companyId, string $method): int
    {
        $method = in_array($method, ['moving_average', 'weighted_average', 'fifo', 'lifo'], true) ? $method : 'moving_average';

        $rows = Database::query(
            'SELECT * FROM stock_ledger WHERE company_id = ? ORDER BY entry_date ASC, id ASC',
            [$companyId]
        );

        // group movements per item+warehouse in date order
        $groups = [];
        foreach ($rows as $row) {
            $key = (int) $row['item_id'] . ':' . (int) $row['warehouse_id'];
            $groups[$key][] = $row;
        }

        Database::execute('DELETE FROM stock_layers WHERE company_id = ?', [$companyId]);

        $total = 0;
        foreach ($groups as $key => $movements) {
            [$itemId, $warehouseId] = explode(':', $key);
            foreach ($movements as $m) {
                $qtyIn = (float) $m['qty_in'];
                $qtyOut = (float) $m['qty_out'];
                if ($qtyIn > 0) {
                    self::layerIn((int) $companyId, (int) $itemId, (int) $warehouseId, $m['entry_date'], $qtyIn, (float) $m['rate'], $method, $m['batch_id'] ? (int) $m['batch_id'] : null, $m['document_type'], $m['document_id']);
                    $total++;
                } elseif ($qtyOut > 0) {
                    $remaining = $qtyOut;
                    while ($remaining > 0.0001) {
                        $consumed = self::consumeLayer((int) $itemId, (int) $warehouseId, $remaining, $method, false);
                        $remaining = round($remaining - $consumed['qty'], 4);
                        if ($consumed['qty'] <= 0) {
                            break; // prevent infinite loop if layers are empty
                        }
                    }
                    $total++;
                }
            }
        }

        SettingsService::set('inventory_cost_method', $method);
        return $total;
    }

    /**
     * Costing methods catalogue with human labels.
     */
    public static function costingMethods(): array
    {
        return [
            'moving_average'   => 'Moving Average',
            'weighted_average' => 'Weighted Average',
            'fifo'             => 'FIFO — First In, First Out',
            'lifo'             => 'LIFO — Last In, First Out',
        ];
    }

    public static function costingMethodLabel(string $method): string
    {
        return self::costingMethods()[$method] ?? $method;
    }

    /**
     * CONTROLLED costing-method change (spec §22, §60).
     *
     * Replays the full stock_ledger history of the company through the new
     * method inside ONE transaction, records the change in
     * costing_method_history and writes an audit entry. If the replay fails
     * for any reason nothing changes (rollback) — historical valuation can
     * never be left half-migrated.
     *
     * @return array{old_method:string, new_method:string, movements_replayed:int}
     */
    public static function changeCostingMethod(int $companyId, string $newMethod, ?string $note = null): array
    {
        if (!isset(self::costingMethods()[$newMethod])) {
            throw new \RuntimeException('Unknown costing method: ' . $newMethod);
        }

        $oldMethod = SettingsService::get('inventory_cost_method', 'moving_average');
        if ($oldMethod === $newMethod) {
            return ['old_method' => $oldMethod, 'new_method' => $newMethod, 'movements_replayed' => 0];
        }

        $note = $note !== null ? mb_substr($note, 0, 255) : '';

        return Database::transaction(function () use ($companyId, $newMethod, $oldMethod, $note) {
            $replayed = self::revalueAll($companyId, $newMethod);

            Database::execute(
                'INSERT INTO costing_method_history
                    (company_id, old_method, new_method, movements_replayed, note, changed_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $companyId,
                    $oldMethod,
                    $newMethod,
                    $replayed,
                    $note,
                    AuthService::id(),
                    date('Y-m-d H:i:s'),
                ]
            );

            AuditService::log(
                'costing_change',
                'inventory',
                'stock',
                null,
                sprintf(
                    'Costing method changed from %s to %s (%d movements replayed)%s',
                    self::costingMethodLabel($oldMethod),
                    self::costingMethodLabel($newMethod),
                    $replayed,
                    $note !== '' ? ' — ' . $note : ''
                )
            );

            return ['old_method' => $oldMethod, 'new_method' => $newMethod, 'movements_replayed' => $replayed];
        });
    }

    /** History of costing-method changes for a company. */
    public static function costingHistory(int $companyId): array
    {
        return Database::query(
            'SELECT h.*, u.name AS changed_name
             FROM costing_method_history h
             LEFT JOIN users u ON u.id = h.changed_by
             WHERE h.company_id = ?
             ORDER BY h.id DESC
             LIMIT 50',
            [$companyId]
        );
    }

    /**
     * Independent valuation simulation.
     *
     * Replays the stock_ledger history in pure-PHP array math through the
     * CURRENT costing method and returns the expected ending valuation per
     * "item_id:warehouse_id". Used by verifyValuation() as a genuine
     * cross-check (a different code path than the DB layer engine).
     *
     * @return array<string,float> key "item:wh" => expected current value
     */
    public static function simulateValuation(int $companyId): array
    {
        $method = SettingsService::get('inventory_cost_method', 'moving_average');
        $rows = Database::query(
            'SELECT item_id, warehouse_id, qty_in, qty_out, rate
             FROM stock_ledger WHERE company_id = ?
             ORDER BY entry_date ASC, id ASC',
            [$companyId]
        );

        $groups = [];
        foreach ($rows as $r) {
            $key = (int) $r['item_id'] . ':' . (int) $r['warehouse_id'];
            $groups[$key][] = $r;
        }

        $isAverage = in_array($method, ['moving_average', 'weighted_average'], true);
        $expected = [];

        foreach ($groups as $key => $movements) {
            $openQty = 0.0;
            $openValue = 0.0;
            $lots = []; // ['qty' => float, 'rate' => float] for fifo/lifo
            $lotValue = 0.0;

            foreach ($movements as $m) {
                $qtyIn = (float) $m['qty_in'];
                $qtyOut = (float) $m['qty_out'];
                $rate = (float) $m['rate'];

                if ($qtyIn > 0) {
                    if ($isAverage) {
                        $openValue += $qtyIn * $rate;
                        $openQty += $qtyIn;
                    } else {
                        $lots[] = ['qty' => $qtyIn, 'rate' => $rate];
                        $lotValue += $qtyIn * $rate;
                    }
                } elseif ($qtyOut > 0) {
                    if ($isAverage) {
                        $avgRate = $openQty > 0 ? $openValue / $openQty : 0.0;
                        $openValue -= $qtyOut * $avgRate;
                        $openQty -= $qtyOut;
                    } else {
                        $remaining = $qtyOut;
                        while ($remaining > 0.0001 && $lots !== []) {
                            $idx = $method === 'fifo' ? 0 : count($lots) - 1;
                            $take = min($lots[$idx]['qty'], $remaining);
                            $lotValue -= $take * $lots[$idx]['rate'];
                            $lots[$idx]['qty'] -= $take;
                            $remaining -= $take;
                            if ($lots[$idx]['qty'] <= 0.0001) {
                                array_splice($lots, $idx, 1);
                            }
                        }
                        // issues beyond available lots consume no cost (negative stock)
                    }
                }
            }

            $expected[$key] = $isAverage
                ? round($openValue, 2)
                : round($lotValue, 2);
        }

        return $expected;
    }

    /**
     * Cross-check the costing layers against the stock ledger.
     * Every item+warehouse must have:
     *   * layer qty == ledger net qty  (integrity — must always hold)
     *   * layer value == simulated value via the current costing method
     *     (independent replay — catches revaluation drift)
     * Returns problems list (empty = consistent).
     */
    public static function verifyValuation(int $companyId): array
    {
        $problems = [];
        $expected = self::simulateValuation($companyId);

        $rows = Database::query(
            'SELECT sl.item_id, sl.warehouse_id, i.item_code, i.name,
                    SUM(sl.qty_in - sl.qty_out) AS ledger_qty
             FROM stock_ledger sl
             JOIN items i ON i.id = sl.item_id
             WHERE sl.company_id = ?
             GROUP BY sl.item_id, sl.warehouse_id, i.item_code, i.name',
            [$companyId]
        );

        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $whId = (int) $row['warehouse_id'];
            $key = $itemId . ':' . $whId;

            $layerQty = (float) Database::value(
                'SELECT COALESCE(SUM(qty_remaining),0) FROM stock_layers WHERE item_id = ? AND warehouse_id = ?',
                [$itemId, $whId]
            );
            $layerValue = (float) Database::value(
                'SELECT COALESCE(SUM(qty_remaining * rate),0) FROM stock_layers WHERE item_id = ? AND warehouse_id = ?',
                [$itemId, $whId]
            );
            $ledgerQty = (float) $row['ledger_qty'];
            $simValue = (float) ($expected[$key] ?? 0);

            if (abs($layerQty - $ledgerQty) > 0.0001) {
                $problems[] = sprintf(
                    'Qty mismatch %s (%s): layers %.4f vs ledger %.4f',
                    $row['item_code'], $row['name'], $layerQty, $ledgerQty
                );
            }
            // Relative tolerance: the engine rounds rates to 4dp at each step
            // while the simulation is unrounded, so tiny drift is expected
            // (< 0.1% or 0.50 absolute — real drift is orders larger).
            $tolerance = max(0.50, abs($simValue) * 0.001);
            if (abs($layerValue - $simValue) > $tolerance) {
                $problems[] = sprintf(
                    'Value mismatch %s (%s): layers %.2f vs simulated %.2f (tolerance %.2f)',
                    $row['item_code'], $row['name'], $layerValue, $simValue, $tolerance
                );
            }
        }

        return $problems;
    }

    /**
     * Item-wise cost report (spec Phase 10):
     * per item — on-hand qty, average rate, valuation, last purchase rate,
     * last sales rate, costing method.
     */
    public static function itemWiseCost(int $companyId, string $search = ''): array
    {
        $where = 'WHERE i.company_id = ? AND i.deleted_at IS NULL';
        $params = [$companyId];
        if ($search !== '') {
            $where .= ' AND (i.item_code LIKE ? OR i.name LIKE ? OR i.barcode LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        return Database::query(
            "SELECT i.id, i.item_code, i.name, i.barcode,
                    COALESCE((SELECT SUM(l.qty_remaining) FROM stock_layers l WHERE l.item_id = i.id), 0) AS on_hand_qty,
                    COALESCE((SELECT SUM(l.qty_remaining * l.rate) FROM stock_layers l WHERE l.item_id = i.id), 0) AS valuation,
                    (SELECT COALESCE(rate,0) FROM stock_ledger WHERE item_id = i.id AND qty_in > 0 ORDER BY entry_date DESC, id DESC LIMIT 1) AS last_purchase_rate,
                    (SELECT COALESCE(rate,0) FROM stock_ledger WHERE item_id = i.id AND qty_out > 0 ORDER BY entry_date DESC, id DESC LIMIT 1) AS last_issue_rate,
                    i.default_sales_rate, i.default_purchase_rate
             FROM items i
             {$where}
             ORDER BY i.item_code",
            $params
        );
    }

    private static function guardContext(array $opts): void
    {
        foreach (['company_id', 'item_id', 'warehouse_id', 'entry_date', 'document_type', 'document_no'] as $k) {
            if (!isset($opts[$k]) || $opts[$k] === '' || $opts[$k] === null) {
                throw new \RuntimeException('Inventory movement is missing required data (' . $k . ').');
            }
        }
        if (!\app\Core\Validator::isValidDate((string) $opts['entry_date'])) {
            throw new \RuntimeException('Invalid stock entry date.');
        }
    }

    private static function insertLedgerRow(array $opts, float $qtyIn, float $qtyOut, float $rate, string $method, ?int $batchId): array
    {
        $companyId = (int) $opts['company_id'];
        $itemId = (int) $opts['item_id'];
        $warehouseId = (int) $opts['warehouse_id'];
        $entryDate = (string) $opts['entry_date'];

        $balance = (float) Database::value(
            'SELECT COALESCE(SUM(qty_in - qty_out), 0) FROM stock_ledger WHERE item_id = ? AND warehouse_id = ?',
            [$itemId, $warehouseId]
        ) + $qtyIn - $qtyOut;

        Database::execute(
            'INSERT INTO stock_ledger
                (company_id, item_id, warehouse_id, entry_date, document_type, document_id, document_no,
                 uom_id, qty_in, qty_out, balance_qty, rate, value, cost_method, batch_id, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $companyId,
                $itemId,
                $warehouseId,
                $entryDate,
                $opts['document_type'],
                $opts['document_id'] ?? null,
                $opts['document_no'],
                $opts['uom_id'] ?? null,
                $qtyIn,
                $qtyOut,
                $balance,
                $rate,
                round(($qtyIn + $qtyOut) * $rate, 2),
                $method,
                $batchId,
                $opts['created_by'] ?? AuthService::id(),
                date('Y-m-d H:i:s'),
            ]
        );

        return ['balance_qty' => $balance];
    }

    /* ---------------- layers ---------------- */

    private static function layerIn(int $companyId, int $itemId, int $warehouseId, string $date, float $qty, float $rate, string $method, ?int $batchId, ?string $docType = null, ?int $docId = null): void
    {
        if ($method === 'moving_average' || $method === 'weighted_average') {
            // merge into the open layer for this item+warehouse+batch
            $layer = Database::row(
                'SELECT * FROM stock_layers WHERE item_id = ? AND warehouse_id = ? AND batch_id <=> ? AND qty_remaining > 0 ORDER BY id DESC LIMIT 1 FOR UPDATE',
                [$itemId, $warehouseId, $batchId]
            );
            if ($layer) {
                $oldQty = (float) $layer['qty_remaining'];
                $oldRate = (float) $layer['rate'];
                $newQty = $oldQty + $qty;
                $newRate = $newQty > 0 ? round(($oldQty * $oldRate + $qty * $rate) / $newQty, 4) : $rate;
                Database::execute(
                    'UPDATE stock_layers SET qty_remaining = ?, rate = ? WHERE id = ?',
                    [$newQty, $newRate, (int) $layer['id']]
                );
            } else {
                Database::execute(
                    'INSERT INTO stock_layers (company_id, item_id, warehouse_id, layer_date, qty_remaining, rate, source_document_type, source_document_id, batch_id, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$companyId, $itemId, $warehouseId, $date, $qty, $rate, $docType, $docId, $batchId, date('Y-m-d H:i:s')]
                );
            }
        } else {
            // FIFO / LIFO: append a distinct layer per receipt
            Database::execute(
                'INSERT INTO stock_layers (company_id, item_id, warehouse_id, layer_date, qty_remaining, rate, source_document_type, source_document_id, batch_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$companyId, $itemId, $warehouseId, $date, $qty, $rate, $docType, $docId, $batchId, date('Y-m-d H:i:s')]
            );
        }
    }

    private static function layerOut(int $companyId, int $itemId, int $warehouseId, float $qty, string $method, bool $allowNegative, ?int $batchId): array
    {
        $consumed = self::consumeLayer($itemId, $warehouseId, $qty, $method, $allowNegative);

        if ($consumed['short'] > 0.0001) {
            // negative stock: consume the shortfall at the item's average rate
            $rate = self::averageRate($itemId, $warehouseId) ?: 0.0;
            $cogs = $consumed['cogs'] + $consumed['short'] * $rate;
            $consumed['qty'] += $consumed['short'];
            $consumed['cogs'] = $cogs;
            if ($consumed['short'] > 0 && !$allowNegative) {
                throw new \RuntimeException('Insufficient stock layers for issue.');
            }
        }

        return $consumed;
    }

    /**
     * Consume layers oldest-first (FIFO) or newest-first (LIFO).
     * Returns ['qty' => consumed, 'cogs' => cost, 'short' => unmet qty].
     */
    private static function consumeLayer(int $itemId, int $warehouseId, float $qty, string $method, bool $allowNegative): array
    {
        $order = $method === 'lifo' ? 'DESC' : 'ASC';
        $layers = Database::query(
            "SELECT * FROM stock_layers
             WHERE item_id = ? AND warehouse_id = ? AND qty_remaining > 0.0001
             ORDER BY layer_date {$order}, id {$order}",
            [$itemId, $warehouseId]
        );

        $remaining = $qty;
        $cogs = 0.0;
        foreach ($layers as $layer) {
            if ($remaining <= 0.0001) {
                break;
            }
            $layerQty = (float) $layer['qty_remaining'];
            $take = min($layerQty, $remaining);
            $cogs += $take * (float) $layer['rate'];
            $remaining = round($remaining - $take, 4);
            Database::execute(
                'UPDATE stock_layers SET qty_remaining = qty_remaining - ? WHERE id = ?',
                [$take, (int) $layer['id']]
            );
            if ($layerQty - $take <= 0.0001) {
                Database::execute('DELETE FROM stock_layers WHERE id = ? AND qty_remaining <= 0.0001', [(int) $layer['id']]);
            }
        }

        return [
            'qty'  => round($qty - $remaining, 4),
            'cogs' => $cogs,
            'short'=> $remaining,
        ];
    }

    /* ---------------- batch & serial internals ---------------- */

    private static function receiveBatch(array $item, int $warehouseId, float $qty, float $rate, ?array $batch): ?int
    {
        if (empty($batch['batch_no'])) {
            throw new \RuntimeException('This item requires a batch number.');
        }
        $companyId = (int) $item['company_id'];
        $exists = Database::row(
            'SELECT id FROM item_batches WHERE item_id = ? AND warehouse_id = ? AND batch_no = ? AND deleted_at IS NULL',
            [(int) $item['id'], $warehouseId, $batch['batch_no']]
        );
        if ($exists) {
            Database::execute(
                'UPDATE item_batches SET qty = qty + ?, rate = ?, mfg_date = COALESCE(?, mfg_date), expiry_date = COALESCE(?, expiry_date), created_at = ? WHERE id = ?',
                [$qty, $rate, $batch['mfg_date'] ?? null, $batch['expiry_date'] ?? null, date('Y-m-d H:i:s'), (int) $exists['id']]
            );
            return (int) $exists['id'];
        }
        Database::execute(
            'INSERT INTO item_batches (company_id, item_id, warehouse_id, batch_no, mfg_date, expiry_date, qty, rate, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$companyId, (int) $item['id'], $warehouseId, $batch['batch_no'], $batch['mfg_date'] ?? null, $batch['expiry_date'] ?? null, $qty, $rate, date('Y-m-d H:i:s')]
        );
        return (int) Database::lastInsertId();
    }

    private static function issueFromBatch(array $item, int $warehouseId, float $qty, string $entryDate, ?array $batch): ?int
    {
        $itemId = (int) $item['id'];

        if (!empty($batch['batch_id'])) {
            $row = Database::row(
                'SELECT * FROM item_batches WHERE id = ? AND item_id = ? AND warehouse_id = ? AND deleted_at IS NULL',
                [(int) $batch['batch_id'], $itemId, $warehouseId]
            );
            if (!$row) {
                throw new \RuntimeException('Selected batch not found in this warehouse.');
            }
            if ($row['expiry_date'] && $row['expiry_date'] < $entryDate) {
                throw new \RuntimeException('Cannot issue from batch ' . $row['batch_no'] . ' — it has expired (' . $row['expiry_date'] . ').');
            }
            if ((float) $row['qty'] + 0.0001 < $qty) {
                throw new \RuntimeException('Batch ' . $row['batch_no'] . ' has only ' . rtrim(rtrim(number_format((float) $row['qty'], 4), '0'), '.') . ' available.');
            }
            Database::execute('UPDATE item_batches SET qty = qty - ? WHERE id = ?', [$qty, (int) $row['id']]);
            return (int) $row['id'];
        }

        // auto-select: earliest expiry first (expired batches excluded)
        $rows = Database::query(
            'SELECT * FROM item_batches
             WHERE item_id = ? AND warehouse_id = ? AND deleted_at IS NULL AND qty > 0.0001
               AND (expiry_date IS NULL OR expiry_date >= ?)
             ORDER BY expiry_date IS NULL ASC, expiry_date ASC, id ASC',
            [$itemId, $warehouseId, $entryDate]
        );
        $remaining = $qty;
        $selected = null;
        foreach ($rows as $row) {
            if ($remaining <= 0.0001) {
                break;
            }
            $take = min((float) $row['qty'], $remaining);
            Database::execute('UPDATE item_batches SET qty = qty - ? WHERE id = ?', [$take, (int) $row['id']]);
            $remaining = round($remaining - $take, 4);
            $selected = (int) $row['id'];
        }
        if ($remaining > 0.0001) {
            throw new \RuntimeException('Insufficient unexpired batch stock for this item.');
        }
        return $selected;
    }

    private static function receiveSerials(int $companyId, int $itemId, int $warehouseId, array $serials, ?int $batchId): void
    {
        $serials = array_values(array_filter(array_map('trim', $serials)));
        if ($serials === []) {
            throw new \RuntimeException('This item requires serial numbers.');
        }
        $stmt = Database::pdo()->prepare(
            'INSERT INTO item_serials (company_id, item_id, warehouse_id, serial_no, status, batch_id, created_at)
             VALUES (?, ?, ?, ?, \'in_stock\', ?, ?)'
        );
        foreach ($serials as $sn) {
            try {
                $stmt->execute([$companyId, $itemId, $warehouseId, $sn, $batchId, date('Y-m-d H:i:s')]);
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'uq_serial_no')) {
                    throw new \RuntimeException('Serial number ' . $sn . ' is already registered.');
                }
                throw $e;
            }
        }
    }

    private static function issueSerials(int $companyId, int $itemId, int $warehouseId, float $qty, array $serials): void
    {
        if ((int) $qty != $qty) {
            throw new \RuntimeException('Serial-tracked items must be issued in whole units.');
        }
        $count = (int) $qty;
        $serials = array_values(array_filter(array_map('trim', $serials)));

        if (count($serials) !== $count) {
            throw new \RuntimeException('Please provide exactly ' . $count . ' serial number(s) for this item.');
        }

        foreach ($serials as $sn) {
            $row = Database::row(
                'SELECT id, status FROM item_serials WHERE item_id = ? AND serial_no = ?',
                [$itemId, $sn]
            );
            if (!$row) {
                throw new \RuntimeException('Serial number ' . $sn . ' is not registered.');
            }
            if ($row['status'] !== 'in_stock') {
                throw new \RuntimeException('Serial number ' . $sn . ' is not in stock (status: ' . $row['status'] . ').');
            }
            Database::execute(
                'UPDATE item_serials SET status = \'issued\', warehouse_id = ? WHERE id = ?',
                [$warehouseId, (int) $row['id']]
            );
        }
    }
}
