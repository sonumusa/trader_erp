<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Database;
use app\Core\Request;
use app\Core\Response;
use app\Core\Validator;
use app\Services\AccountingEngine;
use app\Services\AccountService;
use app\Services\AuditService;
use app\Services\CompanyContextService;
use app\Services\FeatureService;
use app\Services\PaymentModeService;
use app\Services\PermissionService;
use app\Services\VoucherService;

/**
 * Vouchers — Cash/Bank Payment, Cash/Bank Receipt, Journal, Contra.
 * Saving posts a balanced journal entry immediately; no draft step.
 */
final class VoucherController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requireFeature('accounting');
        $this->requirePermission('accounting', 'voucher', 'view');

        $companyId = CompanyContextService::currentCompanyId();
        $data = VoucherService::paginate(
            $companyId,
            (string) $this->request->query('type', ''),
            $this->request->page(),
            25,
            (string) $this->request->query('from', '') ?: null,
            (string) $this->request->query('to', '') ?: null
        );

        return $this->view('vouchers/index', [
            'title' => 'Vouchers',
            'data'  => $data,
            'types' => VoucherService::TYPES,
            'type'  => (string) $this->request->query('type', ''),
            'from'  => (string) $this->request->query('from', ''),
            'to'    => (string) $this->request->query('to', ''),
            'canCreate' => PermissionService::can('accounting', 'voucher', 'create'),
            'canEdit' => PermissionService::can('accounting', 'voucher', 'edit'),
            'canCancel' => PermissionService::can('accounting', 'voucher', 'cancel'),
        ])->render();
    }

    public function createForm(?string $type = null): string
    {
        $this->requireFeature('accounting');
        $this->requirePermission('accounting', 'voucher', 'create');
        \app\Core\Session::forget('_old_input');

        $companyId = CompanyContextService::currentCompanyId();
        $type = $type ?? (string) $this->request->query('type', 'cash_receipt');
        if (!isset(VoucherService::TYPES[$type])) {
            $type = 'cash_receipt';
        }

        $bankModes = array_values(array_filter(
            PaymentModeService::all($companyId),
            fn($m) => (int) $m['is_bank'] === 1 && (int) ($m['account_id'] ?? 0) > 0
        ));

        return $this->view('vouchers/form', [
            'title'        => 'New ' . VoucherService::TYPES[$type]['label'],
            'types'        => VoucherService::TYPES,
            'voucherType'  => $type,
            'customers'    => Database::query('SELECT id, name, code FROM customers WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
            'suppliers'    => Database::query('SELECT id, name, code FROM suppliers WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
            'bankModes'    => $bankModes,
            'accounts'     => AccountService::leafAccounts($companyId),
            'cashBankAccounts' => VoucherService::cashBankAccounts($companyId),
            'voucher'      => null,
            'isEdit'       => false,
        ])->render();
    }

    public function editForm(int|string $id): string
    {
        $this->requireFeature('accounting');
        $this->requirePermission('accounting', 'voucher', 'edit');
        $voucher = VoucherService::find((int) $id);
        if (!$voucher || (int) $voucher['company_id'] !== (int) CompanyContextService::currentCompanyId()) {
            flash('error', 'Voucher not found.');
            return (string) $this->response->redirect($this->request->url('/vouchers'))->body();
        }
        if ($voucher['status'] !== 'posted') {
            flash('error', 'Cancelled vouchers cannot be edited.');
            return (string) $this->response->redirect($this->request->url('/vouchers/' . (int) $id))->body();
        }
        $companyId = (int) $voucher['company_id'];
        return $this->view('vouchers/form', [
            'title' => 'Edit ' . VoucherService::TYPES[$voucher['voucher_type']]['label'],
            'types' => VoucherService::TYPES,
            'voucherType' => $voucher['voucher_type'],
            'customers' => Database::query('SELECT id, name, code FROM customers WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
            'suppliers' => Database::query('SELECT id, name, code FROM suppliers WHERE company_id = ? AND deleted_at IS NULL AND status = \'active\' ORDER BY name', [$companyId]),
            'bankModes' => array_values(array_filter(PaymentModeService::all($companyId), fn($m) => (int) $m['is_bank'] === 1 && (int) ($m['account_id'] ?? 0) > 0)),
            'accounts' => AccountService::leafAccounts($companyId),
            'cashBankAccounts' => VoucherService::cashBankAccounts($companyId),
            'voucher' => $voucher,
            'isEdit' => true,
        ])->render();
    }

    public function store(): mixed
    {
        $this->requireFeature('accounting');
        $this->requirePermission('accounting', 'voucher', 'create');
        $companyId = CompanyContextService::currentCompanyId();

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'voucher_type' => 'required|in:cash_payment,bank_payment,cash_receipt,bank_receipt,journal,contra',
            'voucher_date' => 'required|date',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        // Journal lines from the dynamic grid
        $lines = [];
        $lines = $this->journalLinesFromInput();

        try {
            $id = VoucherService::create($companyId, [
                'branch_id'         => CompanyContextService::currentBranchId(),
                'voucher_type'      => $d['voucher_type'],
                'voucher_date'      => $d['voucher_date'],
                'narration'         => (string) $this->request->input('narration', ''),
                'party_type'        => (string) $this->request->input('party_type', ''),
                'party_id'          => (int) ($this->request->input('party_id') ?? 0),
                'payment_mode_id'   => (int) ($this->request->input('payment_mode_id') ?? 0),
                'amount'            => (float) ($this->request->input('amount') ?? 0),
                'counter_account_id'=> (int) ($this->request->input('counter_account_id') ?? 0),
                'from_account_id'   => (int) ($this->request->input('from_account_id') ?? 0),
                'to_account_id'     => (int) ($this->request->input('to_account_id') ?? 0),
                'lines'             => $lines,
            ]);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('create', 'accounting', 'voucher', $id, 'Created ' . VoucherService::TYPES[$d['voucher_type']]['label']);
        flash('success', 'Voucher posted — accounting updated.');
        return $this->response->redirect($this->request->url('/vouchers/' . $id));
    }

    public function show(int|string $id): string
    {
        $this->requireFeature('accounting');
        $this->requirePermission('accounting', 'voucher', 'view');
        $voucher = VoucherService::find((int) $id);
        if (!$voucher || (int) $voucher['company_id'] !== (int) CompanyContextService::currentCompanyId()) {
            flash('error', 'Voucher not found.');
            return (string) $this->response->redirect($this->request->url('/vouchers'))->body();
        }

        $entry = Database::row(
            'SELECT id, entry_no FROM journal_entries WHERE source_document_type = \'voucher\' AND source_document_id = ? AND status = \'posted\' ORDER BY id LIMIT 1',
            [(int) $id]
        );

        return $this->view('vouchers/view', [
            'title'   => VoucherService::TYPES[$voucher['voucher_type']]['label'] . ' — ' . $voucher['voucher_no'],
            'voucher' => $voucher,
            'journalEntry' => $entry,
            'canCancel' => PermissionService::can('accounting', 'voucher', 'cancel'),
            'canPrint'  => PermissionService::can('accounting', 'voucher', 'print'),
            'canEdit'   => PermissionService::can('accounting', 'voucher', 'edit'),
        ])->render();
    }

    public function cancel(int|string $id): mixed
    {
        $this->requireFeature('accounting');
        $this->requirePermission('accounting', 'voucher', 'cancel');
        $id = (int) $id;

        try {
            VoucherService::cancel($id, (string) $this->request->input('reason', 'Manual cancellation'));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }

        AuditService::log('cancel', 'accounting', 'voucher', $id, 'Cancelled voucher');
        flash('success', 'Voucher cancelled — accounting reversed.');
        return $this->response->redirect($this->request->url('/vouchers/' . $id));
    }

    public function update(int|string $id): mixed
    {
        $this->requireFeature('accounting');
        $this->requirePermission('accounting', 'voucher', 'edit');
        $id = (int) $id;
        $v = new Validator();
        if (!$v->validate($this->request->all(), ['voucher_type' => 'required|in:cash_payment,bank_payment,cash_receipt,bank_receipt,journal,contra', 'voucher_date' => 'required|date'])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();
        try {
            VoucherService::update($id, [
                'voucher_type' => $d['voucher_type'],
                'voucher_date' => $d['voucher_date'],
                'narration' => (string) $this->request->input('narration', ''),
                'party_type' => (string) $this->request->input('party_type', ''),
                'party_id' => (int) ($this->request->input('party_id') ?? 0),
                'payment_mode_id' => (int) ($this->request->input('payment_mode_id') ?? 0),
                'amount' => (float) ($this->request->input('amount') ?? 0),
                'counter_account_id' => (int) ($this->request->input('counter_account_id') ?? 0),
                'from_account_id' => (int) ($this->request->input('from_account_id') ?? 0),
                'to_account_id' => (int) ($this->request->input('to_account_id') ?? 0),
                'lines' => $this->journalLinesFromInput(),
            ]);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            return $this->response->back();
        }
        AuditService::log('update', 'accounting', 'voucher', $id, 'Edited voucher and rebuilt accounting posting');
        flash('success', 'Voucher updated — accounting was rebuilt safely.');
        return $this->response->redirect($this->request->url('/vouchers/' . $id));
    }

    private function journalLinesFromInput(): array
    {
        $lines = [];
        $inputLines = $this->request->input('lines', []);
        if (!is_array($inputLines)) {
            return $lines;
        }
        $accounts = $inputLines['account_id'] ?? [];
        $debits = $inputLines['debit'] ?? [];
        $credits = $inputLines['credit'] ?? [];
        $narrations = $inputLines['narration'] ?? [];
        if (!is_array($accounts)) {
            return $lines;
        }
        foreach ($accounts as $i => $accountId) {
            $lines[] = [
                'account_id' => (int) $accountId,
                'debit' => (float) ($debits[$i] ?? 0),
                'credit' => (float) ($credits[$i] ?? 0),
                'narration' => (string) ($narrations[$i] ?? ''),
            ];
        }
        return $lines;
    }

    public function print(int|string $id): string
    {
        $this->requireFeature('accounting');
        $this->requirePermission('accounting', 'voucher', 'print');
        $voucher = VoucherService::find((int) $id);
        if (!$voucher) {
            flash('error', 'Voucher not found.');
            return (string) $this->response->redirect($this->request->url('/vouchers'))->body();
        }
        return $this->view('vouchers/print', [
            'voucher' => $voucher,
            'title'   => VoucherService::TYPES[$voucher['voucher_type']]['label'] . ' ' . $voucher['voucher_no'],
            'company' => CompanyContextService::currentCompany(),
        ])->layout('print')->render();
    }

    public function journal(int|string $id): string
    {
        $this->requireFeature('accounting');
        $this->requirePermission('accounting', 'voucher', 'view');
        $voucher = VoucherService::find((int) $id);
        if (!$voucher) {
            flash('error', 'Voucher not found.');
            return (string) $this->response->redirect($this->request->url('/vouchers'))->body();
        }
        $entries = Database::query(
            'SELECT id FROM journal_entries WHERE source_document_type = \'voucher\' AND source_document_id = ? ORDER BY id',
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
            'title'   => 'Accounting — ' . $voucher['voucher_no'],
            'entries' => $rows,
            'back'    => $this->request->url('/vouchers/' . (int) $id),
        ])->render();
    }
}
