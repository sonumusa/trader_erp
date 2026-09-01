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
use app\Services\FinancialYearService;
use app\Services\PermissionService;

/**
 * Company management — list, create, edit, switch.
 * Creating a company also creates its head office, default warehouse,
 * financial-year-ready setup and document number series.
 */
final class CompanyController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requirePermission('settings', 'company', 'view');

        $companies = Database::query(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM branches b WHERE b.company_id = c.id AND b.deleted_at IS NULL) AS branch_count,
                    (SELECT COUNT(*) FROM financial_years f WHERE f.company_id = c.id) AS fy_count
             FROM companies c WHERE c.deleted_at IS NULL ORDER BY c.id'
        );

        return $this->view('companies/index', [
            'title'     => 'Companies',
            'companies' => $companies,
            'currentId' => CompanyContextService::currentCompanyId(),
        ])->render();
    }

    public function createForm(): string
    {
        $this->requirePermission('settings', 'company', 'create');
        return $this->view('companies/form', ['title' => 'New Company', 'company' => null])->render();
    }

    public function store(): mixed
    {
        $this->requirePermission('settings', 'company', 'create');

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name'          => 'required|max:150',
            'code'          => 'max:20',
            'email'         => 'email',
            'currency'      => 'required|max:10',
            'fy_start'      => 'date',
            'fy_end'        => 'date',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            session_set('_old_input', $this->request->all());
            return $this->response->back();
        }
        $d = $v->data();

        if (!empty($d['fy_start']) && !empty($d['fy_end']) && $d['fy_start'] >= $d['fy_end']) {
            flash('error', 'Financial year end must be after its start.');
            return $this->response->back();
        }

        $id = $this->createCompany($d);

        AuditService::log('create', 'settings', 'company', $id, "Created company {$d['name']}");
        flash('success', 'Company created. Head Office, warehouse and document numbering are ready.');
        return $this->response->redirect($this->request->url('/companies'));
    }

    public function editForm(int|string $id): string
    {
        $this->requirePermission('settings', 'company', 'edit');
        $company = Database::row('SELECT * FROM companies WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$company) {
            flash('error', 'Company not found.');
            return (string) $this->response->redirect($this->request->url('/companies'))->body();
        }
        return $this->view('companies/form', ['title' => 'Edit Company', 'company' => $company])->render();
    }

    public function update(int|string $id): mixed
    {
        $this->requirePermission('settings', 'company', 'edit');
        $id = (int) $id;

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

        Database::execute(
            'UPDATE companies
             SET name = ?, code = ?, address = ?, phone = ?, email = ?, currency = ?, updated_at = ?
             WHERE id = ?',
            [$d['name'], $d['code'] ?? '', $d['address'] ?? '', $d['phone'] ?? '', $d['email'] ?? '',
             $d['currency'], date('Y-m-d H:i:s'), $id]
        );

        AuditService::log('update', 'settings', 'company', $id, "Updated company {$d['name']}");
        flash('success', 'Company updated.');
        return $this->response->redirect($this->request->url('/companies'));
    }

    /** POST /settings/company/switch — company switcher in the top bar. */
    public function switch(): mixed
    {
        $companyId = (int) $this->request->input('company_id');
        if (CompanyContextService::switchCompany($companyId)) {
            $company = Database::row('SELECT name FROM companies WHERE id = ?', [$companyId]);
            AuditService::log('switch', 'settings', 'company', $companyId, 'Switched active company to ' . ($company['name'] ?? ''));
            flash('success', 'Switched to ' . ($company['name'] ?? '') . '.');
        } else {
            flash('error', 'Could not switch company.');
        }
        return $this->response->back();
    }

    /** Shared creation logic (used by the form and the setup wizard). */
    public static function createCompany(array $d): int
    {
        $now = date('Y-m-d H:i:s');
        $fyStart = $d['fy_start'] ?? FinancialYearService::defaultsForYear((int) date('Y'))['start'];
        $fyEnd = $d['fy_end'] ?? FinancialYearService::defaultsForYear((int) date('Y'))['end'];

        Database::begin();
        try {
            Database::execute(
                'INSERT INTO companies (name, code, address, phone, email, currency, fiscal_year_start, fiscal_year_end, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$d['name'], $d['code'] ?? '', $d['address'] ?? '', $d['phone'] ?? '', $d['email'] ?? '',
                 $d['currency'] ?? 'PKR', $fyStart, $fyEnd, $now, $now]
            );
            $companyId = (int) Database::lastInsertId();

            // Head office
            Database::execute(
                'INSERT INTO branches (company_id, name, code, is_head_office, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, 1, 1, ?, ?)',
                [$companyId, 'Head Office', 'HO', $now, $now]
            );
            $headOfficeId = (int) Database::lastInsertId();

            // Default warehouse
            Database::execute(
                'INSERT INTO warehouses (company_id, branch_id, name, code, is_default, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, \'active\', ?, ?)',
                [$companyId, $headOfficeId, 'Main Store', 'MS', $now, $now]
            );

            // Default financial year
            Database::execute(
                'INSERT INTO financial_years (company_id, name, start_date, end_date, is_active, is_closed, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, 0, ?, ?)',
                [$companyId, FinancialYearService::suggestName($fyStart, $fyEnd), $fyStart, $fyEnd, $now, $now]
            );

            // Document number series
            DocumentNumberService::createDefaults($companyId);

            // Default Chart of Accounts + Accounting Defaults
            if (!function_exists('seed_default_coa')) { require_once APP_ROOT . '/database/seeders/002_coa.php'; }
            seed_default_coa(Database::pdo(), $companyId);

            // Default payment modes (Cash bound to the cash default)
            if (!function_exists('seed_default_payment_modes')) {
                require_once APP_ROOT . '/database/seeders/003_payment_modes.php';
            }
            seed_default_payment_modes(Database::pdo(), $companyId);

            Database::commit();
            return $companyId;
        } catch (\Throwable $e) {
            Database::rollback();
            throw $e;
        }
    }
}
