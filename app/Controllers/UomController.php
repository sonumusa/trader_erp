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
 * UOM master — simple, real CRUD on the uoms table.
 * (The inventory engine that uses these arrives in Phase 5.)
 */
final class UomController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requirePermission('inventory', 'uom', 'view');

        $uoms = Database::query(
            'SELECT u.*,
                    (SELECT COUNT(*) FROM items i WHERE i.stock_uom_id = u.id AND i.deleted_at IS NULL) AS item_count
             FROM uoms u WHERE u.deleted_at IS NULL ORDER BY u.name'
        );

        return $this->view('uoms/index', [
            'title' => 'Units of Measure',
            'uoms'  => $uoms,
        ])->render();
    }

    public function store(): mixed
    {
        $this->requirePermission('inventory', 'uom', 'create');

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name' => 'required|max:60',
            'code' => 'required|max:20|unique:uoms,code',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        Database::execute(
            'INSERT INTO uoms (name, code, description, is_active, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?)',
            [$d['name'], strtoupper($d['code']), $d['description'] ?? '', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        $id = (int) Database::lastInsertId();

        AuditService::log('create', 'inventory', 'uom', $id, "Created UOM {$d['name']} ({$d['code']})");
        flash('success', 'UOM created.');
        return $this->response->redirect($this->request->url('/uoms'));
    }

    public function update(int|string $id): mixed
    {
        $this->requirePermission('inventory', 'uom', 'edit');
        $id = (int) $id;

        $code = (string) $this->request->input('code');
        $dup = Database::row('SELECT id FROM uoms WHERE code = ? AND id != ? AND deleted_at IS NULL', [strtoupper($code), $id]);
        if ($dup) {
            flash('error', 'That UOM code is already in use.');
            return $this->response->back();
        }

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name' => 'required|max:60',
            'code' => 'required|max:20',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        Database::execute(
            'UPDATE uoms SET name = ?, code = ?, description = ?, is_active = ?, updated_at = ? WHERE id = ?',
            [$d['name'], strtoupper($d['code']), $d['description'] ?? '', ($d['is_active'] ?? '1') === '1' ? 1 : 0, date('Y-m-d H:i:s'), $id]
        );

        AuditService::log('update', 'inventory', 'uom', $id, "Updated UOM {$d['name']}");
        flash('success', 'UOM updated.');
        return $this->response->redirect($this->request->url('/uoms'));
    }

    public function destroy(int|string $id): mixed
    {
        $this->requirePermission('inventory', 'uom', 'delete');
        $id = (int) $id;

        $uom = Database::row('SELECT * FROM uoms WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$uom) {
            flash('error', 'UOM not found.');
            return $this->response->back();
        }
        $inUse = (int) Database::value('SELECT COUNT(*) FROM items WHERE stock_uom_id = ?', [$id]);
        if ($inUse > 0) {
            flash('error', 'This UOM is used by items and cannot be deleted.');
            return $this->response->back();
        }

        Database::execute('UPDATE uoms SET deleted_at = ?, updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id]);
        AuditService::log('delete', 'inventory', 'uom', $id, "Deleted UOM {$uom['name']}");
        flash('success', 'UOM deleted.');
        return $this->response->redirect($this->request->url('/uoms'));
    }
}
