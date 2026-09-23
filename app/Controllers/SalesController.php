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
use app\Services\PurchaseService;
use app\Services\SalesInvoiceService;
use app\Services\UploadService;

/**
 * Sales invoices — direct or from a sales order.
 * Saving issues stock (COGS) + posts accounting + optional receipt immediately.
 */
final class SalesController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_invoice', 'view');

        $companyId = CompanyContextService::currentCompanyId();
        $data = SalesInvoiceService::paginate(
            $companyId,
            $this->request->page(),
            20,
            (string) $this->request->query('q', ''),
            (string) $this->request->query('from', '') ?: null,
            (string) $this->request->query('to', '') ?: null
        );

        return $this->view('sales/invoices/index', [
            'title' => 'Sales Invoices',
            'data'  => $data,
            'q'     => (string) $this->request->query('q', ''),
            'from'  => (string) $this->request->query('from', ''),
            'to'    => (string) $this->request->query('to', ''),
            'canCreate' => PermissionService::can('sales', 'sales_invoice', 'create'),
            'canEdit' => PermissionService::can('sales', 'sales_invoice', 'edit'),
            'canCancel' => PermissionService::can('sales', 'sales_invoice', 'cancel'),
        ])->render();
    }

    public function createForm(): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_invoice', 'create');
        \app\Core\Session::forget('_old_input');

        $companyId = CompanyContextService::currentCompanyId();

        $prefillLines = [];
        $fromOrder = (int) $this->request->query('from_order', 0);
        if ($fromOrder > 0) {
            $order = Database::row('SELECT * FROM sales_orders WHERE id = ? AND company_id = ? AND status = \'posted\'', [$fromOrder, $companyId]);
            if ($order) {
                foreach (PurchaseService::linesFromDocument('sales_order_items', $fromOrder) as $l) {
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

        return $this->view('sales/invoices/form', [
            'title'        => 'New Sales Invoice',
            'customers'    => Database::query('SELECT id, name, code FROM customers WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
            'warehouses'   => Database::query('SELECT id, name FROM warehouses WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
            'paymentModes' => PaymentModeService::all($companyId),
            'prefillLines' => $prefillLines,
            'fromOrder'    => $fromOrder,
        ])->render();
    }

    public function editForm(int|string $id): string
    {
        $this->requireFeature('sales'); $this->requirePermission('sales', 'sales_invoice', 'edit');
        $invoice = SalesInvoiceService::find((int) $id);
        if (!$invoice || $invoice['status'] !== 'posted' || (int) $invoice['company_id'] !== (int) CompanyContextService::currentCompanyId()) { flash('error', 'Invoice not found or unavailable.'); return (string) $this->response->redirect($this->request->url('/sales/invoices'))->body(); }
        $lines = []; foreach ($invoice['items'] as $line) { $line['prefill_item_code'] = $line['item_code']; $line['prefill_item_name'] = $line['item_name']; $line['prefill_uom_code'] = $line['uom_code'] ?? ''; $lines[] = $line; }
        $companyId = (int) $invoice['company_id'];
        return $this->view('sales/invoices/form', ['title' => 'Edit Sales Invoice', 'invoice' => $invoice, 'isEdit' => true, 'customers' => Database::query('SELECT id, name, code FROM customers WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]), 'warehouses' => Database::query('SELECT id, name FROM warehouses WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]), 'paymentModes' => PaymentModeService::all($companyId), 'prefillLines' => $lines, 'fromOrder' => (int) ($invoice['reference_order_id'] ?? 0)])->render();
    }

    public function store(): mixed
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_invoice', 'create');
        $companyId = CompanyContextService::currentCompanyId();

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'invoice_date' => 'required|date',
            'warehouse_id' => 'required|integer',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        try {
            $id = SalesInvoiceService::create($companyId, [
                'branch_id'          => CompanyContextService::currentBranchId(),
                'invoice_date'       => $d['invoice_date'],
                'customer_id'        => (int) ($this->request->input('customer_id') ?? 0),
                'warehouse_id'       => (int) $d['warehouse_id'],
                'reference_order_id' => (int) ($this->request->input('reference_order_id') ?? 0) ?: null,
                'payment_mode_id'    => (int) ($this->request->input('payment_mode_id') ?? 0),
                'received_amount'    => (float) ($this->request->input('received_amount') ?? 0),
                'narration'          => (string) $this->request->input('narration', ''),
                'lines'              => PurchaseService::linesFromInput($this->request->input('items', [])),
            ]);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('create', 'sales', 'sales_invoice', $id, 'Created sales invoice');
        flash('success', 'Sales invoice posted — stock, COGS and accounts updated.');
        return $this->response->redirect($this->request->url('/sales/invoices/' . $id));
    }

    public function show(int|string $id): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_invoice', 'view');
        $invoice = SalesInvoiceService::find((int) $id);
        if (!$invoice || (int) $invoice['company_id'] !== (int) CompanyContextService::currentCompanyId()) {
            flash('error', 'Invoice not found.');
            return (string) $this->response->redirect($this->request->url('/sales/invoices'))->body();
        }

        return $this->view('sales/invoices/view', [
            'title'   => 'Sales Invoice — ' . $invoice['invoice_no'],
            'invoice' => $invoice,
            'canCancel' => PermissionService::can('sales', 'sales_invoice', 'cancel'),
            'canPrint'  => PermissionService::can('sales', 'sales_invoice', 'print'),
            'canEdit'   => PermissionService::can('sales', 'sales_invoice', 'edit'),
            'canEdit'   => PermissionService::can('sales', 'sales_invoice', 'edit'),
            'attachments' => \app\Services\UploadService::forDocument((int) $invoice['company_id'], 'sales_invoice', (int) $id),
        ])->render();
    }

    public function cancel(int|string $id): mixed
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_invoice', 'cancel');
        $id = (int) $id;

        try {
            SalesInvoiceService::cancel($id, (string) $this->request->input('reason', 'Manual cancellation'));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('cancel', 'sales', 'sales_invoice', $id, 'Cancelled sales invoice');
        flash('success', 'Invoice cancelled — stock and accounts reversed.');
        return $this->response->redirect($this->request->url('/sales/invoices/' . $id));
    }

    public function update(int|string $id): mixed
    {
        $this->requireFeature('sales'); $this->requirePermission('sales', 'sales_invoice', 'edit');
        $old = SalesInvoiceService::find((int) $id);
        if (!$old || $old['status'] !== 'posted') { flash('error', 'Invoice cannot be edited.'); return $this->response->back(); }
        try {
            SalesInvoiceService::cancel((int) $id, 'Replaced by edited invoice');
            $newId = SalesInvoiceService::create((int) $old['company_id'], ['branch_id' => $old['branch_id'], 'invoice_date' => (string) $this->request->input('invoice_date'), 'customer_id' => (int) $this->request->input('customer_id'), 'warehouse_id' => (int) $this->request->input('warehouse_id'), 'reference_order_id' => (int) $this->request->input('reference_order_id') ?: null, 'payment_mode_id' => (int) $this->request->input('payment_mode_id'), 'received_amount' => (float) $this->request->input('received_amount'), 'narration' => (string) $this->request->input('narration', ''), 'lines' => PurchaseService::linesFromInput($this->request->input('items', []))]);
        } catch (\RuntimeException $e) { flash('error', $e->getMessage()); return $this->response->back(); }
        AuditService::log('update', 'sales', 'sales_invoice', $newId, 'Replaced edited sales invoice #' . $id); flash('success', 'Sales invoice updated safely.'); return $this->response->redirect($this->request->url('/sales/invoices/' . $newId));
    }

    public function print(int|string $id): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_invoice', 'print');
        $invoice = SalesInvoiceService::find((int) $id);
        if (!$invoice || (int) $invoice['company_id'] !== (int) CompanyContextService::currentCompanyId()) {
            flash('error', 'Invoice not found.');
            return (string) $this->response->redirect($this->request->url('/sales/invoices'))->body();
        }
        return $this->view('sales/print', [
            'doc'     => $invoice,
            'type'    => 'sales_invoice',
            'title'   => 'Sales Invoice ' . $invoice['invoice_no'],
            'company' => CompanyContextService::currentCompany(),
        ])->layout('print')->render();
    }

    /* ---------------- Attachments ---------------- */

    /** POST upload an attachment to a sales invoice. */
    public function attach(int|string $id): mixed
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_invoice', 'edit');
        $id = (int) $id;

        $invoice = SalesInvoiceService::find($id);
        if (!$invoice || (int) $invoice['company_id'] !== (int) CompanyContextService::currentCompanyId()) {
            flash('error', 'Invoice not found.');
            return $this->response->back();
        }

        $file = $this->request->files('attachment');
        if (!$file || !is_array($file)) {
            flash('error', 'No file was uploaded.');
            return $this->response->back();
        }

        try {
            $result = UploadService::store(
                $file,
                (string) $this->request->input('category', 'document'),
                (int) $invoice['company_id'],
                'sales_invoice',
                $id
            );
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('upload', 'sales', 'sales_invoice', $id, 'Attached file: ' . $result['original_name']);
        flash('success', 'Attachment saved: ' . $result['original_name']);
        return $this->response->redirect($this->request->url('/sales/invoices/' . $id));
    }

    /** Download an attachment (auth + permission enforced). */
    public function download(int|string $id, int|string $attachmentId): mixed
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_invoice', 'view');
        $id = (int) $id;

        $invoice = SalesInvoiceService::find($id);
        if (!$invoice) {
            flash('error', 'Invoice not found.');
            return $this->response->back();
        }
        $att = UploadService::find((int) $invoice['company_id'], (int) $attachmentId);
        if (!$att || $att['document_type'] !== 'sales_invoice' || (int) $att['document_id'] !== $id) {
            flash('error', 'Attachment not found.');
            return $this->response->back();
        }

        try {
            $file = UploadService::stream($att);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        return $this->response
            ->header('Content-Type', $file['mime'])
            ->header('Content-Length', (string) $file['size'])
            ->download($file['path'], $file['download_name']);
    }

    public function deleteAttachment(int|string $id, int|string $attachmentId): mixed
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_invoice', 'edit');
        $id = (int) $id;

        $invoice = SalesInvoiceService::find($id);
        if (!$invoice) {
            flash('error', 'Invoice not found.');
            return $this->response->back();
        }
        UploadService::delete((int) $invoice['company_id'], (int) $attachmentId);
        AuditService::log('delete', 'sales', 'sales_invoice', $id, 'Removed attachment #' . (int) $attachmentId);
        flash('success', 'Attachment removed.');
        return $this->response->redirect($this->request->url('/sales/invoices/' . $id));
    }

    /** View the generated journal entries for an invoice. */
    public function journal(int|string $id): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'sales_invoice', 'view');
        $invoice = SalesInvoiceService::find((int) $id);
        if (!$invoice) {
            flash('error', 'Invoice not found.');
            return (string) $this->response->redirect($this->request->url('/sales/invoices'))->body();
        }
        $entries = Database::query(
            'SELECT id FROM journal_entries WHERE source_document_type = \'sales_invoice\' AND source_document_id = ? ORDER BY id',
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
            'back'    => $this->request->url('/sales/invoices/' . (int) $id),
        ])->render();
    }
}
