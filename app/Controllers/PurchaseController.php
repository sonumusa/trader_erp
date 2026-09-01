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
use app\Services\PaymentModeService;
use app\Services\PermissionService;
use app\Services\PurchaseInvoiceService;
use app\Services\PurchaseService;

/**
 * Purchase invoices — direct or from a purchase order.
 * Saving posts stock + accounting + (optional) payment immediately.
 */
final class PurchaseController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_invoice', 'view');

        $companyId = CompanyContextService::currentCompanyId();
        $data = PurchaseInvoiceService::paginate(
            $companyId,
            $this->request->page(),
            20,
            (string) $this->request->query('q', ''),
            (string) $this->request->query('from', '') ?: null,
            (string) $this->request->query('to', '') ?: null
        );

        return $this->view('purchase/invoices/index', [
            'title' => 'Purchase Invoices',
            'data'  => $data,
            'q'     => (string) $this->request->query('q', ''),
            'from'  => (string) $this->request->query('from', ''),
            'to'    => (string) $this->request->query('to', ''),
            'canCreate' => PermissionService::can('purchase', 'purchase_invoice', 'create'),
            'canCancel' => PermissionService::can('purchase', 'purchase_invoice', 'cancel'),
        ])->render();
    }

    public function createForm(): string
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_invoice', 'create');
        \app\Core\Session::forget('_old_input');

        $companyId = CompanyContextService::currentCompanyId();

        // Optional prefill from a purchase order
        $prefillLines = [];
        $fromOrder = (int) $this->request->query('from_order', 0);
        if ($fromOrder > 0) {
            $order = Database::row('SELECT * FROM purchase_orders WHERE id = ? AND company_id = ? AND status = \'posted\'', [$fromOrder, $companyId]);
            if ($order) {
                foreach (PurchaseService::linesFromDocument('purchase_order_items', $fromOrder) as $l) {
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

        return $this->view('purchase/invoices/form', [
            'title'         => 'New Purchase Invoice',
            'suppliers'     => Database::query('SELECT id, name, code FROM suppliers WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
            'warehouses'    => Database::query('SELECT id, name FROM warehouses WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
            'paymentModes'  => PaymentModeService::all($companyId),
            'prefillLines'  => $prefillLines,
            'fromOrder'     => $fromOrder,
        ])->render();
    }

    public function store(): mixed
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_invoice', 'create');
        $companyId = CompanyContextService::currentCompanyId();

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'invoice_date'   => 'required|date',
            'supplier_id'    => 'required|integer',
            'warehouse_id'   => 'required|integer',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        try {
            $id = PurchaseInvoiceService::create($companyId, [
                'branch_id'          => CompanyContextService::currentBranchId(),
                'invoice_date'       => $d['invoice_date'],
                'supplier_id'        => (int) $d['supplier_id'],
                'warehouse_id'       => (int) $d['warehouse_id'],
                'reference_order_id' => (int) ($this->request->input('reference_order_id') ?? 0) ?: null,
                'payment_mode_id'    => (int) ($this->request->input('payment_mode_id') ?? 0),
                'paid_amount'        => (float) ($this->request->input('paid_amount') ?? 0),
                'narration'          => (string) $this->request->input('narration', ''),
                'lines'              => PurchaseService::linesFromInput($this->request->input('items', [])),
            ]);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('create', 'purchase', 'purchase_invoice', $id, 'Created purchase invoice');
        flash('success', 'Purchase invoice posted — stock and accounts updated.');
        return $this->response->redirect($this->request->url('/purchase/invoices/' . $id));
    }

    public function show(int|string $id): string
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_invoice', 'view');
        $invoice = PurchaseInvoiceService::find((int) $id);
        if (!$invoice || (int) $invoice['company_id'] !== (int) CompanyContextService::currentCompanyId()) {
            flash('error', 'Invoice not found.');
            return (string) $this->response->redirect($this->request->url('/purchase/invoices'))->body();
        }

        $journalEntry = Database::row(
            'SELECT id, entry_no FROM journal_entries WHERE source_document_type = \'purchase_invoice\' AND source_document_id = ? AND status = \'posted\' ORDER BY id LIMIT 1',
            [(int) $id]
        );

        return $this->view('purchase/invoices/view', [
            'title'        => 'Purchase Invoice — ' . $invoice['invoice_no'],
            'invoice'      => $invoice,
            'journalEntry' => $journalEntry,
            'canCancel'    => PermissionService::can('purchase', 'purchase_invoice', 'cancel'),
            'canPrint'     => PermissionService::can('purchase', 'purchase_invoice', 'print'),
        ])->render();
    }

    public function cancel(int|string $id): mixed
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_invoice', 'cancel');
        $id = (int) $id;

        try {
            PurchaseInvoiceService::cancel($id, (string) $this->request->input('reason', 'Manual cancellation'));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('cancel', 'purchase', 'purchase_invoice', $id, 'Cancelled purchase invoice');
        flash('success', 'Invoice cancelled — stock and accounts reversed.');
        return $this->response->redirect($this->request->url('/purchase/invoices/' . $id));
    }

    public function print(int|string $id): string
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_invoice', 'print');
        $invoice = PurchaseInvoiceService::find((int) $id);
        if (!$invoice || (int) $invoice['company_id'] !== (int) CompanyContextService::currentCompanyId()) {
            flash('error', 'Invoice not found.');
            return (string) $this->response->redirect($this->request->url('/purchase/invoices'))->body();
        }
        return $this->view('purchase/print', [
            'doc'      => $invoice,
            'type'     => 'purchase_invoice',
            'title'    => 'Purchase Invoice ' . $invoice['invoice_no'],
            'company'  => CompanyContextService::currentCompany(),
        ])->layout('print')->render();
    }

    /** View the generated journal entry for an invoice. */
    public function journal(int|string $id): string
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_invoice', 'view');
        $invoice = PurchaseInvoiceService::find((int) $id);
        if (!$invoice) {
            flash('error', 'Invoice not found.');
            return (string) $this->response->redirect($this->request->url('/purchase/invoices'))->body();
        }
        $entries = Database::query(
            'SELECT id FROM journal_entries WHERE source_document_type = \'purchase_invoice\' AND source_document_id = ? ORDER BY id',
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
            'title'   => 'Accounting — ' . $invoice['invoice_no'],
            'entries' => $rows,
            'back'    => $this->request->url('/purchase/invoices/' . (int) $id),
        ])->render();
    }
}
