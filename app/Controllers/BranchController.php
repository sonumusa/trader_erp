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
use app\Services\FeatureService;
use app\Services\PermissionService;

/**
 * Branch management — list, create, edit, delete (soft), switch context.
 * Branches are optional: if the features.branches toggle is off, this
 * controller refuses to run and the UI hides the module.
 */
final class BranchController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requireFeature('branches');
        $this->requirePermission('settings', 'branch', 'view');

        $company = CompanyContextService::currentCompany();
        $branches = CompanyContextService::branches();

        return $this->view('branches/index', [
            'title'    => 'Branches',
            'company'  => $company,
            'branches' => $branches,
            'currentId' => CompanyContextService::currentBranchId(),
        ])->render();
    }

    public function createForm(): string
    {
        $this->requireFeature('branches');
        $this->requirePermission('settings', 'branch', 'create');
        return $this->view('branches/form', ['title' => 'New Branch', 'branch' => null])->render();
    }

    public function store(): mixed
    {
        $this->requireFeature('branches');
        $this->requirePermission('settings', 'branch', 'create');

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name' => 'required|max:150',
            'code' => 'required|max:20',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();
        $companyId = CompanyContextService::currentCompanyId();

        $dup = Database::row('SELECT id FROM branches WHERE company_id = ? AND code = ? AND deleted_at IS NULL', [$companyId, $d['code']]);
        if ($dup) {
            flash('error', 'A branch with this code already exists.');
            return $this->response->back();
        }

        Database::execute(
            'INSERT INTO branches (company_id, name, code, address, is_head_office, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, 1, ?, ?)',
            [$companyId, $d['name'], $d['code'], $d['address'] ?? '', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        $id = (int) Database::lastInsertId();

        AuditService::log('create', 'settings', 'branch', $id, "Created branch {$d['name']} ({$d['code']})");
        flash('success', 'Branch created.');
        return $this->response->redirect($this->request->url('/branches'));
    }

    public function editForm(int|string $id): string
    {
        $this->requireFeature('branches');
        $this->requirePermission('settings', 'branch', 'edit');

        $branch = Database::row('SELECT * FROM branches WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$branch) {
            flash('error', 'Branch not found.');
            return (string) $this->response->redirect($this->request->url('/branches'))->body();
        }
        return $this->view('branches/form', ['title' => 'Edit Branch', 'branch' => $branch])->render();
    }

    public function update(int|string $id): mixed
    {
        $this->requireFeature('branches');
        $this->requirePermission('settings', 'branch', 'edit');
        $id = (int) $id;

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name' => 'required|max:150',
            'code' => 'required|max:20',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        Database::execute(
            'UPDATE branches SET name = ?, code = ?, address = ?, is_active = ?, updated_at = ? WHERE id = ?',
            [$d['name'], $d['code'], $d['address'] ?? '', ($d['is_active'] ?? '1') === '1' ? 1 : 0, date('Y-m-d H:i:s'), $id]
        );

        AuditService::log('update', 'settings', 'branch', $id, "Updated branch {$d['name']}");
        flash('success', 'Branch updated.');
        return $this->response->redirect($this->request->url('/branches'));
    }

    public function destroy(int|string $id): mixed
    {
        $this->requireFeature('branches');
        $this->requirePermission('settings', 'branch', 'delete');
        $id = (int) $id;

        $branch = Database::row('SELECT * FROM branches WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$branch) {
            flash('error', 'Branch not found.');
            return $this->response->back();
        }
        if ((int) $branch['is_head_office'] === 1) {
            flash('error', 'The Head Office branch cannot be deleted.');
            return $this->response->back();
        }

        Database::execute('UPDATE branches SET deleted_at = ?, is_active = 0, updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id]);
        AuditService::log('delete', 'settings', 'branch', $id, "Deleted branch {$branch['name']}");
        flash('success', 'Branch deleted.');
        return $this->response->redirect($this->request->url('/branches'));
    }

    /** POST /settings/branch/switch — branch switcher in the top bar. */
    public function switchBranch(): mixed
    {
        $branchId = (int) $this->request->input('branch_id');
        if (CompanyContextService::switchBranch($branchId)) {
            $branch = Database::row('SELECT name FROM branches WHERE id = ?', [$branchId]);
            flash('success', 'Branch context: ' . ($branch['name'] ?? ''));
        } else {
            flash('error', 'Could not switch branch.');
        }
        return $this->response->back();
    }
}
