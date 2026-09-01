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
use app\Services\PurchaseInvoiceService;
use app\Services\PurchaseReturnService;
use app\Services\PurchaseService;

/**
 * Purchase returns — reverse stock + AP + tax; optional invoice reference.
 */
final class PurchaseReturnController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_return', 'view');
        $companyId = CompanyContextService::currentCompanyId();
        return $this->view('purchase/returns/index', [
            'title' => 'Purchase Returns',
            'data'  => PurchaseReturnService::paginate($companyId, $this->request->page()),
            'canCreate' => PermissionService::can('purchase', 'purchase_return', 'create'),
        ])->render();
    }

    public function createForm(): string
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_return', 'create');
        \app\Core\Session::forget('_old_input');
        $companyId = CompanyContextService::currentCompanyId();

        $prefillLines = [];
        $referenceId = (int) $this->request->query('from_invoice', 0);
        $reference = null;
        if ($referenceId > 0) {
            $reference = PurchaseInvoiceService::find($referenceId);
            if ($reference && (int) $reference['company_id'] === $companyId && $reference['status'] === 'posted') {
                foreach (PurchaseService::linesFromDocument('purchase_invoice_items', $referenceId) as $l) {
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

        return $this->view('purchase/returns/form', [
            'title'        => 'New Purchase Return',
            'suppliers'    => Database::query('SELECT id, name, code FROM suppliers WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
            'warehouses'   => Database::query('SELECT id, name FROM warehouses WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
            'invoices'     => Database::query('SELECT id, invoice_no, invoice_date, total FROM purchase_invoices WHERE company_id = ? AND status = \'posted\' ORDER BY id DESC LIMIT 50', [$companyId]),
            'prefillLines' => $prefillLines,
            'reference'    => $reference,
        ])->render();
    }

    public function store(): mixed
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_return', 'create');
        $companyId = CompanyContextService::currentCompanyId();

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'return_date' => 'required|date',
            'supplier_id' => 'required|integer',
            'warehouse_id' => 'required|integer',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        try {
            $id = PurchaseReturnService::create($companyId, [
                'branch_id'            => CompanyContextService::currentBranchId(),
                'return_date'          => $d['return_date'],
                'supplier_id'          => (int) $d['supplier_id'],
                'warehouse_id'         => (int) $d['warehouse_id'],
                'reference_invoice_id' => (int) ($this->request->input('reference_invoice_id') ?? 0) ?: null,
                'narration'            => (string) $this->request->input('narration', ''),
                'lines'                => PurchaseService::linesFromInput($this->request->input('items', [])),
            ]);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('create', 'purchase', 'purchase_return', $id, 'Created purchase return');
        flash('success', 'Purchase return posted — stock and accounts reversed.');
        return $this->response->redirect($this->request->url('/purchase/returns'));
    }

    public function show(int|string $id): string
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_return', 'view');
        $return = Database::row('SELECT * FROM purchase_returns WHERE id = ?', [(int) $id]);
        if (!$return || (int) $return['company_id'] !== (int) CompanyContextService::currentCompanyId()) {
            flash('error', 'Return not found.');
            return (string) $this->response->redirect($this->request->url('/purchase/returns'))->body();
        }
        $return['supplier'] = Database::row('SELECT * FROM suppliers WHERE id = ?', [(int) $return['supplier_id']]);
        $return['items'] = Database::query(
            'SELECT pri.*, i.item_code, i.name AS item_name, u.code AS uom_code
             FROM purchase_return_items pri
             JOIN items i ON i.id = pri.item_id
             LEFT JOIN uoms u ON u.id = pri.uom_id
             WHERE pri.return_id = ? ORDER BY pri.id',
            [(int) $id]
        );

        return $this->view('purchase/returns/view', [
            'title'     => 'Purchase Return — ' . $return['return_no'],
            'return'    => $return,
            'canCancel' => PermissionService::can('purchase', 'purchase_return', 'cancel'),
            'canPrint'  => PermissionService::can('purchase', 'purchase_return', 'print'),
        ])->render();
    }

    public function cancel(int|string $id): mixed
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_return', 'cancel');
        $id = (int) $id;

        try {
            PurchaseReturnService::cancel($id, (string) $this->request->input('reason', 'Manual cancellation'));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('cancel', 'purchase', 'purchase_return', $id, 'Cancelled purchase return');
        flash('success', 'Return cancelled and reversed.');
        return $this->response->redirect($this->request->url('/purchase/returns/' . $id));
    }

    public function print(int|string $id): string
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'purchase_return', 'print');
        $return = Database::row('SELECT * FROM purchase_returns WHERE id = ?', [(int) $id]);
        if (!$return) {
            flash('error', 'Return not found.');
            return (string) $this->response->redirect($this->request->url('/purchase/returns'))->body();
        }
        $return['supplier'] = Database::row('SELECT * FROM suppliers WHERE id = ?', [(int) $return['supplier_id']]);
        $return['items'] = Database::query(
            'SELECT pri.*, i.name AS item_name, i.item_code, u.code AS uom_code
             FROM purchase_return_items pri JOIN items i ON i.id = pri.item_id LEFT JOIN uoms u ON u.id = pri.uom_id
             WHERE pri.return_id = ? ORDER BY pri.id', [(int) $id]
        );
        return $this->view('purchase/print', [
            'doc'     => $return,
            'type'    => 'purchase_return',
            'title'   => 'Purchase Return ' . $return['return_no'],
            'company' => CompanyContextService::currentCompany(),
        ])->layout('print')->render();
    }
}
