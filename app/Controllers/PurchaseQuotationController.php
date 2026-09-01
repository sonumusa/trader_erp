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
use app\Services\PurchaseDocumentService;
use app\Services\PurchaseService;

/** Purchase quotations. */
final class PurchaseQuotationController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_quotation', 'view');
        $companyId = CompanyContextService::currentCompanyId();
        return $this->view('purchase/quotations/index', [
            'title' => 'Purchase Quotations',
            'data'  => PurchaseDocumentService::paginate('quotation', $companyId, $this->request->page()),
            'canCreate' => PermissionService::can('purchase', 'purchase_quotation', 'create'),
        ])->render();
    }

    public function createForm(): string
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_quotation', 'create');
        \app\Core\Session::forget('_old_input');
        $companyId = CompanyContextService::currentCompanyId();
        return $this->view('purchase/quotations/form', [
            'title'     => 'New Purchase Quotation',
            'suppliers' => Database::query('SELECT id, name, code FROM suppliers WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
            'prefillLines' => [],
        ])->render();
    }

    public function store(): mixed
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_quotation', 'create');
        $companyId = CompanyContextService::currentCompanyId();

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'quotation_date' => 'required|date',
            'supplier_id'    => 'required|integer',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        try {
            $id = PurchaseDocumentService::createQuotation($companyId, [
                'branch_id' => CompanyContextService::currentBranchId(),
                'date'      => $d['quotation_date'],
                'supplier_id' => (int) $d['supplier_id'],
                'narration' => (string) $this->request->input('narration', ''),
                'lines'     => PurchaseService::linesFromInput($this->request->input('items', [])),
            ]);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('create', 'purchase', 'purchase_quotation', $id, 'Created purchase quotation');
        flash('success', 'Purchase quotation saved.');
        return $this->response->redirect($this->request->url('/purchase/quotations'));
    }

    public function cancel(int|string $id): mixed
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_quotation', 'cancel');
        try {
            PurchaseDocumentService::cancel('quotation', (int) $id, (string) $this->request->input('reason', 'Manual cancellation'));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }
        AuditService::log('cancel', 'purchase', 'purchase_quotation', (int) $id, 'Cancelled purchase quotation');
        flash('success', 'Purchase quotation cancelled.');
        return $this->response->redirect($this->request->url('/purchase/quotations'));
    }
}
