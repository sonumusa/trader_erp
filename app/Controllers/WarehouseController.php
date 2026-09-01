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
use app\Services\PermissionService;

/**
 * Warehouse master — simple, real CRUD on the warehouses table.
 * (Stock movement & costing engine arrive in Phase 5.)
 */
final class WarehouseController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requirePermission('inventory', 'warehouse', 'view');
        $this->requireFeature('warehouses');

        $companyId = CompanyContextService::currentCompanyId();
        $warehouses = Database::query(
            'SELECT w.*, b.name AS branch_name
             FROM warehouses w
             LEFT JOIN branches b ON b.id = w.branch_id
             WHERE w.company_id = ? AND w.deleted_at IS NULL
             ORDER BY w.is_default DESC, w.name',
            [$companyId]
        );

        return $this->view('warehouses/index', [
            'title'      => 'Warehouses',
            'warehouses' => $warehouses,
        ])->render();
    }

    public function createForm(): string
    {
        $this->requirePermission('inventory', 'warehouse', 'create');
        $this->requireFeature('warehouses');
        return $this->view('warehouses/form', [
            'title'     => 'New Warehouse',
            'warehouse' => null,
            'branches'  => CompanyContextService::branches(),
        ])->render();
    }

    public function store(): mixed
    {
        $this->requirePermission('inventory', 'warehouse', 'create');
        $this->requireFeature('warehouses');

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name' => 'required|max:120',
            'code' => 'required|max:30',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        $companyId = CompanyContextService::currentCompanyId();
        $branchId = (int) ($d['branch_id'] ?? 0) ?: CompanyContextService::currentBranchId();

        Database::execute(
            'INSERT INTO warehouses (company_id, branch_id, name, code, address, is_default, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, \'active\', ?, ?)',
            [$companyId, $branchId, $d['name'], $d['code'], $d['address'] ?? '',
             ($d['is_default'] ?? '0') === '1' ? 1 : 0, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        $id = (int) Database::lastInsertId();

        if (($d['is_default'] ?? '0') === '1') {
            Database::execute('UPDATE warehouses SET is_default = 0 WHERE company_id = ? AND id != ?', [$companyId, $id]);
        }

        AuditService::log('create', 'inventory', 'warehouse', $id, "Created warehouse {$d['name']}");
        flash('success', 'Warehouse created.');
        return $this->response->redirect($this->request->url('/warehouses'));
    }

    public function editForm(int|string $id): string
    {
        $this->requirePermission('inventory', 'warehouse', 'edit');
        $warehouse = Database::row('SELECT * FROM warehouses WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$warehouse) {
            flash('error', 'Warehouse not found.');
            return (string) $this->response->redirect($this->request->url('/warehouses'))->body();
        }
        $branches = CompanyContextService::branches();
        return $this->view('warehouses/form', [
            'title'     => 'Edit Warehouse',
            'warehouse' => $warehouse,
            'branches'  => $branches,
        ])->render();
    }

    public function update(int|string $id): mixed
    {
        $this->requirePermission('inventory', 'warehouse', 'edit');
        $this->requireFeature('warehouses');
        $id = (int) $id;

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name' => 'required|max:120',
            'code' => 'required|max:30',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        $branchId = (int) ($d['branch_id'] ?? 0) ?: null;
        Database::execute(
            'UPDATE warehouses SET name = ?, code = ?, address = ?, branch_id = ?, status = ?, updated_at = ? WHERE id = ?',
            [$d['name'], $d['code'], $d['address'] ?? '', $branchId, ($d['status'] ?? 'active') === 'active' ? 'active' : 'inactive', date('Y-m-d H:i:s'), $id]
        );

        AuditService::log('update', 'inventory', 'warehouse', $id, "Updated warehouse {$d['name']}");
        flash('success', 'Warehouse updated.');
        return $this->response->redirect($this->request->url('/warehouses'));
    }

    public function destroy(int|string $id): mixed
    {
        $this->requirePermission('inventory', 'warehouse', 'delete');
        $id = (int) $id;

        $warehouse = Database::row('SELECT * FROM warehouses WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$warehouse) {
            flash('error', 'Warehouse not found.');
            return $this->response->back();
        }
        if ((int) $warehouse['is_default'] === 1) {
            flash('error', 'The default warehouse cannot be deleted.');
            return $this->response->back();
        }

        Database::execute('UPDATE warehouses SET deleted_at = ?, status = \'inactive\', updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id]);
        AuditService::log('delete', 'inventory', 'warehouse', $id, "Deleted warehouse {$warehouse['name']}");
        flash('success', 'Warehouse deleted.');
        return $this->response->redirect($this->request->url('/warehouses'));
    }
}
