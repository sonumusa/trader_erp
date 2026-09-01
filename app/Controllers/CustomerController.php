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
use app\Services\PartyLedgerService;
use app\Services\PermissionService;

/**
 * Customer master — separate from suppliers. Accounting uses Accounts
 * Receivable (party-tagged); ledgers and outstanding derive from the
 * accounting engine's party sub-ledger.
 */
final class CustomerController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'customer', 'view');

        $page = $this->request->page();
        $per = 20;
        $search = (string) $this->request->query('q', '');
        $companyId = CompanyContextService::currentCompanyId();

        $where = 'WHERE c.company_id = ? AND c.deleted_at IS NULL';
        $params = [$companyId];
        if ($search !== '') {
            $where .= ' AND (c.name LIKE ? OR c.code LIKE ? OR c.phone LIKE ? OR c.mobile LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $total = (int) Database::value("SELECT COUNT(*) FROM customers c {$where}", $params);
        $offset = max(0, ($page - 1) * $per);
        $rows = Database::query(
            "SELECT c.*,
                    (SELECT COALESCE(SUM(al.debit),0) - COALESCE(SUM(al.credit),0)
                     FROM account_ledger al
                     WHERE al.party_type = 'customer' AND al.party_id = c.id) AS outstanding
             FROM customers c
             {$where}
             ORDER BY c.name
             LIMIT {$per} OFFSET {$offset}",
            $params
        );

        return $this->view('customers/index', [
            'title'     => 'Customers',
            'rows'      => $rows,
            'total'     => $total,
            'pages'     => max(1, (int) ceil($total / $per)),
            'page'      => $page,
            'search'    => $search,
            'canCreate' => PermissionService::can('sales', 'customer', 'create'),
            'canEdit'   => PermissionService::can('sales', 'customer', 'edit'),
        ])->render();
    }

    public function createForm(): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'customer', 'create');
        \app\Core\Session::forget('_old_input');
        return $this->view('customers/form', $this->formData(null))->render();
    }

    public function store(): mixed
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'customer', 'create');

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name'  => 'required|max:150',
            'email' => 'email',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            session_set('_old_input', $this->request->all());
            return $this->response->back();
        }
        $d = $v->data();

        $companyId = CompanyContextService::currentCompanyId();
        $code = $d['code'] ?? '';
        if ($code === '') {
            $code = 'C' . str_pad((string) (1 + (int) Database::value('SELECT COUNT(*) FROM customers WHERE company_id = ?', [$companyId])), 4, '0', STR_PAD_LEFT);
        }
        $dup = Database::row('SELECT id FROM customers WHERE company_id = ? AND code = ? AND deleted_at IS NULL', [$companyId, $code]);
        if ($dup) {
            flash('error', 'A customer with this code already exists.');
            return $this->response->back();
        }

        $opening = (float) ($d['opening_balance'] ?? 0);

        Database::begin();
        try {
            Database::execute(
                'INSERT INTO customers (company_id, branch_id, code, name, type, phone, mobile, email, address, city, tax_number, opening_balance, credit_limit, status, notes, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $companyId,
                    CompanyContextService::currentBranchId(),
                    $code,
                    $d['name'],
                    $d['type'] ?? 'business',
                    $d['phone'] ?? '',
                    $d['mobile'] ?? '',
                    $d['email'] ?? '',
                    $d['address'] ?? '',
                    $d['city'] ?? '',
                    $d['tax_number'] ?? '',
                    $opening,
                    !empty($d['credit_limit']) ? (float) $d['credit_limit'] : null,
                    $d['status'] ?? 'active',
                    $d['notes'] ?? '',
                    \app\Services\AuthService::id(),
                    date('Y-m-d H:i:s'),
                    date('Y-m-d H:i:s'),
                ]
            );
            $id = (int) Database::lastInsertId();

            // Opening balance → real accounting posting (party-tagged AR line)
            if ($opening != 0) {
                AccountingEngine::postPartyOpening($companyId, 'customer', $id, $opening);
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            \app\Core\ErrorHandler::exception($e);
            return $this->response->back();
        }

        AuditService::log('create', 'sales', 'customer', $id, "Created customer {$d['name']}");
        flash('success', 'Customer created.');
        return $this->response->redirect($this->request->url('/customers'));
    }

    public function editForm(int|string $id): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'customer', 'edit');
        $customer = Database::row('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$customer) {
            flash('error', 'Customer not found.');
            return (string) $this->response->redirect($this->request->url('/customers'))->body();
        }
        \app\Core\Session::forget('_old_input');
        return $this->view('customers/form', $this->formData($customer))->render();
    }

    public function update(int|string $id): mixed
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'customer', 'edit');
        $id = (int) $id;

        $customer = Database::row('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$customer) {
            flash('error', 'Customer not found.');
            return $this->response->back();
        }

        $v = new Validator();
        if (!$v->validate($this->request->all(), [
            'name'  => 'required|max:150',
            'email' => 'email',
        ])) {
            flash('error', $v->firstError() ?? 'Please check the form.');
            return $this->response->back();
        }
        $d = $v->data();

        Database::execute(
            'UPDATE customers
             SET name = ?, type = ?, phone = ?, mobile = ?, email = ?, address = ?, city = ?,
                 tax_number = ?, credit_limit = ?, status = ?, notes = ?, updated_at = ?
             WHERE id = ?',
            [
                $d['name'],
                $d['type'] ?? 'business',
                $d['phone'] ?? '',
                $d['mobile'] ?? '',
                $d['email'] ?? '',
                $d['address'] ?? '',
                $d['city'] ?? '',
                $d['tax_number'] ?? '',
                !empty($d['credit_limit']) ? (float) $d['credit_limit'] : null,
                $d['status'] ?? 'active',
                $d['notes'] ?? '',
                date('Y-m-d H:i:s'),
                $id,
            ]
        );

        AuditService::log('update', 'sales', 'customer', $id, "Updated customer {$d['name']}");
        flash('success', 'Customer updated.');
        return $this->response->redirect($this->request->url('/customers'));
    }

    public function destroy(int|string $id): mixed
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'customer', 'delete');
        $id = (int) $id;

        $customer = Database::row('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$customer) {
            flash('error', 'Customer not found.');
            return $this->response->back();
        }

        $outstanding = PartyLedgerService::outstanding(CompanyContextService::currentCompanyId(), 'customer', $id);
        if (abs($outstanding) > 0.001) {
            flash('error', 'This customer has an outstanding balance (' . format_money($outstanding) . '). Settle the balance before removing them.');
            return $this->response->back();
        }

        Database::execute('UPDATE customers SET deleted_at = ?, status = \'inactive\', updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id]);
        AuditService::log('delete', 'sales', 'customer', $id, "Deleted customer {$customer['name']}");
        flash('success', 'Customer removed.');
        return $this->response->redirect($this->request->url('/customers'));
    }

    public function ledger(int|string $id): string
    {
        $this->requireFeature('sales');
        $this->requirePermission('sales', 'customer', 'view');
        $id = (int) $id;

        $customer = Database::row('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$customer) {
            flash('error', 'Customer not found.');
            return (string) $this->response->redirect($this->request->url('/customers'))->body();
        }

        $companyId = CompanyContextService::currentCompanyId();
        $from = (string) $this->request->query('from', '');
        $to = (string) $this->request->query('to', '');
        $data = PartyLedgerService::ledger(
            $companyId,
            'customer',
            $id,
            $from !== '' ? $from : null,
            $to !== '' ? $to : null,
            $this->request->page(),
            50
        );
        $aging = PartyLedgerService::aging($companyId, 'customer', $id);

        return $this->view('customers/ledger', [
            'title'    => 'Customer Ledger — ' . $customer['name'],
            'customer' => $customer,
            'data'     => $data,
            'aging'    => $aging,
            'from'     => $from,
            'to'       => $to,
        ])->render();
    }

    private function formData(?array $customer): array
    {
        return [
            'title'    => $customer ? 'Edit Customer' : 'New Customer',
            'customer' => $customer,
        ];
    }
}
