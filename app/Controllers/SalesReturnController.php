<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Database;
use app\Core\Request;
use app\Core\Response;
use app\Core\Validator;
use app\Services\AccountingEngine;
use app\Services\AuditService;
use app\Services\CompanyContextService;
use app\Services\FeatureService;
use app\Services\PermissionService;
use app\Services\PurchaseService;
use app\Services\SalesInvoiceService;
use app\Services\SalesReturnService;

/**
 * Sales returns — reverse stock + receivable/cash + COGS + tax;
 * optional reference to the original sales invoice.
 */
final class SalesReturnController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_return', 'view');
        $companyId = CompanyContextService::currentCompanyId();
        return $this->view('sales/returns/index', [
            'title' => 'Sales Returns',
            'data'  => SalesReturnService::paginate($companyId, $this->request->page()),
            'canCreate' => PermissionService::can('sales', 'sales_return', 'create'),
        ])->render();
    }

    public function createForm(): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_return', 'create');
        \app\Core\Session::forget('_old_input');
        $companyId = CompanyContextService::currentCompanyId();

        $prefillLines = [];
        $referenceId = (int) $this->request->query('from_invoice', 0);
        $reference = null;
        if ($referenceId > 0) {
            $reference = SalesInvoiceService::find($referenceId);
            if ($reference && (int) $reference['company_id'] === $companyId && $reference['status'] === 'posted') {
                foreach (PurchaseService::linesFromDocument('sales_invoice_items', $referenceId) as $l) {
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

        return $this->view('sales/returns/form', [
            'title'        => 'New Sales Return',
            'customers'    => Database::query('SELECT id, name, code FROM customers WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
            'warehouses'   => Database::query('SELECT id, name FROM warehouses WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
            'invoices'     => Database::query('SELECT id, invoice_no, invoice_date, total FROM sales_invoices WHERE company_id = ? AND status = \'posted\' ORDER BY id DESC LIMIT 50', [$companyId]),
            'prefillLines' => $prefillLines,
            'reference'    => $reference,
        ])->render();
    }

    public function store(): mixed
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_return', 'create');
        $companyId = CompanyContextService::currentCompanyId();

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'return_date'  => 'required|date',
            'warehouse_id' => 'required|integer',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        try {
            $id = SalesReturnService::create($companyId, [
                'branch_id'            => CompanyContextService::currentBranchId(),
                'return_date'          => $d['return_date'],
                'customer_id'          => (int) ($this->request->input('customer_id') ?? 0),
                'warehouse_id'         => (int) $d['warehouse_id'],
                'reference_invoice_id' => (int) ($this->request->input('reference_invoice_id') ?? 0) ?: null,
                'narration'            => (string) $this->request->input('narration', ''),
                'lines'                => PurchaseService::linesFromInput($this->request->input('items', [])),
            ]);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('create', 'sales', 'sales_return', $id, 'Created sales return');
        flash('success', 'Sales return posted — stock, COGS and accounts reversed.');
        return $this->response->redirect($this->request->url('/sales/returns'));
    }

    public function show(int|string $id): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_return', 'view');
        $return = SalesReturnService::find((int) $id);
        if (!$return || (int) $return['company_id'] !== (int) CompanyContextService::currentCompanyId()) {
            flash('error', 'Return not found.');
            return (string) $this->response->redirect($this->request->url('/sales/returns'))->body();
        }

        return $this->view('sales/returns/view', [
            'title'    => 'Sales Return — ' . $return['return_no'],
            'return'   => $return,
            'canCancel' => PermissionService::can('sales', 'sales_return', 'cancel'),
            'canPrint'  => PermissionService::can('sales', 'sales_return', 'print'),
        ])->render();
    }

    public function cancel(int|string $id): mixed
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_return', 'cancel');
        $id = (int) $id;

        try {
            SalesReturnService::cancel($id, (string) $this->request->input('reason', 'Manual cancellation'));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('cancel', 'sales', 'sales_return', $id, 'Cancelled sales return');
        flash('success', 'Return cancelled and reversed.');
        return $this->response->redirect($this->request->url('/sales/returns/' . $id));
    }

    public function print(int|string $id): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_return', 'print');
        $return = SalesReturnService::find((int) $id);
        if (!$return) {
            flash('error', 'Return not found.');
            return (string) $this->response->redirect($this->request->url('/sales/returns'))->body();
        }
        return $this->view('sales/returns/print', [
            'doc'     => $return,
            'title'   => 'Sales Return ' . $return['return_no'],
            'company' => CompanyContextService::currentCompany(),
        ])->layout('print')->render();
    }

    /** View the generated journal entries for a return. */
    public function journal(int|string $id): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_return', 'view');
        $return = SalesReturnService::find((int) $id);
        if (!$return) {
            flash('error', 'Return not found.');
            return (string) $this->response->redirect($this->request->url('/sales/returns'))->body();
        }
        $entries = Database::query(
            'SELECT id FROM journal_entries WHERE source_document_type = \'sales_return\' AND source_document_id = ? ORDER BY id',
            [(int) $id]
        );
        $rows = [];
        foreach ($entries as $e) {
            $entry = AccountingEngine::entry((int) $e['id']);
            if ($entry) {
                $rows[] = $entry;
            }
        }
        return $this->view('accounting/journal', [
            'title'   => 'Accounting — ' . $return['return_no'],
            'entries' => $rows,
            'back'    => $this->request->url('/sales/returns/' . (int) $id),
        ])->render();
    }
}
