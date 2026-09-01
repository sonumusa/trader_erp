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
use app\Services\PurchaseService;
use app\Services\SalesDocumentService;

/** Sales quotations. */
final class SalesQuotationController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_quotation', 'view');
        $companyId = CompanyContextService::currentCompanyId();
        return $this->view('sales/quotations/index', [
            'title' => 'Sales Quotations',
            'data'  => SalesDocumentService::paginate('quotation', $companyId, $this->request->page()),
            'canCreate' => PermissionService::can('sales', 'sales_quotation', 'create'),
        ])->render();
    }

    public function createForm(): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_quotation', 'create');
        \app\Core\Session::forget('_old_input');
        $companyId = CompanyContextService::currentCompanyId();
        return $this->view('sales/quotations/form', [
            'title'     => 'New Sales Quotation',
            'customers' => Database::query('SELECT id, name, code FROM customers WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
            'prefillLines' => [],
        ])->render();
    }

    public function store(): mixed
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_quotation', 'create');
        $companyId = CompanyContextService::currentCompanyId();

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'quotation_date' => 'required|date',
            'customer_id'    => 'required|integer',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        try {
            $id = SalesDocumentService::createQuotation($companyId, [
                'branch_id' => CompanyContextService::currentBranchId(),
                'date'      => $d['quotation_date'],
                'customer_id' => (int) $d['customer_id'],
                'narration' => (string) $this->request->input('narration', ''),
                'lines'     => PurchaseService::linesFromInput($this->request->input('items', [])),
            ]);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('create', 'sales', 'sales_quotation', $id, 'Created sales quotation');
        flash('success', 'Sales quotation saved.');
        return $this->response->redirect($this->request->url('/sales/quotations'));
    }

    public function cancel(int|string $id): mixed
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_quotation', 'cancel');
        try {
            SalesDocumentService::cancel('quotation', (int) $id, (string) $this->request->input('reason', 'Manual cancellation'));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }
        AuditService::log('cancel', 'sales', 'sales_quotation', (int) $id, 'Cancelled sales quotation');
        flash('success', 'Sales quotation cancelled.');
        return $this->response->redirect($this->request->url('/sales/quotations'));
    }
}
