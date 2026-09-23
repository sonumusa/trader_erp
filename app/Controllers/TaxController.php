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
 * Tax master — configurable tax rates (e.g. Sales Tax 17%).
 * Used by items (tax category) and later by sales/purchase documents.
 */
final class TaxController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requirePermission('settings', 'tax', 'view');

        $companyId = CompanyContextService::currentCompanyId();
        $taxes = Database::query(
            'SELECT * FROM taxes WHERE company_id = ? AND deleted_at IS NULL ORDER BY name',
            [$companyId]
        );

        return $this->view('settings/taxes', [
            'title' => 'Taxes',
            'taxes' => $taxes,
        ])->render();
    }

    public function store(): mixed
    {
        $this->requirePermission('settings', 'tax', 'create');

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name' => 'required|max:80',
            'tax_type' => 'required|max:40',
            'rate' => 'required|numeric|min:0|max:100',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();
        if (!empty($d['effective_from']) && !empty($d['effective_to']) && $d['effective_from'] > $d['effective_to']) {
            flash('error', 'Effective end date must be on or after the start date.');
            return $this->response->back();
        }
        if (Database::value('SELECT id FROM taxes WHERE company_id = ? AND name = ? AND deleted_at IS NULL', [CompanyContextService::currentCompanyId(), $d['name']])) {
            flash('error', 'A tax rate with this name already exists.');
            return $this->response->back();
        }

        Database::execute(
            'INSERT INTO taxes (company_id, name, tax_type, rate, effective_from, effective_to, description, is_system, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1, ?, ?)',
            [CompanyContextService::currentCompanyId(), $d['name'], $d['tax_type'], (float) $d['rate'], $d['effective_from'] ?: null, $d['effective_to'] ?: null, $d['description'] ?? '', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        $id = (int) Database::lastInsertId();

        AuditService::log('create', 'settings', 'tax', $id, "Created tax {$d['name']} @ {$d['rate']}%");
        flash('success', 'Tax created.');
        return $this->response->redirect($this->request->url('/settings/taxes'));
    }

    public function update(int|string $id): mixed
    {
        $this->requirePermission('settings', 'tax', 'edit');
        $id = (int) $id;

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name' => 'required|max:80',
            'tax_type' => 'required|max:40',
            'rate' => 'required|numeric|min:0|max:100',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();
        if (!empty($d['effective_from']) && !empty($d['effective_to']) && $d['effective_from'] > $d['effective_to']) {
            flash('error', 'Effective end date must be on or after the start date.');
            return $this->response->back();
        }
        if (Database::value('SELECT id FROM taxes WHERE company_id = ? AND name = ? AND id != ? AND deleted_at IS NULL', [CompanyContextService::currentCompanyId(), $d['name'], $id])) {
            flash('error', 'A tax rate with this name already exists.');
            return $this->response->back();
        }

        Database::execute(
            'UPDATE taxes SET name = ?, tax_type = ?, rate = ?, effective_from = ?, effective_to = ?, description = ?, is_active = ?, updated_at = ? WHERE id = ?',
            [$d['name'], $d['tax_type'], (float) $d['rate'], $d['effective_from'] ?: null, $d['effective_to'] ?: null, $d['description'] ?? '', ($d['is_active'] ?? '1') === '1' ? 1 : 0, date('Y-m-d H:i:s'), $id]
        );

        AuditService::log('update', 'settings', 'tax', $id, "Updated tax {$d['name']}");
        flash('success', 'Tax updated.');
        return $this->response->redirect($this->request->url('/settings/taxes'));
    }

    public function destroy(int|string $id): mixed
    {
        $this->requirePermission('settings', 'tax', 'delete');
        $id = (int) $id;

        $tax = Database::row('SELECT * FROM taxes WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$tax) {
            flash('error', 'Tax not found.');
            return $this->response->back();
        }
        $used = (int) Database::value('SELECT COUNT(*) FROM items WHERE tax_id = ? AND deleted_at IS NULL', [$id]);
        if ($used > 0) {
            flash('error', 'This tax is used by ' . $used . ' item(s). Deactivate it instead.');
            return $this->response->back();
        }

        Database::execute('UPDATE taxes SET deleted_at = ?, is_active = 0, updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id]);
        AuditService::log('delete', 'settings', 'tax', $id, "Deleted tax {$tax['name']}");
        flash('success', 'Tax deleted.');
        return $this->response->redirect($this->request->url('/settings/taxes'));
    }
}
