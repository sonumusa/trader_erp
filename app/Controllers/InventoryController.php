<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Database;
use app\Core\Request;
use app\Core\Response;
use app\Core\Validator;
use app\Services\AuditService;
use app\Services\CompanyContextService;
use app\Services\InventoryEngine;
use app\Services\OpeningStockService;
use app\Services\PermissionService;
use app\Services\SettingsService;
use app\Services\StockAdjustmentService;
use app\Services\StockTransferService;

/**
 * Inventory operations — transfers, adjustments, opening stock, stock balance.
 * (Document entry forms use the AJAX item search; the item picker shows only
 * the selected item's UOMs.)
 */
final class InventoryController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    /* ---------------- Stock balance ---------------- */

    public function stockBalance(): string
    {
        $this->requirePermission('inventory', 'stock', 'view');

        $companyId = CompanyContextService::currentCompanyId();
        $warehouseId = (int) $this->request->query('warehouse', 0);
        $search = (string) $this->request->query('q', '');

        $rows = InventoryEngine::stockBalance($companyId, $warehouseId ?: null, $search);
        $warehouses = Database::query('SELECT id, name FROM warehouses WHERE company_id = ? AND deleted_at IS NULL ORDER BY name', [$companyId]);
        $totalValue = array_sum(array_map('floatval', array_column($rows, 'value')));

        return $this->view('inventory/stock_balance', [
            'title'     => 'Stock Balance',
            'rows'      => $rows,
            'warehouses' => $warehouses,
            'warehouseId' => $warehouseId,
            'search'    => $search,
            'totalValue' => $totalValue,
        ])->render();
    }

    public function lowStock(): string
    {
        $this->requirePermission('inventory', 'stock', 'view');
        $companyId = CompanyContextService::currentCompanyId();

        return $this->view('inventory/low_stock', [
            'title' => 'Low Stock',
            'rows'  => InventoryEngine::lowStock($companyId),
        ])->render();
    }

    /* ---------------- Transfers ---------------- */

    public function transfers(): string
    {
        $this->requirePermission('inventory', 'transfer', 'view');
        $companyId = CompanyContextService::currentCompanyId();
        $data = StockTransferService::paginate($companyId, $this->request->page());

        return $this->view('inventory/transfers/index', [
            'title' => 'Stock Transfers',
            'data'  => $data,
        ])->render();
    }

    public function transferForm(): string
    {
        $this->requirePermission('inventory', 'transfer', 'create');
        $companyId = CompanyContextService::currentCompanyId();

        return $this->view('inventory/transfers/form', [
            'title'      => 'New Stock Transfer',
            'warehouses' => Database::query('SELECT id, name FROM warehouses WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
        ])->render();
    }

    public function transferStore(): mixed
    {
        $this->requirePermission('inventory', 'transfer', 'create');
        $companyId = CompanyContextService::currentCompanyId();

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'transfer_date' => 'required|date',
            'from_warehouse_id' => 'required|integer',
            'to_warehouse_id'   => 'required|integer',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        try {
            $id = StockTransferService::create($companyId, [
                'branch_id'        => CompanyContextService::currentBranchId(),
                'transfer_date'    => $d['transfer_date'],
                'from_warehouse_id' => (int) $d['from_warehouse_id'],
                'to_warehouse_id'   => (int) $d['to_warehouse_id'],
                'narration'        => (string) $this->request->input('narration', ''),
                'lines'            => $this->linesFromInput('items'),
            ]);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('create', 'inventory', 'transfer', $id, 'Created stock transfer');
        flash('success', 'Stock transfer posted.');
        return $this->response->redirect($this->request->url('/inventory/transfers'));
    }

    public function transferReverse(int|string $id): mixed
    {
        $this->requirePermission('inventory', 'transfer', 'delete');
        $id = (int) $id;

        try {
            StockTransferService::reverse($id, (string) $this->request->input('reason', 'Manual cancellation'));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('cancel', 'inventory', 'transfer', $id, 'Cancelled stock transfer');
        flash('success', 'Transfer cancelled and reversed.');
        return $this->response->redirect($this->request->url('/inventory/transfers'));
    }

    /* ---------------- Adjustments ---------------- */

    public function adjustments(): string
    {
        $this->requirePermission('inventory', 'adjustment', 'view');
        $companyId = CompanyContextService::currentCompanyId();
        $data = StockAdjustmentService::paginate($companyId, $this->request->page());

        return $this->view('inventory/adjustments/index', [
            'title' => 'Stock Adjustments',
            'data'  => $data,
        ])->render();
    }

    public function adjustmentForm(): string
    {
        $this->requirePermission('inventory', 'adjustment', 'create');
        $companyId = CompanyContextService::currentCompanyId();

        return $this->view('inventory/adjustments/form', [
            'title'      => 'New Stock Adjustment',
            'warehouses' => Database::query('SELECT id, name FROM warehouses WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
        ])->render();
    }

    public function adjustmentStore(): mixed
    {
        $this->requirePermission('inventory', 'adjustment', 'create');
        $companyId = CompanyContextService::currentCompanyId();

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'adjustment_date' => 'required|date',
            'reason'          => 'required|max:255',
        ])) {
            flash('error', $v->firstError() ?? 'Reason is required.');
            return $this->response->back();
        }
        $d = $v->data();

        try {
            $id = StockAdjustmentService::create($companyId, [
                'branch_id'      => CompanyContextService::currentBranchId(),
                'adjustment_date' => $d['adjustment_date'],
                'reason'         => $d['reason'],
                'lines'          => $this->linesFromInput('items'),
            ]);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('create', 'inventory', 'adjustment', $id, 'Created stock adjustment: ' . $d['reason']);
        flash('success', 'Stock adjustment posted.');
        return $this->response->redirect($this->request->url('/inventory/adjustments'));
    }

    /* ---------------- Opening stock ---------------- */

    public function openingStockForm(): string
    {
        $this->requirePermission('inventory', 'opening_stock', 'create');
        $companyId = CompanyContextService::currentCompanyId();

        return $this->view('inventory/opening_stock', [
            'title'      => 'Opening Stock',
            'warehouses' => Database::query('SELECT id, name FROM warehouses WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
        ])->render();
    }

    public function openingStockStore(): mixed
    {
        $this->requirePermission('inventory', 'opening_stock', 'create');
        $companyId = CompanyContextService::currentCompanyId();

        try {
            $result = OpeningStockService::post($companyId, $this->linesFromInput('items'));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('create', 'inventory', 'opening_stock', null, 'Posted opening stock value ' . format_money($result['value']));
        flash('success', 'Opening stock posted (' . $result['posted'] . ' line(s), value ' . format_money($result['value']) . ').');
        return $this->response->redirect($this->request->url('/inventory/opening-stock'));
    }

    /* ---------------- helpers ---------------- */

    /**
     * Parse the dynamic item-line inputs:
     * items[item_id][], items[uom_id][], items[quantity][], items[rate][],
     * items[warehouse_id][], items[batch_no][], items[serials][]
     */
    /* ---------------- Costing method (controlled change) ---------------- */

    public function costingUpdate(): mixed
    {
        $this->requirePermission('settings', 'setting', 'edit');

        $companyId = CompanyContextService::currentCompanyId();
        $method = (string) $this->request->input('inventory_cost_method', '');
        $note = (string) $this->request->input('note', '');

        if (!isset(InventoryEngine::costingMethods()[$method])) {
            flash('error', 'Select a valid costing method.');
            return $this->response->back();
        }

        try {
            $result = InventoryEngine::changeCostingMethod($companyId, $method, $note);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        if ($result['movements_replayed'] === 0 && $result['old_method'] === $result['new_method']) {
            flash('info', 'The costing method is already ' . InventoryEngine::costingMethodLabel($method) . '.');
        } else {
            flash('success', sprintf(
                'Costing method changed from %s to %s. %d stock movements re-valued.',
                InventoryEngine::costingMethodLabel($result['old_method']),
                InventoryEngine::costingMethodLabel($result['new_method']),
                $result['movements_replayed']
            ));
        }
        return $this->response->redirect($this->request->url('/settings'));
    }

    /* ---------------- Item-wise cost report ---------------- */

    public function itemCost(): string
    {
        $this->requirePermission('inventory', 'stock', 'view');

        $companyId = CompanyContextService::currentCompanyId();
        $search = (string) $this->request->query('q', '');
        $rows = InventoryEngine::itemWiseCost($companyId, $search);

        $totalValue = array_sum(array_map(fn($r) => (float) $r['valuation'], $rows));

        return $this->view('inventory/item_cost', [
            'title'      => 'Item-wise Cost',
            'rows'       => $rows,
            'search'     => $search,
            'totalValue' => $totalValue,
            'method'     => SettingsService::get('inventory_cost_method', 'moving_average'),
        ])->render();
    }

    private function linesFromInput(string $name): array
    {
        $raw = $this->request->input($name, []);
        $raw = is_array($raw) ? $raw : [];

        $items = $raw['item_id'] ?? [];
        if (!is_array($items) || $items === []) {
            return [];
        }

        $lines = [];
        foreach ($items as $i => $itemIdRaw) {
            $itemId = (int) $itemIdRaw;
            if ($itemId <= 0) {
                continue;
            }
            $lines[] = [
                'item_id'     => $itemId,
                'uom_id'      => (int) ($raw['uom_id'][$i] ?? 0),
                'quantity'    => (float) ($raw['quantity'][$i] ?? 0),
                'rate'        => (float) ($raw['rate'][$i] ?? 0),
                'warehouse_id' => (int) ($raw['warehouse_id'][$i] ?? 0),
                'batch'       => [
                    'batch_no'   => (string) ($raw['batch_no'][$i] ?? ''),
                    'mfg_date'   => (string) ($raw['mfg_date'][$i] ?? '') ?: null,
                    'expiry_date'=> (string) ($raw['expiry_date'][$i] ?? '') ?: null,
                ],
                'serials'     => array_filter(array_map('trim', explode(',', (string) ($raw['serials'][$i] ?? '')))),
            ];
        }
        return $lines;
    }
}
