<?php

declare(strict_types=1);

namespace app\Controllers;

use app\Core\Controller;
use app\Core\Database;
use app\Core\Request;
use app\Core\Response;
use app\Core\Validator;
use app\Services\AccountDefaultService;
use app\Services\AccountService;
use app\Services\AuditService;
use app\Services\CompanyContextService;
use app\Services\PermissionService;

/**
 * Chart of Accounts — hierarchical tree, account CRUD with deletion safety,
 * per-account ledger, and the Accounting Defaults screen.
 */
final class AccountController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    /* ---------------- COA tree ---------------- */

    public function index(): string
    {
        $this->requirePermission('accounting', 'account', 'view');

        $companyId = CompanyContextService::currentCompanyId();
        $tree = AccountService::tree($companyId);

        return $this->view('accounts/index', [
            'title'   => 'Chart of Accounts',
            'tree'    => $tree,
            'company' => CompanyContextService::currentCompany(),
            'canCreate' => PermissionService::can('accounting', 'account', 'create'),
            'canEdit'   => PermissionService::can('accounting', 'account', 'edit'),
        ])->render();
    }

    /* ---------------- Account form ---------------- */

    public function createForm(): string
    {
        $this->requirePermission('accounting', 'account', 'create');
        $companyId = CompanyContextService::currentCompanyId();
        return $this->view('accounts/form', $this->formData(null, $companyId))->render();
    }

    public function store(): mixed
    {
        $this->requirePermission('accounting', 'account', 'create');
        $companyId = CompanyContextService::currentCompanyId();

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'code'         => 'required|max:30',
            'name'         => 'required|max:150',
            'account_type' => 'required|in:asset,liability,income,expense,equity',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        $dup = Database::row('SELECT id FROM accounts WHERE company_id = ? AND code = ? AND deleted_at IS NULL', [$companyId, $d['code']]);
        if ($dup) {
            flash('error', 'An account with this code already exists.');
            return $this->response->back();
        }

        $parentId = (int) ($d['parent_id'] ?? 0);
        if ($parentId > 0) {
            $parent = Database::row('SELECT * FROM accounts WHERE id = ? AND company_id = ?', [$parentId, $companyId]);
            if (!$parent) {
                flash('error', 'Invalid parent account.');
                return $this->response->back();
            }
        }

        $isGroup = ($d['is_group'] ?? '0') === '1';
        $accountType = $d['account_type'];
        if ($parentId > 0) {
            // child must share the parent's type
            $parentType = Database::value('SELECT account_type FROM accounts WHERE id = ?', [$parentId]);
            if ($parentType !== $accountType && !$isGroup) {
                $accountType = $parentType;
            }
        }

        Database::execute(
            'INSERT INTO accounts (company_id, parent_id, code, name, account_type, is_group, is_system, is_cash, is_bank, is_active, opening_balance, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?)',
            [$companyId, $parentId > 0 ? $parentId : null, $d['code'], $d['name'], $accountType, $isGroup ? 1 : 0,
             ($d['is_cash'] ?? '0') === '1' ? 1 : 0, ($d['is_bank'] ?? '0') === '1' ? 1 : 0,
             ($d['is_active'] ?? '1') === '1' ? 1 : 0,
             (float) ($d['opening_balance'] ?? 0),
             date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]
        );
        $id = (int) Database::lastInsertId();

        AuditService::log('create', 'accounting', 'account', $id, "Created account {$d['code']} {$d['name']}");
        flash('success', 'Account created.');
        return $this->response->redirect($this->request->url('/accounts'));
    }

    public function editForm(int|string $id): string
    {
        $this->requirePermission('accounting', 'account', 'edit');
        $companyId = CompanyContextService::currentCompanyId();
        $account = Database::row('SELECT * FROM accounts WHERE id = ? AND company_id = ? AND deleted_at IS NULL', [(int) $id, $companyId]);
        if (!$account) {
            flash('error', 'Account not found.');
            return (string) $this->response->redirect($this->request->url('/accounts'))->body();
        }
        return $this->view('accounts/form', $this->formData($account, $companyId))->render();
    }

    public function update(int|string $id): mixed
    {
        $this->requirePermission('accounting', 'account', 'edit');
        $id = (int) $id;
        $companyId = CompanyContextService::currentCompanyId();

        $account = Database::row('SELECT * FROM accounts WHERE id = ? AND company_id = ? AND deleted_at IS NULL', [$id, $companyId]);
        if (!$account) {
            flash('error', 'Account not found.');
            return $this->response->back();
        }

        $code = (string) $this->request->input('code');
        $dup = Database::row('SELECT id FROM accounts WHERE company_id = ? AND code = ? AND id != ? AND deleted_at IS NULL', [$companyId, $code, $id]);
        if ($dup) {
            flash('error', 'An account with this code already exists.');
            return $this->response->back();
        }

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'code'         => 'required|max:30',
            'name'         => 'required|max:150',
            'account_type' => 'required|in:asset,liability,income,expense,equity',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        // Account type is immutable once the account has ledger activity
        if ($d['account_type'] !== $account['account_type']) {
            $used = (int) Database::value('SELECT COUNT(*) FROM journal_entry_lines WHERE account_id = ?', [$id]);
            if ($used > 0) {
                flash('error', 'This account has transactions, so its type cannot be changed.');
                return $this->response->back();
            }
        }

        Database::execute(
            'UPDATE accounts
             SET code = ?, name = ?, account_type = ?, is_group = ?, is_cash = ?, is_bank = ?,
                 is_active = ?, opening_balance = ?, updated_at = ?
             WHERE id = ?',
            [$d['code'], $d['name'], $d['account_type'], ($d['is_group'] ?? '0') === '1' ? 1 : 0,
             ($d['is_cash'] ?? '0') === '1' ? 1 : 0, ($d['is_bank'] ?? '0') === '1' ? 1 : 0,
             ($d['is_active'] ?? '1') === '1' ? 1 : 0, (float) ($d['opening_balance'] ?? 0),
             date('Y-m-d H:i:s'), $id]
        );

        AuditService::log('update', 'accounting', 'account', $id, "Updated account {$d['code']} {$d['name']}");
        flash('success', 'Account updated.');
        return $this->response->redirect($this->request->url('/accounts'));
    }

    public function destroy(int|string $id): mixed
    {
        $this->requirePermission('accounting', 'account', 'delete');
        $id = (int) $id;

        [$ok, $reason] = AccountService::canDelete($id);
        if (!$ok) {
            flash('error', $reason);
            return $this->response->back();
        }

        Database::execute('UPDATE accounts SET deleted_at = ?, is_active = 0, updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id]);
        AuditService::log('delete', 'accounting', 'account', $id, "Deleted account (no transactions)");
        flash('success', 'Account deleted.');
        return $this->response->redirect($this->request->url('/accounts'));
    }

    /* ---------------- Ledger ---------------- */

    public function ledger(int|string $id): string
    {
        $this->requirePermission('accounting', 'account', 'view');
        $companyId = CompanyContextService::currentCompanyId();
        $id = (int) $id;

        $account = Database::row('SELECT * FROM accounts WHERE id = ? AND company_id = ? AND deleted_at IS NULL', [$id, $companyId]);
        if (!$account) {
            flash('error', 'Account not found.');
            return (string) $this->response->redirect($this->request->url('/accounts'))->body();
        }

        $from = (string) $this->request->query('from', '');
        $to = (string) $this->request->query('to', '');
        $data = AccountService::ledger(
            $id,
            $from !== '' ? $from : null,
            $to !== '' ? $to : null,
            $this->request->page(),
            50
        );

        return $this->view('accounts/ledger', [
            'title' => 'Ledger — ' . $account['name'],
            'data'  => $data,
            'from'  => $from,
            'to'    => $to,
        ])->render();
    }

    /* ---------------- Accounting defaults ---------------- */

    public function defaults(): string
    {
        $this->requirePermission('accounting', 'defaults', 'view');

        $companyId = CompanyContextService::currentCompanyId();
        $current = AccountDefaultService::all($companyId);

        // Build the per-key dropdown options, filtered by account type
        $options = [];
        foreach (AccountDefaultService::KEYS as $key => [$label, $type]) {
            $options[$key] = [
                'label'   => $label,
                'account' => $current[$key] ?? null,
                'choices' => AccountService::leafAccounts($companyId, $type === 'any' ? null : $type),
            ];
        }

        return $this->view('accounts/defaults', [
            'title'   => 'Accounting Defaults',
            'options' => $options,
        ])->render();
    }

    public function defaultsStore(): mixed
    {
        $this->requirePermission('accounting', 'defaults', 'edit');
        $companyId = CompanyContextService::currentCompanyId();

        $submitted = $this->request->input('defaults', []);
        $submitted = is_array($submitted) ? $submitted : [];

        $saved = 0;
        foreach (AccountDefaultService::KEYS as $key => $_) {
            $accountId = (int) ($submitted[$key] ?? 0);
            if ($accountId > 0) {
                try {
                    AccountDefaultService::set($companyId, $key, $accountId);
                    $saved++;
                } catch (\RuntimeException $e) {
                    flash('error', $e->getMessage());
                    return $this->response->back();
                }
            }
        }

        AuditService::log('update', 'accounting', 'defaults', null, "Updated accounting defaults ({$saved} keys)");
        flash('success', 'Accounting defaults saved.');
        return $this->response->redirect($this->request->url('/accounts/defaults'));
    }

    /* ---------------- helpers ---------------- */

    private function formData(?array $account, int $companyId): array
    {
        $groups = Database::query(
            'SELECT id, code, name, account_type FROM accounts
             WHERE company_id = ? AND is_group = 1 AND deleted_at IS NULL ORDER BY code',
            [$companyId]
        );
        return [
            'title'    => $account ? 'Edit Account' : 'New Account',
            'account'  => $account,
            'groups'   => $groups,
        ];
    }
}
