<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Database;
use app\Core\Request;
use app\Core\Response;
use app\Core\Validator;
use app\Services\AccountService;
use app\Services\AuditService;
use app\Services\CompanyContextService;
use app\Services\FeatureService;
use app\Services\InventoryEngine;
use app\Services\PermissionService;
use app\Services\UomService;

/**
 * Item master — codes, barcodes, groups, UOMs (item-specific), pricing,
 * stock flags, account overrides. Includes the AJAX search endpoint used by
 * transaction forms (code / name / barcode).
 */
final class ItemController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requirePermission('inventory', 'item', 'view');

        $page = $this->request->page();
        $per = 20;
        $search = (string) $this->request->query('q', '');
        $groupId = (int) $this->request->query('group', 0);
        $companyId = CompanyContextService::currentCompanyId();

        $where = 'WHERE i.company_id = ? AND i.deleted_at IS NULL';
        $params = [$companyId];
        if ($search !== '') {
            $where .= ' AND (i.item_code LIKE ? OR i.name LIKE ? OR i.barcode LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        if ($groupId > 0) {
            $where .= ' AND i.item_group_id = ?';
            $params[] = $groupId;
        }

        $total = (int) Database::value("SELECT COUNT(*) FROM items i {$where}", $params);
        $offset = max(0, ($page - 1) * $per);
        $rows = Database::query(
            "SELECT i.*, g.name AS group_name, u.name AS uom_name,
                    (SELECT COALESCE(SUM(sl.qty_in - sl.qty_out),0) FROM stock_ledger sl
                     WHERE sl.item_id = i.id) AS total_qty
             FROM items i
             LEFT JOIN item_groups g ON g.id = i.item_group_id
             LEFT JOIN uoms u ON u.id = i.stock_uom_id
             {$where}
             ORDER BY i.item_code
             LIMIT {$per} OFFSET {$offset}",
            $params
        );

        $groups = Database::query('SELECT id, name FROM item_groups WHERE deleted_at IS NULL ORDER BY name');

        return $this->view('items/index', [
            'title'    => 'Items',
            'rows'     => $rows,
            'total'    => $total,
            'pages'    => max(1, (int) ceil($total / $per)),
            'page'     => $page,
            'search'   => $search,
            'groups'   => $groups,
            'groupId'  => $groupId,
            'canCreate' => PermissionService::can('inventory', 'item', 'create'),
            'canEdit'   => PermissionService::can('inventory', 'item', 'edit'),
        ])->render();
    }

    /** AJAX item search — code / name / barcode, fast, LIMIT 20. */
    public function search(): mixed
    {
        $this->requirePermission('inventory', 'item', 'view');

        $q = trim((string) $this->request->query('q', ''));
        $companyId = CompanyContextService::currentCompanyId();

        if ($q === '') {
            return $this->jsonOk([]);
        }

        $rows = Database::query(
            'SELECT i.id, i.item_code, i.name, i.barcode, i.default_sales_rate, i.default_purchase_rate,
                    i.stock_uom_id, u.code AS uom_code,
                    t.rate AS tax_rate, t.name AS tax_name
             FROM items i
             LEFT JOIN uoms u ON u.id = i.stock_uom_id
             LEFT JOIN taxes t ON t.id = i.tax_id AND t.is_active = 1 AND t.deleted_at IS NULL
             WHERE i.company_id = ? AND i.deleted_at IS NULL AND i.status = \'active\'
               AND (i.item_code LIKE ? OR i.name LIKE ? OR i.barcode LIKE ?)
             ORDER BY i.item_code
             LIMIT 20',
            [$companyId, "%{$q}%", "%{$q}%", "%{$q}%"]
        );

        foreach ($rows as &$row) {
            $row['uoms'] = UomService::itemUoms((int) $row['id']);
        }
        unset($row);

        return $this->jsonOk($rows);
    }

    public function createForm(): string
    {
        $this->requirePermission('inventory', 'item', 'create');
        \app\Core\Session::forget('_old_input');
        return $this->view('items/form', $this->formData(null))->render();
    }

    public function store(): mixed
    {
        $this->requirePermission('inventory', 'item', 'create');

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'item_code' => 'required|max:60',
            'name'      => 'required|max:150',
            'stock_uom_id' => 'required|integer',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            session_set('_old_input', $this->request->all());
            return $this->response->back();
        }
        $d = $v->data();
        $companyId = CompanyContextService::currentCompanyId();

        $dup = Database::row('SELECT id FROM items WHERE company_id = ? AND item_code = ? AND deleted_at IS NULL', [$companyId, $d['item_code']]);
        if ($dup) {
            flash('error', 'An item with this code already exists.');
            return $this->response->back();
        }

        $id = $this->saveItem($companyId, null, $d);
        AuditService::log('create', 'inventory', 'item', $id, "Created item {$d['item_code']} {$d['name']}");
        flash('success', 'Item created.');
        return $this->response->redirect($this->request->url('/items'));
    }

    public function editForm(int|string $id): string
    {
        $this->requirePermission('inventory', 'item', 'edit');
        $item = Database::row('SELECT * FROM items WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$item) {
            flash('error', 'Item not found.');
            return (string) $this->response->redirect($this->request->url('/items'))->body();
        }
        \app\Core\Session::forget('_old_input');
        return $this->view('items/form', $this->formData($item))->render();
    }

    public function update(int|string $id): mixed
    {
        $this->requirePermission('inventory', 'item', 'edit');
        $id = (int) $id;

        $item = Database::row('SELECT * FROM items WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$item) {
            flash('error', 'Item not found.');
            return $this->response->back();
        }

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'item_code' => 'required|max:60',
            'name'      => 'required|max:150',
            'stock_uom_id' => 'required|integer',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        $code = $d['item_code'];
        $dup = Database::row('SELECT id FROM items WHERE company_id = ? AND item_code = ? AND id != ? AND deleted_at IS NULL', [$item['company_id'], $code, $id]);
        if ($dup) {
            flash('error', 'An item with this code already exists.');
            return $this->response->back();
        }

        $this->saveItem((int) $item['company_id'], $id, $d);
        AuditService::log('update', 'inventory', 'item', $id, "Updated item {$d['item_code']}");
        flash('success', 'Item updated.');
        return $this->response->redirect($this->request->url('/items'));
    }

    public function destroy(int|string $id): mixed
    {
        $this->requirePermission('inventory', 'item', 'delete');
        $id = (int) $id;

        $item = Database::row('SELECT * FROM items WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$item) {
            flash('error', 'Item not found.');
            return $this->response->back();
        }
        $stock = (float) InventoryEngine::availableQty($id, 0) + 0; // sum across warehouses
        $stock = (float) Database::value(
            'SELECT COALESCE(SUM(qty_in - qty_out),0) FROM stock_ledger WHERE item_id = ?',
            [$id]
        );
        if (abs($stock) > 0.0001) {
            flash('error', 'This item has stock (' . rtrim(rtrim(number_format($stock, 4), '0'), '.') . ' units). Deactivate it instead of deleting.');
            return $this->response->back();
        }
        $used = (int) Database::value(
            'SELECT COUNT(*) FROM (SELECT id FROM stock_ledger WHERE item_id = ? UNION ALL SELECT id FROM stock_transfer_items WHERE item_id = ?) t',
            [$id, $id]
        );
        if ($used > 0) {
            flash('error', 'This item appears in inventory documents. Deactivate it instead of deleting.');
            return $this->response->back();
        }

        Database::execute('UPDATE items SET deleted_at = ?, status = \'inactive\', updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id]);
        AuditService::log('delete', 'inventory', 'item', $id, "Deleted item {$item['item_code']}");
        flash('success', 'Item deleted.');
        return $this->response->redirect($this->request->url('/items'));
    }

    /** Stock ledger for one item with running balance + warehouse filter. */
    public function ledger(int|string $id): string
    {
        $this->requirePermission('inventory', 'item', 'view');
        $id = (int) $id;

        $item = Database::row('SELECT * FROM items WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$item) {
            flash('error', 'Item not found.');
            return (string) $this->response->redirect($this->request->url('/items'))->body();
        }

        $warehouseId = (int) $this->request->query('warehouse', 0);
        $data = InventoryEngine::ledger($id, $warehouseId ?: null, $this->request->page(), 40);
        $warehouses = Database::query(
            'SELECT id, name FROM warehouses WHERE company_id = ? AND deleted_at IS NULL ORDER BY name',
            [(int) $item['company_id']]
        );

        // All-warehouse totals when no specific warehouse is selected
        $available = $warehouseId
            ? InventoryEngine::availableQty($id, $warehouseId)
            : (float) Database::value(
                'SELECT COALESCE(SUM(qty_in - qty_out),0) FROM stock_ledger WHERE item_id = ?',
                [$id]
            );
        $valuation = $warehouseId
            ? InventoryEngine::valuation($id, $warehouseId)
            : (float) Database::value(
                'SELECT COALESCE(SUM(qty_remaining * rate),0) FROM stock_layers WHERE item_id = ?',
                [$id]
            );

        return $this->view('items/ledger', [
            'title'      => 'Stock Ledger — ' . $item['name'],
            'item'       => $item,
            'data'       => $data,
            'warehouses' => $warehouses,
            'warehouseId'=> $warehouseId,
            'available'  => $available,
            'valuation'  => $valuation,
        ])->render();
    }

    private function saveItem(int $companyId, ?int $id, array $d): int
    {
        $stockUomId = (int) $d['stock_uom_id'];

        // collect alternative UOMs
        $alts = [];
        $uomIds = $d['alt_uom_id'] ?? [];
        $factors = $d['alt_factor'] ?? [];
        if (is_array($uomIds)) {
            foreach ($uomIds as $i => $uomIdRaw) {
                $uid = (int) $uomIdRaw;
                if ($uid > 0 && $uid !== $stockUomId) {
                    $alts[] = ['uom_id' => $uid, 'factor' => (float) ($factors[$i] ?? 1)];
                }
            }
        }

        $now = date('Y-m-d H:i:s');

        if ($id === null) {
            Database::execute(
                'INSERT INTO items (company_id, item_code, name, barcode, item_group_id, brand, description,
                                    default_purchase_rate, default_sales_rate, stock_uom_id, min_stock, reorder_level,
                                    batch_enabled, serial_enabled, expiry_enabled,
                                    inventory_account_id, cogs_account_id, sales_account_id, purchase_account_id, tax_id,
                                    status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $companyId,
                    $d['item_code'],
                    $d['name'],
                    $d['barcode'] ?? '',
                    !empty($d['item_group_id']) ? (int) $d['item_group_id'] : null,
                    $d['brand'] ?? '',
                    $d['description'] ?? '',
                    (float) ($d['default_purchase_rate'] ?? 0),
                    (float) ($d['default_sales_rate'] ?? 0),
                    $stockUomId,
                    (float) ($d['min_stock'] ?? 0),
                    (float) ($d['reorder_level'] ?? 0),
                    !empty($d['batch_enabled']) ? 1 : 0,
                    !empty($d['serial_enabled']) ? 1 : 0,
                    !empty($d['expiry_enabled']) ? 1 : 0,
                    !empty($d['inventory_account_id']) ? (int) $d['inventory_account_id'] : null,
                    !empty($d['cogs_account_id']) ? (int) $d['cogs_account_id'] : null,
                    !empty($d['sales_account_id']) ? (int) $d['sales_account_id'] : null,
                    !empty($d['purchase_account_id']) ? (int) $d['purchase_account_id'] : null,
                    !empty($d['tax_id']) ? (int) $d['tax_id'] : null,
                    $d['status'] ?? 'active',
                    \app\Services\AuthService::id(),
                    $now,
                    $now,
                ]
            );
            $id = (int) Database::lastInsertId();
        } else {
            Database::execute(
                'UPDATE items
                 SET item_code = ?, name = ?, barcode = ?, item_group_id = ?, brand = ?, description = ?,
                     default_purchase_rate = ?, default_sales_rate = ?, stock_uom_id = ?, min_stock = ?, reorder_level = ?,
                     batch_enabled = ?, serial_enabled = ?, expiry_enabled = ?,
                     inventory_account_id = ?, cogs_account_id = ?, sales_account_id = ?, purchase_account_id = ?, tax_id = ?,
                     status = ?, updated_at = ?
                 WHERE id = ?',
                [
                    $d['item_code'],
                    $d['name'],
                    $d['barcode'] ?? '',
                    !empty($d['item_group_id']) ? (int) $d['item_group_id'] : null,
                    $d['brand'] ?? '',
                    $d['description'] ?? '',
                    (float) ($d['default_purchase_rate'] ?? 0),
                    (float) ($d['default_sales_rate'] ?? 0),
                    $stockUomId,
                    (float) ($d['min_stock'] ?? 0),
                    (float) ($d['reorder_level'] ?? 0),
                    !empty($d['batch_enabled']) ? 1 : 0,
                    !empty($d['serial_enabled']) ? 1 : 0,
                    !empty($d['expiry_enabled']) ? 1 : 0,
                    !empty($d['inventory_account_id']) ? (int) $d['inventory_account_id'] : null,
                    !empty($d['cogs_account_id']) ? (int) $d['cogs_account_id'] : null,
                    !empty($d['sales_account_id']) ? (int) $d['sales_account_id'] : null,
                    !empty($d['purchase_account_id']) ? (int) $d['purchase_account_id'] : null,
                    !empty($d['tax_id']) ? (int) $d['tax_id'] : null,
                    $d['status'] ?? 'active',
                    $now,
                    $id,
                ]
            );
        }

        UomService::syncItemUoms($id, $stockUomId, $alts);
        return $id;
    }

    private function formData(?array $item): array
    {
        $companyId = CompanyContextService::currentCompanyId();
        return [
            'title'       => $item ? 'Edit Item' : 'New Item',
            'item'        => $item,
            'groups'      => Database::query('SELECT id, name FROM item_groups WHERE deleted_at IS NULL ORDER BY name'),
            'uoms'        => Database::query('SELECT id, name, code FROM uoms WHERE deleted_at IS NULL ORDER BY name'),
            'itemUoms'    => $item ? UomService::itemUoms((int) $item['id']) : [],
            'taxes'       => Database::query('SELECT id, name, rate FROM taxes WHERE company_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY name', [$companyId]),
            'accounts'    => AccountService::leafAccounts($companyId),
            'batchEnabled'=> FeatureService::isEnabled('batch'),
            'serialEnabled' => FeatureService::isEnabled('serial'),
            'expiryEnabled' => FeatureService::isEnabled('expiry'),
        ];
    }
}
