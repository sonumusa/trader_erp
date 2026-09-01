<?php /** @var app\Core\View $this */
$counts = $this->data['counts'];
$cards  = $this->data['financeCards'];
$today  = $this->data['today'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Dashboard</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Dashboard</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <span class="badge badge-soft-neutral"><i class="bi bi-calendar3 me-1"></i><?= $this->e(format_date($today)) ?></span>
        <a href="<?= $this->url('/sales/invoices/new') ?>" class="btn btn-sm btn-primary btn-icon"><i class="bi bi-plus-lg"></i> New Sale</a>
        <a href="<?= $this->url('/purchase/invoices/new') ?>" class="btn btn-sm btn-outline-primary btn-icon"><i class="bi bi-plus-lg"></i> New Purchase</a>
    </div>
</div>

<!-- Finance cards — compact, no charts -->
<div class="stat-grid">
    <?php foreach ($cards as $card): ?>
    <div class="stat-card">
        <div class="stat-icon icon-<?= $this->e($card['key'] === 'sales' ? 'blue' : ($card['key'] === 'purchase' ? 'green' : ($card['key'] === 'receipts' || $card['key'] === 'receivables' ? 'violet' : ($card['key'] === 'payments' || $card['key'] === 'payables' ? 'amber' : 'slate')))) ?>">
            <i class="bi bi-<?= $this->e($card['icon']) ?>"></i>
        </div>
        <div>
            <div class="stat-label"><?= $this->e($card['label']) ?></div>
            <div class="stat-value"><?= $this->e(format_money($card['value'])) ?></div>
            <div class="stat-note"><?= in_array($card['key'], ['sales', 'purchase', 'receipts', 'payments']) ? 'Today' : 'Live from ledger' ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="stat-grid mb-3" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
    <div class="stat-card">
        <div class="stat-icon icon-slate"><i class="bi bi-people"></i></div>
        <div>
            <div class="stat-label">Users</div>
            <div class="stat-value"><?= (int) $counts['users'] ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-blue"><i class="bi bi-people"></i></div>
        <div>
            <div class="stat-label">Customers</div>
            <div class="stat-value"><?= (int) $counts['customers'] ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-green"><i class="bi bi-truck"></i></div>
        <div>
            <div class="stat-label">Suppliers</div>
            <div class="stat-value"><?= (int) $counts['suppliers'] ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-amber"><i class="bi bi-box"></i></div>
        <div>
            <div class="stat-label">Items</div>
            <div class="stat-value"><?= (int) $counts['items'] ?></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Recent sales -->
    <div class="col-lg-6">
        <div class="erp-card h-100">
            <div class="erp-card-header">
                <h6><i class="bi bi-receipt me-1 text-primary"></i> Recent sales</h6>
                <a href="<?= $this->url('/sales/invoices') ?>" class="btn btn-sm btn-outline-primary ms-auto btn-icon">All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="erp-card-body p-0">
                <div class="table-responsive">
                    <table class="table erp-table mb-0">
                        <thead><tr><th>Invoice</th><th>Date</th><th>Customer</th><th class="num">Total</th></tr></thead>
                        <tbody>
                        <?php foreach ($this->data['recentSales'] as $s): ?>
                            <tr>
                                <td class="fw-semibold"><?= $this->e($s['invoice_no']) ?></td>
                                <td><?= $this->e(format_date($s['invoice_date'])) ?></td>
                                <td class="small"><?= $this->e($s['customer']) ?></td>
                                <td class="num"><?= $this->e(format_money($s['total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$this->data['recentSales']): ?>
                            <tr><td colspan="4"><div class="empty-state" style="padding:1.2rem"><i class="bi bi-receipt"></i>No sales yet.</div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Low stock -->
    <div class="col-lg-6">
        <div class="erp-card h-100">
            <div class="erp-card-header">
                <h6><i class="bi bi-exclamation-triangle me-1 text-warning"></i> Low stock</h6>
                <a href="<?= $this->url('/inventory/low-stock') ?>" class="btn btn-sm btn-outline-primary ms-auto btn-icon">All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="erp-card-body p-0">
                <div class="table-responsive">
                    <table class="table erp-table mb-0">
                        <thead><tr><th>Item</th><th>Warehouse</th><th class="num">On hand</th><th class="num">Reorder</th></tr></thead>
                        <tbody>
                        <?php foreach ($this->data['lowStock'] as $l): ?>
                            <tr>
                                <td class="fw-semibold"><?= $this->e($l['name']) ?><div class="small text-muted"><?= $this->e($l['item_code']) ?></div></td>
                                <td class="small"><?= $this->e($l['warehouse_name']) ?></td>
                                <td class="num"><span class="badge badge-soft-danger"><?= $this->e(rtrim(rtrim(number_format((float) $l['qty'], 2), '0'), '.')) ?></span></td>
                                <td class="num"><?= $this->e(rtrim(rtrim(number_format((float) $l['reorder_level'], 2), '0'), '.')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$this->data['lowStock']): ?>
                            <tr><td colspan="4"><div class="empty-state" style="padding:1.2rem"><i class="bi bi-check2-all"></i>All items above reorder level.</div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Top outstanding customers -->
    <div class="col-lg-6">
        <div class="erp-card h-100">
            <div class="erp-card-header">
                <h6><i class="bi bi-people me-1 text-danger"></i> Outstanding customers</h6>
                <a href="<?= $this->url('/reports/customer_outstanding') ?>" class="btn btn-sm btn-outline-primary ms-auto btn-icon">Report <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="erp-card-body p-0">
                <div class="table-responsive">
                    <table class="table erp-table mb-0">
                        <thead><tr><th>Customer</th><th class="num">Outstanding</th></tr></thead>
                        <tbody>
                        <?php foreach ($this->data['topCustomers'] as $c): ?>
                            <tr>
                                <td class="fw-semibold"><?= $this->e($c['name']) ?><div class="small text-muted"><?= $this->e($c['code']) ?></div></td>
                                <td class="num"><span class="badge badge-soft-danger"><?= $this->e(format_money($c['outstanding'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$this->data['topCustomers']): ?>
                            <tr><td colspan="2"><div class="empty-state" style="padding:1.2rem"><i class="bi bi-check2-all"></i>No outstanding balances.</div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Top payable suppliers -->
    <div class="col-lg-6">
        <div class="erp-card h-100">
            <div class="erp-card-header">
                <h6><i class="bi bi-truck me-1 text-warning"></i> Payable suppliers</h6>
                <a href="<?= $this->url('/reports/supplier_payable') ?>" class="btn btn-sm btn-outline-primary ms-auto btn-icon">Report <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="erp-card-body p-0">
                <div class="table-responsive">
                    <table class="table erp-table mb-0">
                        <thead><tr><th>Supplier</th><th class="num">Payable</th></tr></thead>
                        <tbody>
                        <?php foreach ($this->data['topSuppliers'] as $s): ?>
                            <tr>
                                <td class="fw-semibold"><?= $this->e($s['name']) ?><div class="small text-muted"><?= $this->e($s['code']) ?></div></td>
                                <td class="num"><span class="badge badge-soft-warning"><?= $this->e(format_money($s['payable'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$this->data['topSuppliers']): ?>
                            <tr><td colspan="2"><div class="empty-state" style="padding:1.2rem"><i class="bi bi-check2-all"></i>No payable balances.</div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <!-- Recent activity -->
    <div class="col-lg-6">
        <div class="erp-card h-100">
            <div class="erp-card-header">
                <h6><i class="bi bi-clock-history me-1 text-primary"></i> Recent activity</h6>
                <a href="<?= $this->url('/audit') ?>" class="btn btn-sm btn-outline-primary ms-auto btn-icon">Audit trail <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="erp-card-body p-0">
                <div class="table-responsive">
                    <table class="table erp-table mb-0">
                        <thead><tr><th>User</th><th>Action</th><th>When</th></tr></thead>
                        <tbody>
                        <?php foreach ($this->data['recentActivity'] as $a): ?>
                            <tr>
                                <td><?= $this->e($a['user_name'] ?? 'System') ?></td>
                                <td><span class="badge badge-soft-info"><?= $this->e($a['action']) ?></span></td>
                                <td class="small text-muted"><?= $this->e(format_datetime($a['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$this->data['recentActivity']): ?>
                            <tr><td colspan="3"><div class="empty-state" style="padding:1.2rem"><i class="bi bi-clock-history"></i>No activity recorded yet.</div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recently added users -->
    <div class="col-lg-6">
        <div class="erp-card h-100">
            <div class="erp-card-header">
                <h6><i class="bi bi-person-plus me-1 text-primary"></i> Recently added users</h6>
                <a href="<?= $this->url('/users/new') ?>" class="btn btn-sm btn-outline-primary ms-auto btn-icon"><i class="bi bi-plus-lg"></i> New user</a>
            </div>
            <div class="erp-card-body p-0">
                <div class="table-responsive">
                    <table class="table erp-table mb-0">
                        <thead><tr><th>Name</th><th>Role</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($this->data['recentUsers'] as $u): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= $this->e($u['name']) ?></div>
                                    <div class="small text-muted"><?= $this->e($u['email']) ?></div>
                                </td>
                                <td><span class="badge badge-soft-neutral"><?= $this->e($u['role_name']) ?></span></td>
                                <td>
                                    <?php if ($u['status'] === 'active'): ?>
                                        <span class="badge badge-soft-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-soft-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
