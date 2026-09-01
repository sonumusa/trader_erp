<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Database;
use app\Core\Request;
use app\Core\Response;
use app\Core\Validator;
use app\Services\AccountService;
use app\Services\AuditService;
use app\Services\CompanyContextService;
use app\Services\PaymentModeService;
use app\Services\PermissionService;

/**
 * Payment modes — configurable payment modes linked to GL accounts.
 * When a user picks a mode (Cash, Meezan Bank, HBL…) the linked account is
 * auto-selected; they never choose GL accounts manually.
 */
final class PaymentModeController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requirePermission('settings', 'payment_mode', 'view');

        $companyId = CompanyContextService::currentCompanyId();
        $modes = PaymentModeService::all($companyId, false);

        // Account choices: asset accounts (cash/bank) for binding
        $accounts = AccountService::leafAccounts($companyId, 'asset');

        return $this->view('settings/payment_modes', [
            'title'    => 'Payment Modes',
            'modes'    => $modes,
            'accounts' => $accounts,
        ])->render();
    }

    public function store(): mixed
    {
        $this->requirePermission('settings', 'payment_mode', 'create');

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name' => 'required|max:80',
            'code' => 'required|max:30',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        $companyId = CompanyContextService::currentCompanyId();
        $code = strtoupper($d['code']);
        $dup = Database::row('SELECT id FROM payment_modes WHERE company_id = ? AND code = ? AND deleted_at IS NULL', [$companyId, $code]);
        if ($dup) {
            flash('error', 'A payment mode with this code already exists.');
            return $this->response->back();
        }

        $id = PaymentModeService::create($companyId, $d);
        AuditService::log('create', 'settings', 'payment_mode', $id, "Created payment mode {$d['name']} ({$code})");
        flash('success', 'Payment mode created.');
        return $this->response->redirect($this->request->url('/settings/payment-modes'));
    }

    public function update(int|string $id): mixed
    {
        $this->requirePermission('settings', 'payment_mode', 'edit');
        $id = (int) $id;

        $mode = PaymentModeService::get($id);
        if (!$mode) {
            flash('error', 'Payment mode not found.');
            return $this->response->back();
        }

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name' => 'required|max:80',
            'code' => 'required|max:30',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        $code = strtoupper($d['code']);
        $dup = Database::row('SELECT id FROM payment_modes WHERE company_id = ? AND code = ? AND id != ? AND deleted_at IS NULL', [$mode['company_id'], $code, $id]);
        if ($dup) {
            flash('error', 'A payment mode with this code already exists.');
            return $this->response->back();
        }

        PaymentModeService::update($id, $d);
        AuditService::log('update', 'settings', 'payment_mode', $id, "Updated payment mode {$d['name']}");
        flash('success', 'Payment mode updated.');
        return $this->response->redirect($this->request->url('/settings/payment-modes'));
    }

    public function destroy(int|string $id): mixed
    {
        $this->requirePermission('settings', 'payment_mode', 'delete');
        $id = (int) $id;

        [$ok, $reason] = PaymentModeService::canDelete($id);
        if (!$ok) {
            flash('error', $reason);
            return $this->response->back();
        }

        PaymentModeService::destroy($id);
        AuditService::log('delete', 'settings', 'payment_mode', $id, 'Deleted payment mode');
        flash('success', 'Payment mode deleted.');
        return $this->response->redirect($this->request->url('/settings/payment-modes'));
    }
}
