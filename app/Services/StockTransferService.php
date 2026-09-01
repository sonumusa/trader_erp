<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Stock transfer service — warehouse A → warehouse B.
 *
 * Issues from the source and receives into the destination with the SAME
 * document number; both movements are recorded in the stock ledger. No
 * sales/purchase accounting entries are created (internal movement) unless
 * the optional setting transfer_posts_accounting is enabled (default off).
 */
final class StockTransferService
{
    /**
     * Create and post a transfer.
     * $lines: list of ['item_id', 'uom_id', 'quantity' (in selected uom),
     *                  'rate' (per selected uom, optional)]
     * @return int transfer id
     */
    public static function create(int $companyId, array $data): int
    {
        $fromWh = (int) $data['from_warehouse_id'];
        $toWh = (int) $data['to_warehouse_id'];
        if ($fromWh === $toWh) {
            throw new \RuntimeException('Source and destination warehouses must be different.');
        }
        if (empty($data['lines'])) {
            throw new \RuntimeException('Add at least one item to transfer.');
        }

        $transferNo = DocumentNumberService::next('stock_transfer', $companyId, $data['branch_id'] ?? null);
        $entryDate = (string) $data['transfer_date'];
        ClosingPeriodService::guardDate($entryDate, 'create stock transfer', 'inventory');
        $now = date('Y-m-d H:i:s');
        $userId = AuthService::id();

        return (int) Database::transaction(function () use ($companyId, $data, $fromWh, $toWh, $transferNo, $entryDate, $now, $userId) {
            Database::execute(
                'INSERT INTO stock_transfers (company_id, branch_id, transfer_no, transfer_date, from_warehouse_id, to_warehouse_id, narration, status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, \'posted\', ?, ?, ?)',
                [$companyId, $data['branch_id'] ?? null, $transferNo, $entryDate, $fromWh, $toWh, $data['narration'] ?? '', $userId, $now, $now]
            );
            $transferId = (int) Database::lastInsertId();

            $stmt = Database::pdo()->prepare(
                'INSERT INTO stock_transfer_items (transfer_id, item_id, uom_id, quantity, base_qty, rate) VALUES (?, ?, ?, ?, ?, ?)'
            );

            foreach ($data['lines'] as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                $uomId = (int) ($line['uom_id'] ?? 0);
                $factor = $uomId ? UomService::factor($itemId, $uomId) : 1.0;
                $qty = (float) ($line['quantity'] ?? 0);
                if ($itemId <= 0 || $qty <= 0) {
                    throw new \RuntimeException('Each transfer line needs an item and a positive quantity.');
                }
                $baseQty = UomService::baseQty($qty, $factor);

                // Issue from source at its current valuation rate
                $issue = InventoryEngine::issue([
                    'company_id'    => $companyId,
                    'item_id'       => $itemId,
                    'warehouse_id'  => $fromWh,
                    'entry_date'    => $entryDate,
                    'document_type' => 'stock_transfer',
                    'document_id'   => $transferId,
                    'document_no'   => $transferNo,
                    'qty'           => $baseQty,
                    'uom_id'        => $uomId,
                    'batch'         => $line['batch'] ?? null,
                    'serials'       => $line['serials'] ?? [],
                    'created_by'    => $userId,
                ]);
                $rate = $issue['rate'];

                // Receive into destination at the same rate
                InventoryEngine::receive([
                    'company_id'    => $companyId,
                    'item_id'       => $itemId,
                    'warehouse_id'  => $toWh,
                    'entry_date'    => $entryDate,
                    'document_type' => 'stock_transfer',
                    'document_id'   => $transferId,
                    'document_no'   => $transferNo,
                    'qty'           => $baseQty,
                    'rate'          => $rate,
                    'uom_id'        => $uomId,
                    'batch'         => $line['batch'] ?? null,
                    'serials'       => $line['serials'] ?? [],
                    'created_by'    => $userId,
                ]);

                $stmt->execute([$transferId, $itemId, $uomId, $qty, $baseQty, $rate]);
            }

            return $transferId;
        });
    }

    /** Reversal: move the same quantities back and mark the transfer cancelled. */
    public static function reverse(int $transferId, string $reason): void
    {
        $transfer = Database::row('SELECT * FROM stock_transfers WHERE id = ?', [$transferId]);
        if (!$transfer || $transfer['status'] === 'cancelled') {
            throw new \RuntimeException('Transfer not found or already cancelled.');
        }

        Database::transaction(function () use ($transfer, $transferId, $reason) {
            $lines = Database::query('SELECT * FROM stock_transfer_items WHERE transfer_id = ?', [$transferId]);
            $userId = AuthService::id();

            foreach ($lines as $line) {
                // issue from destination back to source
                InventoryEngine::issue([
                    'company_id'    => (int) $transfer['company_id'],
                    'item_id'       => (int) $line['item_id'],
                    'warehouse_id'  => (int) $transfer['to_warehouse_id'],
                    'entry_date'    => date('Y-m-d'),
                    'document_type' => 'stock_transfer',
                    'document_id'   => $transferId,
                    'document_no'   => $transfer['transfer_no'] . ' (R)',
                    'qty'           => (float) $line['base_qty'],
                    'uom_id'        => $line['uom_id'] ? (int) $line['uom_id'] : null,
                    'created_by'    => $userId,
                ]);
                InventoryEngine::receive([
                    'company_id'    => (int) $transfer['company_id'],
                    'item_id'       => (int) $line['item_id'],
                    'warehouse_id'  => (int) $transfer['from_warehouse_id'],
                    'entry_date'    => date('Y-m-d'),
                    'document_type' => 'stock_transfer',
                    'document_id'   => $transferId,
                    'document_no'   => $transfer['transfer_no'] . ' (R)',
                    'qty'           => (float) $line['base_qty'],
                    'rate'          => (float) $line['rate'],
                    'uom_id'        => $line['uom_id'] ? (int) $line['uom_id'] : null,
                    'created_by'    => $userId,
                ]);
            }

            Database::execute(
                'UPDATE stock_transfers SET status = \'cancelled\', cancelled_by = ?, cancelled_at = ?, cancelled_reason = ?, updated_at = ? WHERE id = ?',
                [$userId, date('Y-m-d H:i:s'), mb_substr($reason, 0, 255), date('Y-m-d H:i:s'), $transferId]
            );
        });
    }

    public static function paginate(int $companyId, int $page = 1, int $perPage = 20): array
    {
        $total = (int) Database::value('SELECT COUNT(*) FROM stock_transfers WHERE company_id = ?', [$companyId]);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::query(
            'SELECT t.*, wf.name AS from_wh, wt.name AS to_wh, u.name AS created_name
             FROM stock_transfers t
             JOIN warehouses wf ON wf.id = t.from_warehouse_id
             JOIN warehouses wt ON wt.id = t.to_warehouse_id
             LEFT JOIN users u ON u.id = t.created_by
             WHERE t.company_id = ?
             ORDER BY t.id DESC
             LIMIT ? OFFSET ?',
            [$companyId, $perPage, $offset]
        );
        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage)), 'page' => $page];
    }
}
