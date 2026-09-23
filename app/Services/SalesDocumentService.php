<?php

declare(strict_types=1);

namespace app\Services;

use app\Core\Database;

/**
 * Sales quotations & orders (pre-invoice documents).
 * No stock / accounting impact — mirror of the purchase document service.
 */
final class SalesDocumentService
{
    public static function createQuotation(int $companyId, array $data): int
    {
        return self::createDocument('quotation', $companyId, $data);
    }

    public static function createOrder(int $companyId, array $data): int
    {
        return self::createDocument('order', $companyId, $data);
    }

    public static function findOrder(int $id): ?array
    {
        $order = Database::row('SELECT * FROM sales_orders WHERE id = ?', [$id]);
        if ($order) {
            $order['items'] = Database::query('SELECT * FROM sales_order_items WHERE order_id = ? ORDER BY id', [$id]);
        }
        return $order;
    }

    public static function updateOrder(int $id, array $data): void
    {
        $order = self::findOrder($id);
        if (!$order || $order['status'] !== 'posted') throw new \RuntimeException('Order not found or cancelled.');
        $customer = Database::row('SELECT id FROM customers WHERE id = ? AND company_id = ? AND deleted_at IS NULL', [(int) $data['customer_id'], (int) $order['company_id']]);
        if (!$customer || empty($data['lines'])) throw new \RuntimeException('Select a valid customer and add at least one item.');
        $lines = array_map(fn($line) => PurchaseService::calculateLine($line), $data['lines']);
        foreach ($lines as $line) {
            if (PurchaseService::invoicedBaseQty('sales', $id, (int) $line['item_id']) > (float) $line['base_qty'] + 0.0001) throw new \RuntimeException('An order line cannot be reduced below its already invoiced quantity.');
        }
        Database::transaction(function () use ($id, $data, $lines): void {
            Database::execute('UPDATE sales_orders SET customer_id = ?, order_date = ?, reference_quotation_id = ?, narration = ?, updated_at = ? WHERE id = ?', [(int) $data['customer_id'], $data['date'], $data['reference_quotation_id'] ?? null, $data['narration'] ?? '', date('Y-m-d H:i:s'), $id]);
            Database::execute('DELETE FROM sales_order_items WHERE order_id = ?', [$id]);
            $stmt = Database::pdo()->prepare('INSERT INTO sales_order_items (order_id, item_id, uom_id, quantity, base_qty, rate, discount, tax_id, tax_rate, tax_amount, amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($lines as $line) $stmt->execute([$id, $line['item_id'], $line['uom_id'] ?: null, $line['quantity'], $line['base_qty'], $line['rate'], $line['discount'], $line['tax_id'], $line['tax_rate'], $line['tax_amount'], $line['amount']]);
        });
    }

    private static function createDocument(string $kind, int $companyId, array $data): int
    {
        $customerId = (int) ($data['customer_id'] ?? 0);
        $customer = Database::row(
            'SELECT * FROM customers WHERE id = ? AND company_id = ? AND deleted_at IS NULL',
            [$customerId, $companyId]
        );
        if (!$customer) {
            throw new \RuntimeException('Select a valid customer.');
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
        $no = DocumentNumberService::next($isQuotation ? 'sales_quotation' : 'sales_order', $companyId, $data['branch_id'] ?? null);
        $table = $isQuotation ? 'sales_quotations' : 'sales_orders';
        $itemTable = $isQuotation ? 'sales_quotation_items' : 'sales_order_items';
        $noCol = $isQuotation ? 'quotation_no' : 'order_no';
        $dateCol = $isQuotation ? 'quotation_date' : 'order_date';
        $idCol = $isQuotation ? 'quotation_id' : 'order_id';
        $userId = AuthService::id();
        $now = date('Y-m-d H:i:s');

        return (int) Database::transaction(function () use ($companyId, $data, $customer, $lines, $totals, $no, $table, $itemTable, $noCol, $dateCol, $idCol, $userId, $now, $isQuotation) {
            Database::execute(
                "INSERT INTO {$table} (company_id, branch_id, {$noCol}, {$dateCol}, customer_id, "
                . ($isQuotation ? '' : 'reference_quotation_id, ') . "narration, status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, " . ($isQuotation ? '' : '?, ') . "?, 'posted', ?, ?, ?)",
                array_merge(
                    [$companyId, $data['branch_id'] ?? null, $no, $data['date'], (int) $customer['id']],
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
        $table = $isQuotation ? 'sales_quotations' : 'sales_orders';
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
        $table = $isQuotation ? 'sales_quotations' : 'sales_orders';
        $total = (int) Database::value("SELECT COUNT(*) FROM {$table} WHERE company_id = ?", [$companyId]);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::query(
            "SELECT d.*, c.name AS customer_name
                    , (SELECT COALESCE(SUM(soi.base_qty), 0) FROM sales_order_items soi WHERE soi.order_id = d.id) AS ordered_qty
                    , (SELECT COALESCE(SUM(sii.base_qty), 0) FROM sales_invoice_items sii JOIN sales_invoices si ON si.id = sii.invoice_id WHERE si.reference_order_id = d.id AND si.status = 'posted') AS invoiced_qty
             FROM {$table} d
             JOIN customers c ON c.id = d.customer_id
             WHERE d.company_id = ?
             ORDER BY d.id DESC
             LIMIT ? OFFSET ?",
            [$companyId, $perPage, $offset]
        );
        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int) ceil($total / $perPage)), 'page' => $page];
    }
}
