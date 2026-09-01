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
use app\Services\PermissionService;

/**
 * Item group master — simple CRUD with account overrides for the
 * item → group → company default fallback.
 */
final class ItemGroupController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requirePermission('inventory', 'item_group', 'view');

        $companyId = CompanyContextService::currentCompanyId();
        $groups = Database::query(
            'SELECT g.*,
                    (SELECT COUNT(*) FROM items i WHERE i.item_group_id = g.id AND i.deleted_at IS NULL) AS item_count
             FROM item_groups g
             WHERE g.deleted_at IS NULL
             ORDER BY g.name'
        );

        return $this->view('item_groups/index', [
            'title'  => 'Item Groups',
            'groups' => $groups,
        ])->render();
    }

    public function store(): mixed
    {
        $this->requirePermission('inventory', 'item_group', 'create');

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name' => 'required|max:120',
            'code' => 'max:30',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        $dup = Database::row('SELECT id FROM item_groups WHERE name = ? AND deleted_at IS NULL', [$d['name']]);
        if ($dup) {
            flash('error', 'An item group with this name already exists.');
            return $this->response->back();
        }

        Database::execute(
            'INSERT INTO item_groups (name, code, inventory_account_id, cogs_account_id, sales_account_id, purchase_account_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['name'],
                $d['code'] ?? '',
                !empty($d['inventory_account_id']) ? (int) $d['inventory_account_id'] : null,
                !empty($d['cogs_account_id']) ? (int) $d['cogs_account_id'] : null,
                !empty($d['sales_account_id']) ? (int) $d['sales_account_id'] : null,
                !empty($d['purchase_account_id']) ? (int) $d['purchase_account_id'] : null,
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s'),
            ]
        );
        $id = (int) Database::lastInsertId();

        AuditService::log('create', 'inventory', 'item_group', $id, "Created item group {$d['name']}");
        flash('success', 'Item group created.');
        return $this->response->redirect($this->request->url('/item-groups'));
    }

    public function editForm(int|string $id): string
    {
        $this->requirePermission('inventory', 'item_group', 'edit');
        $group = Database::row('SELECT * FROM item_groups WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$group) {
            flash('error', 'Item group not found.');
            return (string) $this->response->redirect($this->request->url('/item-groups'))->body();
        }
        $accounts = AccountService::leafAccounts(CompanyContextService::currentCompanyId());
        return $this->view('item_groups/form', ['title' => 'Edit Item Group', 'group' => $group, 'accounts' => $accounts])->render();
    }

    public function update(int|string $id): mixed
    {
        $this->requirePermission('inventory', 'item_group', 'edit');
        $id = (int) $id;

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name' => 'required|max:120',
            'code' => 'max:30',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        Database::execute(
            'UPDATE item_groups
             SET name = ?, code = ?, inventory_account_id = ?, cogs_account_id = ?, sales_account_id = ?, purchase_account_id = ?, updated_at = ?
             WHERE id = ?',
            [
                $d['name'],
                $d['code'] ?? '',
                !empty($d['inventory_account_id']) ? (int) $d['inventory_account_id'] : null,
                !empty($d['cogs_account_id']) ? (int) $d['cogs_account_id'] : null,
                !empty($d['sales_account_id']) ? (int) $d['sales_account_id'] : null,
                !empty($d['purchase_account_id']) ? (int) $d['purchase_account_id'] : null,
                date('Y-m-d H:i:s'),
                $id,
            ]
        );

        AuditService::log('update', 'inventory', 'item_group', $id, "Updated item group {$d['name']}");
        flash('success', 'Item group updated.');
        return $this->response->redirect($this->request->url('/item-groups'));
    }

    public function destroy(int|string $id): mixed
    {
        $this->requirePermission('inventory', 'item_group', 'delete');
        $id = (int) $id;

        $group = Database::row('SELECT * FROM item_groups WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$group) {
            flash('error', 'Item group not found.');
            return $this->response->back();
        }
        $items = (int) Database::value('SELECT COUNT(*) FROM items WHERE item_group_id = ? AND deleted_at IS NULL', [$id]);
        if ($items > 0) {
            flash('error', 'This group contains ' . $items . ' item(s). Move them to another group first.');
            return $this->response->back();
        }

        Database::execute('UPDATE item_groups SET deleted_at = ?, updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id]);
        AuditService::log('delete', 'inventory', 'item_group', $id, "Deleted item group {$group['name']}");
        flash('success', 'Item group deleted.');
        return $this->response->redirect($this->request->url('/item-groups'));
    }
}
