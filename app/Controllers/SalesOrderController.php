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

/** Sales orders (workflow: quotation → order → invoice; or direct). */
final class SalesOrderController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_order', 'view');
        $companyId = CompanyContextService::currentCompanyId();
        return $this->view('sales/orders/index', [
            'title' => 'Sales Orders',
            'data'  => SalesDocumentService::paginate('order', $companyId, $this->request->page()),
            'canCreate' => PermissionService::can('sales', 'sales_order', 'create'),
        ])->render();
    }

    public function createForm(): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_order', 'create');
        \app\Core\Session::forget('_old_input');
        $companyId = CompanyContextService::currentCompanyId();

        $prefillLines = [];
        $fromQuotation = (int) $this->request->query('from_quotation', 0);
        $reference = null;
        if ($fromQuotation > 0) {
            $reference = Database::row('SELECT * FROM sales_quotations WHERE id = ? AND company_id = ? AND status = \'posted\'', [$fromQuotation, $companyId]);
            if ($reference) {
                foreach (PurchaseService::linesFromDocument('sales_quotation_items', $fromQuotation) as $l) {
                    $item = Database::row('SELECT * FROM items WHERE id = ?', [(int) $l['item_id']]);
                    $calc = PurchaseService::calculateLine([
                        'item_id'  => (int) $l['item_id'],
                        'uom_id'   => (int) ($l['uom_id'] ?? 0),
                        'quantity' => (float) $l['quantity'],
                        'rate'     => (float) $l['rate'],
                        'discount' => (float) $l['discount'],
                    ], $item);
                    $calc['prefill_item_name'] = $item['name'] ?? '';
                    $calc['prefill_item_code'] = $item['item_code'] ?? '';
                    $calc['prefill_uom_code'] = $l['uom_id'] ? (string) Database::value('SELECT code FROM uoms WHERE id = ?', [(int) $l['uom_id']]) : '';
                    $prefillLines[] = $calc;
                }
            }
        }

        return $this->view('sales/orders/form', [
            'title'       => 'New Sales Order',
            'customers'   => Database::query('SELECT id, name, code FROM customers WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
            'quotations'  => Database::query('SELECT id, quotation_no, quotation_date FROM sales_quotations WHERE company_id = ? AND status = \'posted\' ORDER BY id DESC LIMIT 50', [$companyId]),
            'prefillLines' => $prefillLines,
            'reference'   => $reference,
        ])->render();
    }

    public function store(): mixed
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_order', 'create');
        $companyId = CompanyContextService::currentCompanyId();

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'order_date'  => 'required|date',
            'customer_id' => 'required|integer',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        try {
            $id = SalesDocumentService::createOrder($companyId, [
                'branch_id' => CompanyContextService::currentBranchId(),
                'date'      => $d['order_date'],
                'customer_id' => (int) $d['customer_id'],
                'reference_quotation_id' => (int) ($this->request->input('reference_quotation_id') ?? 0) ?: null,
                'narration' => (string) $this->request->input('narration', ''),
                'lines'     => PurchaseService::linesFromInput($this->request->input('items', [])),
            ]);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('create', 'sales', 'sales_order', $id, 'Created sales order');
        flash('success', 'Sales order saved.');
        return $this->response->redirect($this->request->url('/sales/orders'));
    }

    public function cancel(int|string $id): mixed
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_order', 'cancel');
        try {
            SalesDocumentService::cancel('order', (int) $id, (string) $this->request->input('reason', 'Manual cancellation'));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }
        AuditService::log('cancel', 'sales', 'sales_order', (int) $id, 'Cancelled sales order');
        flash('success', 'Sales order cancelled.');
        return $this->response->redirect($this->request->url('/sales/orders'));
    }
}
