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
use app\Services\DocumentNumberService;
use app\Services\FeatureService;
use app\Services\FinancialYearService;
use app\Services\PermissionService;

/**
 * Setup wizard — guides a non-technical user through configuring the system
 * after installation. Steps that belong to modules not yet built are shown
 * honestly as "arrives with Phase X" instead of faking functionality.
 */
final class SetupController extends Controller
{
    /** Step definitions: key, label, phase (null = live now). */
    public const STEPS = [
        ['company',        'Company',            null],
        ['financial_year', 'Financial Year',     null],
        ['branches',       'Branches',           null],
        ['coa',            'Chart of Accounts',  null],
        ['default_accounts','Default Accounts',  null],
        ['warehouses',     'Warehouses',         null],
        ['uom',            'UOM',                null],
        ['tax',            'Tax',                null],
        ['users_roles',    'Users & Roles',      null],
        ['opening_balances','Opening Balances',  'Phase 6'],
        ['opening_stock',  'Opening Stock',      null],
        ['features',       'Feature Settings',   null],
        ['finish',         'Finish',             null],
    ];

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requirePermission('settings', 'setup', 'view');

        return $this->view('setup/index', [
            'title'   => 'Setup Wizard',
            'steps'   => self::STEPS,
            'state'   => $this->state(),
        ])->render();
    }

    /** POST /setup/step/{n} — handle the submitted step. */
    public function step(int|string $n): mixed
    {
        $this->requirePermission('settings', 'setup', 'run');
        $n = (int) $n;
        $key = self::STEPS[$n - 1][0] ?? null;
        if ($key === null) {
            flash('error', 'Unknown wizard step.');
            return $this->response->redirect($this->request->url('/setup'));
        }

        switch ($key) {
            case 'company':
                return $this->saveCompany();
            case 'financial_year':
                return $this->saveFinancialYear();
            case 'branches':
                return $this->saveBranch();
            case 'coa':
                return $this->setupCoa();
            case 'default_accounts':
                return $this->setupDefaults();
            case 'warehouses':
                return $this->saveWarehouse();
            case 'uom':
                return $this->saveUom();
            case 'tax':
                return $this->saveTax();
            case 'opening_stock':
                flash('success', 'Use the Opening Stock page to enter opening quantities and rates — it posts stock AND the opening accounting entry.');
                return $this->response->redirect($this->request->url('/inventory/opening-stock'));
            case 'finish':
                flash('success', 'Setup complete. TradeERP is ready to use.');
                return $this->response->redirect($this->request->url('/'));
            default:
                flash('warning', 'That step becomes available in ' . (self::STEPS[$n - 1][1] ?? 'a later phase') . '.');
                return $this->response->redirect($this->request->url('/setup'));
        }
    }

    /* ------------------------------------------------------------------ */

    private function saveCompany(): mixed
    {
        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name'     => 'required|max:150',
            'code'     => 'max:20',
            'email'    => 'email',
            'currency' => 'required|max:10',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();
        $company = CompanyContextService::currentCompany();

        if ($company) {
            Database::execute(
                'UPDATE companies SET name = ?, code = ?, address = ?, phone = ?, email = ?, currency = ?, updated_at = ? WHERE id = ?',
                [$d['name'], $d['code'] ?? '', $d['address'] ?? '', $d['phone'] ?? '', $d['email'] ?? '', $d['currency'], date('Y-m-d H:i:s'), $company['id']]
            );
            AuditService::log('update', 'settings', 'company', (int) $company['id'], 'Setup wizard: updated company');
        } else {
            $id = CompanyController::createCompany($d);
            AuditService::log('create', 'settings', 'company', $id, 'Setup wizard: created company');
        }

        flash('success', 'Company details saved.');
        return $this->response->redirect($this->request->url('/setup'));
    }

    private function saveFinancialYear(): mixed
    {
        $v = new Validator();
        if (!$v->validate($this->request->all(), [
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
        $existing = FinancialYearService::current($companyId);

        if ($existing && (int) $existing['is_active'] === 1) {
            Database::execute(
                'UPDATE financial_years SET start_date = ?, end_date = ?, updated_at = ? WHERE id = ?',
                [$d['start_date'], $d['end_date'], date('Y-m-d H:i:s'), (int) $existing['id']]
            );
        } else {
            $name = FinancialYearService::suggestName($d['start_date'], $d['end_date']);
            Database::execute(
                'INSERT INTO financial_years (company_id, name, start_date, end_date, is_active, is_closed, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, 0, ?, ?)',
                [$companyId, $name, $d['start_date'], $d['end_date'], date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
            );
        }

        AuditService::log('update', 'settings', 'financial_year', (int) ($existing['id'] ?? 0), 'Setup wizard: configured financial year');
        flash('success', 'Financial year saved.');
        return $this->response->redirect($this->request->url('/setup'));
    }

    private function saveBranch(): mixed
    {
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

        Database::execute(
            'INSERT INTO branches (company_id, name, code, address, is_head_office, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, 1, ?, ?)',
            [$companyId, $d['name'], $d['code'], $d['address'] ?? '', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        AuditService::log('create', 'settings', 'branch', (int) Database::lastInsertId(), 'Setup wizard: added branch');
        flash('success', 'Branch added.');
        return $this->response->redirect($this->request->url('/setup'));
    }

    private function saveWarehouse(): mixed
    {
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

        Database::execute(
            'INSERT INTO warehouses (company_id, branch_id, name, code, address, is_default, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 0, \'active\', ?, ?)',
            [$companyId, CompanyContextService::currentBranchId(), $d['name'], $d['code'], $d['address'] ?? '', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        AuditService::log('create', 'inventory', 'warehouse', (int) Database::lastInsertId(), 'Setup wizard: added warehouse');
        flash('success', 'Warehouse added.');
        return $this->response->redirect($this->request->url('/setup'));
    }

    private function saveTax(): mixed
    {
        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name' => 'required|max:80',
            'rate' => 'required|numeric|min:0|max:100',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();
        Database::execute(
            'INSERT INTO taxes (company_id, name, rate, is_system, is_active, created_at, updated_at)
             VALUES (?, ?, ?, 0, 1, ?, ?)',
            [CompanyContextService::currentCompanyId(), $d['name'], (float) $d['rate'], date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        AuditService::log('create', 'settings', 'tax', (int) Database::lastInsertId(), 'Setup wizard: added tax');
        flash('success', 'Tax added.');
        return $this->response->redirect($this->request->url('/setup'));
    }

    private function setupCoa(): mixed
    {
        $companyId = CompanyContextService::currentCompanyId();
        $existing = (int) Database::value(
            'SELECT COUNT(*) FROM accounts WHERE company_id = ? AND deleted_at IS NULL',
            [$companyId]
        );
        if ($existing > 0) {
            flash('success', 'The default Chart of Accounts is already in place (' . $existing . ' accounts). You can adjust it under Chart of Accounts.');
        } else {
            if (!function_exists('seed_default_coa')) { require_once APP_ROOT . '/database/seeders/002_coa.php'; }
            $r = seed_default_coa(Database::pdo(), $companyId);
            AuditService::log('create', 'accounting', 'account', null, 'Setup wizard: seeded default COA');
            flash('success', 'Default Chart of Accounts created (' . $r['accounts'] . ' accounts, ' . $r['groups'] . ' groups).');
        }
        return $this->response->redirect($this->request->url('/setup'));
    }

    private function setupDefaults(): mixed
    {
        $companyId = CompanyContextService::currentCompanyId();
        $count = \app\Services\AccountDefaultService::count($companyId);
        flash('success', 'Accounting Defaults are configured (' . $count . ' mappings). Review them under Chart of Accounts → Accounting Defaults.');
        return $this->response->redirect($this->request->url('/accounts/defaults'));
    }

    private function saveUom(): mixed
    {
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
            'INSERT INTO uoms (name, code, created_at, updated_at) VALUES (?, ?, ?, ?)',
            [$d['name'], strtoupper($d['code']), date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        AuditService::log('create', 'inventory', 'uom', (int) Database::lastInsertId(), 'Setup wizard: added UOM');
        flash('success', 'UOM added.');
        return $this->response->redirect($this->request->url('/setup'));
    }

    /** Compute the wizard progress state (what is done vs pending). */
    private function state(): array
    {
        $companyId = CompanyContextService::currentCompanyId();

        $companyDone = (bool) Database::value('SELECT id FROM companies WHERE id = ? AND deleted_at IS NULL', [$companyId]);
        $fyDone = (bool) Database::value('SELECT id FROM financial_years WHERE company_id = ? LIMIT 1', [$companyId]);
        $branchDone = (int) Database::value('SELECT COUNT(*) FROM branches WHERE company_id = ? AND deleted_at IS NULL', [$companyId]) > 0;
        $warehouseDone = (int) Database::value('SELECT COUNT(*) FROM warehouses WHERE company_id = ? AND deleted_at IS NULL', [$companyId]) > 0;
        $uomDone = (int) Database::value('SELECT COUNT(*) FROM uoms WHERE deleted_at IS NULL') > 0;
        $usersDone = (int) Database::value('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL') > 1;

        return [
            'company'         => $companyDone,
            'financial_year'  => $fyDone,
            'branches'        => $branchDone,
            'coa'             => (int) Database::value('SELECT COUNT(*) FROM accounts WHERE company_id = ? AND deleted_at IS NULL', [$companyId]) > 0,
            'default_accounts'=> (int) \app\Services\AccountDefaultService::count($companyId) > 0,
            'warehouses'      => $warehouseDone,
            'uom'             => $uomDone,
            'tax'             => (int) Database::value('SELECT COUNT(*) FROM taxes WHERE company_id = ? AND deleted_at IS NULL', [$companyId]) > 0,
            'users_roles'     => $usersDone,
            'opening_balances'=> false,
            'opening_stock'   => false,
            'features'        => true,
            'finish'          => $companyDone && $fyDone && $warehouseDone && $uomDone && $usersDone,
        ];
    }
}
