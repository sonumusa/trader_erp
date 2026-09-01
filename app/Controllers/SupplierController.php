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
 * Supplier master — separate from customers. Accounting uses Accounts
 * Payable (party-tagged); ledgers and outstanding derive from the engine.
 */
final class SupplierController extends Controller
{
    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    public function index(): string
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'supplier', 'view');

        $page = $this->request->page();
        $per = 20;
        $search = (string) $this->request->query('q', '');
        $companyId = CompanyContextService::currentCompanyId();

        $where = 'WHERE s.company_id = ? AND s.deleted_at IS NULL';
        $params = [$companyId];
        if ($search !== '') {
            $where .= ' AND (s.name LIKE ? OR s.code LIKE ? OR s.phone LIKE ?)';
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }

        $total = (int) Database::value("SELECT COUNT(*) FROM suppliers s {$where}", $params);
        $offset = max(0, ($page - 1) * $per);
        $rows = Database::query(
            "SELECT s.*,
                    (SELECT COALESCE(SUM(al.credit),0) - COALESCE(SUM(al.debit),0)
                     FROM account_ledger al
                     WHERE al.party_type = 'supplier' AND al.party_id = s.id) AS outstanding
             FROM suppliers s
             {$where}
             ORDER BY s.name
             LIMIT {$per} OFFSET {$offset}",
            $params
        );

        return $this->view('suppliers/index', [
            'title'     => 'Suppliers',
            'rows'      => $rows,
            'total'     => $total,
            'pages'     => max(1, (int) ceil($total / $per)),
            'page'      => $page,
            'search'    => $search,
            'canCreate' => PermissionService::can('purchase', 'supplier', 'create'),
            'canEdit'   => PermissionService::can('purchase', 'supplier', 'edit'),
        ])->render();
    }

    public function createForm(): string
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'supplier', 'create');
        \app\Core\Session::forget('_old_input');
        return $this->view('suppliers/form', $this->formData(null))->render();
    }

    public function store(): mixed
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'supplier', 'create');

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
            $code = 'S' . str_pad((string) (1 + (int) Database::value('SELECT COUNT(*) FROM suppliers WHERE company_id = ?', [$companyId])), 4, '0', STR_PAD_LEFT);
        }
        $dup = Database::row('SELECT id FROM suppliers WHERE company_id = ? AND code = ? AND deleted_at IS NULL', [$companyId, $code]);
        if ($dup) {
            flash('error', 'A supplier with this code already exists.');
            return $this->response->back();
        }

        $opening = (float) ($d['opening_balance'] ?? 0);

        Database::begin();
        try {
            Database::execute(
                'INSERT INTO suppliers (company_id, branch_id, code, name, phone, mobile, email, address, city, tax_number, opening_balance, status, notes, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $companyId,
                    CompanyContextService::currentBranchId(),
                    $code,
                    $d['name'],
                    $d['phone'] ?? '',
                    $d['mobile'] ?? '',
                    $d['email'] ?? '',
                    $d['address'] ?? '',
                    $d['city'] ?? '',
                    $d['tax_number'] ?? '',
                    $opening,
                    $d['status'] ?? 'active',
                    $d['notes'] ?? '',
                    \app\Services\AuthService::id(),
                    date('Y-m-d H:i:s'),
                    date('Y-m-d H:i:s'),
                ]
            );
            $id = (int) Database::lastInsertId();

            if ($opening != 0) {
                AccountingEngine::postPartyOpening($companyId, 'supplier', $id, $opening);
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollback();
            \app\Core\ErrorHandler::exception($e);
            return $this->response->back();
        }

        AuditService::log('create', 'purchase', 'supplier', $id, "Created supplier {$d['name']}");
        flash('success', 'Supplier created.');
        return $this->response->redirect($this->request->url('/suppliers'));
    }

    public function editForm(int|string $id): string
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'supplier', 'edit');
        $supplier = Database::row('SELECT * FROM suppliers WHERE id = ? AND deleted_at IS NULL', [(int) $id]);
        if (!$supplier) {
            flash('error', 'Supplier not found.');
            return (string) $this->response->redirect($this->request->url('/suppliers'))->body();
        }
        \app\Core\Session::forget('_old_input');
        return $this->view('suppliers/form', $this->formData($supplier))->render();
    }

    public function update(int|string $id): mixed
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'supplier', 'edit');
        $id = (int) $id;

        $supplier = Database::row('SELECT * FROM suppliers WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$supplier) {
            flash('error', 'Supplier not found.');
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
            'UPDATE suppliers
             SET name = ?, phone = ?, mobile = ?, email = ?, address = ?, city = ?,
                 tax_number = ?, status = ?, notes = ?, updated_at = ?
             WHERE id = ?',
            [
                $d['name'],
                $d['phone'] ?? '',
                $d['mobile'] ?? '',
                $d['email'] ?? '',
                $d['address'] ?? '',
                $d['city'] ?? '',
                $d['tax_number'] ?? '',
                $d['status'] ?? 'active',
                $d['notes'] ?? '',
                date('Y-m-d H:i:s'),
                $id,
            ]
        );

        AuditService::log('update', 'purchase', 'supplier', $id, "Updated supplier {$d['name']}");
        flash('success', 'Supplier updated.');
        return $this->response->redirect($this->request->url('/suppliers'));
    }

    public function destroy(int|string $id): mixed
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'supplier', 'delete');
        $id = (int) $id;

        $supplier = Database::row('SELECT * FROM suppliers WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$supplier) {
            flash('error', 'Supplier not found.');
            return $this->response->back();
        }

        $outstanding = PartyLedgerService::outstanding(CompanyContextService::currentCompanyId(), 'supplier', $id);
        if (abs($outstanding) > 0.001) {
            flash('error', 'This supplier has an outstanding balance (' . format_money($outstanding) . '). Settle the balance before removing them.');
            return $this->response->back();
        }

        Database::execute('UPDATE suppliers SET deleted_at = ?, status = \'inactive\', updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $id]);
        AuditService::log('delete', 'purchase', 'supplier', $id, "Deleted supplier {$supplier['name']}");
        flash('success', 'Supplier removed.');
        return $this->response->redirect($this->request->url('/suppliers'));
    }

    public function ledger(int|string $id): string
    {
        $this->requireFeature('purchase');
        $this->requirePermission('purchase', 'supplier', 'view');
        $id = (int) $id;

        $supplier = Database::row('SELECT * FROM suppliers WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$supplier) {
            flash('error', 'Supplier not found.');
            return (string) $this->response->redirect($this->request->url('/suppliers'))->body();
        }

        $companyId = CompanyContextService::currentCompanyId();
        $from = (string) $this->request->query('from', '');
        $to = (string) $this->request->query('to', '');
        $data = PartyLedgerService::ledger(
            $companyId,
            'supplier',
            $id,
            $from !== '' ? $from : null,
            $to !== '' ? $to : null,
            $this->request->page(),
            50
        );
        $aging = PartyLedgerService::aging($companyId, 'supplier', $id);

        return $this->view('suppliers/ledger', [
            'title'    => 'Supplier Ledger — ' . $supplier['name'],
            'supplier' => $supplier,
            'data'     => $data,
            'aging'    => $aging,
            'from'     => $from,
            'to'       => $to,
        ])->render();
    }

    private function formData(?array $supplier): array
    {
        return [
            'title'    => $supplier ? 'Edit Supplier' : 'New Supplier',
            'supplier' => $supplier,
        ];
    }
}
