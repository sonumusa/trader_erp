<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Purchase quotations & orders (pre-invoice documents).
 * No stock / accounting impact — saved documents for the workflow
 * Quotation → Order → Invoice. Both support cancellation (status only).
 */
final class PurchaseDocumentService
{
    public static function createQuotation(int $companyId, array $data): int
    {
        return self::createDocument('quotation', $companyId, $data);
    }

    public static function createOrder(int $companyId, array $data): int
    {
        return self::createDocument('order', $companyId, $data);
    }

    private static function createDocument(string $kind, int $companyId, array $data): int
    {
        $supplier = Database::row(
            'SELECT * FROM suppliers WHERE id = ? AND company_id = ? AND deleted_at IS NULL',
            [(int) ($data['supplier_id'] ?? 0), $companyId]
        );
        if (!$supplier) {
            throw new \RuntimeException('Select a valid supplier.');
        }
        $rawLines = $data['lines'] ?? [];
        if ($rawLines === []) {
            throw new \RuntimeException('Add at least one item.');
        }

        $lines = [];
        foreach ($rawLines as $raw) {
            $lines[] = PurchaseService::calculateLine($raw);
        }
        $totals = PurchaseService::totals($lines);

        $isQuotation = $kind === 'quotation';
        $no = DocumentNumberService::next($isQuotation ? 'purchase_quotation' : 'purchase_order', $companyId, $data['branch_id'] ?? null);
        $dateCol = $isQuotation ? 'quotation_date' : 'order_date';
        $noCol = $isQuotation ? 'quotation_no' : 'order_no';
        $table = $isQuotation ? 'purchase_quotations' : 'purchase_orders';
        $itemTable = $isQuotation ? 'purchase_quotation_items' : 'purchase_order_items';
        $idCol = $isQuotation ? 'quotation_id' : 'order_id';
        $userId = AuthService::id();
        $now = date('Y-m-d H:i:s');

        return (int) Database::transaction(function () use ($companyId, $data, $supplier, $lines, $totals, $no, $dateCol, $noCol, $table, $itemTable, $idCol, $userId, $now, $isQuotation) {
            Database::execute(
                "INSERT INTO {$table} (company_id, branch_id, {$noCol}, {$dateCol}, supplier_id, "
                . ($isQuotation ? '' : 'reference_quotation_id, ') . "narration, status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, " . ($isQuotation ? '' : '?, ') . "?, 'posted', ?, ?, ?)",
                array_merge(
                    [$companyId, $data['branch_id'] ?? null, $no, $data['date'], (int) $supplier['id']],
                    $isQuotation ? [] : [!empty($data['reference_quotation_id']) ? (int) $data['reference_quotation_id'] : null],
                    [$data['narration'] ?? '', $userId, $now, $now]
                )
            );
            $docId = (int) Database::lastInsertId();

            $stmt = Database::pdo()->prepare(
                "INSERT INTO {$itemTable} ({$idCol}, item_id, uom_id, quantity, base_qty, rate, discount, tax_id, tax_rate, tax_amount, amount)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($lines as $line) {
                $stmt->execute([
                    $docId, (int) $line['item_id'], $line['uom_id'] ?: null, $line['quantity'], $line['base_qty'],
                    $line['rate'], $line['discount'], $line['tax_id'], $line['tax_rate'], $line['tax_amount'], $line['amount'],
                ]);
            }

            return $docId;
        });
    }

    public static function cancel(string $kind, int $id, string $reason): void
    {
        $isQuotation = $kind === 'quotation';
        $table = $isQuotation ? 'purchase_quotations' : 'purchase_orders';
        $noCol = $isQuotation ? 'quotation_no' : 'order_no';
        $doc = Database::row("SELECT * FROM {$table} WHERE id = ?", [$id]);
        if (!$doc || $doc['status'] === 'cancelled') {
            throw new \RuntimeException('Document not found or already cancelled.');
        }
        Database::execute(
            "UPDATE {$table} SET status = 'cancelled', cancelled_by = ?, cancelled_at = ?, cancelled_reason = ?, updated_at = ? WHERE id = ?",
            [AuthService::id(), date('Y-m-d H:i:s'), mb_substr($reason, 0, 255), date('Y-m-d H:i:s'), $id]
        );
    }

    public static function paginate(string $kind, int $companyId, int $page = 1, int $perPage = 20): array
    {
        $isQuotation = $kind === 'quotation';
        $table = $isQuotation ? 'purchase_quotations' : 'purchase_orders';
        $total = (int) Database::value("SELECT COUNT(*) FROM {$table} WHERE company_id = ?", [$companyId]);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::query(
            "SELECT d.*, s.name AS supplier_name
             FROM {$table} d
             JOIN suppliers s ON s.id = d.supplier_id
             WHERE d.company_id = ?
             ORDER BY d.id DESC
             LIMIT ? OFFSET ?",
            [$companyId, $perPage, $offset]
        );
        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage)), 'page' => $page];
    }
}
