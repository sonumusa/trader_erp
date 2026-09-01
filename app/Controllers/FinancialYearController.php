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
use app\Services\FinancialYearService;
use app\Services\PermissionService;

/**
 * Financial year management — list, create, edit, activate, close/reopen.
 */
final class FinancialYearController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requirePermission('settings', 'financial_year', 'view');

        $companyId = CompanyContextService::currentCompanyId();
        $years = Database::query(
            'SELECT * FROM financial_years WHERE company_id = ? ORDER BY start_date DESC',
            [$companyId]
        );
        $current = FinancialYearService::current($companyId);

        return $this->view('financial_years/index', [
            'title'   => 'Financial Years',
            'years'   => $years,
            'current' => $current,
        ])->render();
    }

    public function createForm(): string
    {
        $this->requirePermission('settings', 'financial_year', 'create');
        return $this->view('financial_years/form', ['title' => 'New Financial Year', 'year' => null])->render();
    }

    public function store(): mixed
    {
        $this->requirePermission('settings', 'financial_year', 'create');

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name'       => 'required|max:60',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();
        if ($d['start_date'] >= $d['end_date']) {
            flash('error', 'The financial year end must be after its start.');
            return $this->response->back();
        }

        $companyId = CompanyContextService::currentCompanyId();
        Database::execute(
            'INSERT INTO financial_years (company_id, name, start_date, end_date, is_active, is_closed, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?)',
            [$companyId, $d['name'], $d['start_date'], $d['end_date'],
             !empty($d['is_active']) ? 1 : 0, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        $id = (int) Database::lastInsertId();

        if (!empty($d['is_active'])) {
            FinancialYearService::activate($id, $companyId);
        }

        AuditService::log('create', 'settings', 'financial_year', $id, "Created financial year {$d['name']}");
        flash('success', 'Financial year created.');
        return $this->response->redirect($this->request->url('/settings/financial-years'));
    }

    public function editForm(int|string $id): string
    {
        $this->requirePermission('settings', 'financial_year', 'edit');
        $year = Database::row('SELECT * FROM financial_years WHERE id = ?', [(int) $id]);
        if (!$year) {
            flash('error', 'Financial year not found.');
            return (string) $this->response->redirect($this->request->url('/settings/financial-years'))->body();
        }
        return $this->view('financial_years/form', ['title' => 'Edit Financial Year', 'year' => $year])->render();
    }

    public function update(int|string $id): mixed
    {
        $this->requirePermission('settings', 'financial_year', 'edit');
        $id = (int) $id;

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name'       => 'required|max:60',
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();
        if ($d['start_date'] >= $d['end_date']) {
            flash('error', 'The financial year end must be after its start.');
            return $this->response->back();
        }

        Database::execute(
            'UPDATE financial_years SET name = ?, start_date = ?, end_date = ?, updated_at = ? WHERE id = ?',
            [$d['name'], $d['start_date'], $d['end_date'], date('Y-m-d H:i:s'), $id]
        );
        if (!empty($d['is_active'])) {
            FinancialYearService::activate($id, CompanyContextService::currentCompanyId());
        }

        AuditService::log('update', 'settings', 'financial_year', $id, "Updated financial year {$d['name']}");
        flash('success', 'Financial year updated.');
        return $this->response->redirect($this->request->url('/settings/financial-years'));
    }

    /** POST activate — sets this FY active (and others inactive). */
    public function activate(int|string $id): mixed
    {
        $this->requirePermission('settings', 'financial_year', 'edit');
        $id = (int) $id;
        $year = Database::row('SELECT * FROM financial_years WHERE id = ?', [$id]);
        if (!$year) {
            flash('error', 'Financial year not found.');
            return $this->response->back();
        }
        FinancialYearService::activate($id, (int) $year['company_id']);
        AuditService::log('activate', 'settings', 'financial_year', $id, "Activated financial year {$year['name']}");
        flash('success', "Financial year '{$year['name']}' is now active.");
        return $this->response->redirect($this->request->url('/settings/financial-years'));
    }

    /** POST close / reopen — a permissioned, audited operation. */
    public function toggleClose(int|string $id): mixed
    {
        $this->requirePermission('settings', 'financial_year', 'close');
        $id = (int) $id;
        $year = Database::row('SELECT * FROM financial_years WHERE id = ?', [$id]);
        if (!$year) {
            flash('error', 'Financial year not found.');
            return $this->response->back();
        }

        if ((int) $year['is_closed'] === 1) {
            FinancialYearService::reopen($id);
            AuditService::log('reopen', 'settings', 'financial_year', $id, "Reopened financial year {$year['name']}");
            flash('success', "Financial year '{$year['name']}' reopened.");
        } else {
            FinancialYearService::close($id);
            AuditService::log('close', 'settings', 'financial_year', $id, "Closed financial year {$year['name']}");
            flash('success', "Financial year '{$year['name']}' closed. Transactions in this period are locked by the closing-period rule (Phase 13).");
        }
        return $this->response->redirect($this->request->url('/settings/financial-years'));
    }
}
