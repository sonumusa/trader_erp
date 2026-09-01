<?php /** @var app\Core\View $this */
$data = $this->data['data'];
$report = $this->data['report'];
$filters = $this->data['filters'];
// normalize DB-qualified sort keys (si.total -> total) for column matching
$sortKeyMap = ['si.invoice_date' => 'invoice_date', 'si.invoice_no' => 'invoice_no', 'si.total' => 'total',
               'pi.invoice_date' => 'invoice_date', 'pi.invoice_no' => 'invoice_no', 'pi.total' => 'total',
               'al.entry_date' => 'entry_date', 'al.voucher_no' => 'voucher_no'];
$activeSort = $sortKeyMap[$filters['sort']] ?? $filters['sort'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1><?= $this->e($data['title']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/reports') ?>">Reports</a></li>
                <li class="breadcrumb-item active"><?= $this->e($data['title']) ?></li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <?php
        $q = [];
        foreach (['from', 'to', 'sort', 'dir'] as $fk) {
            if (($filters[$fk] ?? '') !== '') {
                $q[] = $fk . '=' . urlencode($filters[$fk]);
            }
        }
        $qs = $q ? '?' . implode('&', $q) : '';
        ?>
        <a href="<?= $this->url('/reports/' . $report . '/print' . $qs) ?>" class="btn btn-outline-secondary btn-icon" target="_blank"><i class="bi bi-printer"></i> Print</a>
        <?php if ($this->data['canExport']): ?>
            <a href="<?= $this->url('/reports/' . $report . '/excel' . $qs) ?>" class="btn btn-outline-success btn-icon"><i class="bi bi-file-earmark-excel"></i> Excel</a>
            <a href="<?= $this->url('/reports/' . $report . '/csv' . $qs) ?>" class="btn btn-outline-primary btn-icon"><i class="bi bi-filetype-csv"></i> CSV</a>
        <?php endif; ?>
    </div>
</div>

<div class="erp-card mb-3">
    <div class="erp-card-body">
        <form method="get" action="<?= $this->url('/reports/' . $report) ?>" class="row g-2 align-items-end">
            <div class="col-auto"><label class="form-label mb-0">From</label><input type="date" name="from" class="form-control form-control-sm" value="<?= $this->e($filters['from']) ?>"></div>
            <div class="col-auto"><label class="form-label mb-0">To</label><input type="date" name="to" class="form-control form-control-sm" value="<?= $this->e($filters['to']) ?>"></div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Apply filters</button></div>
            <div class="col-auto">
                <?php if ($filters['from'] || $filters['to']): ?>
                    <a href="<?= $this->url('/reports/' . $report) ?>" class="btn btn-sm btn-link">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php
// Special layout for Profit & Loss and Balance Sheet
if (isset($data['income']) || isset($data['assets'])):
?>
    <?php if (isset($data['income'])): ?>
        <div class="row g-3 mb-3">
            <div class="col-lg-6">
                <div class="erp-card h-100">
                    <div class="erp-card-header"><h6><i class="bi bi-graph-up-arrow me-1 text-success"></i> Income</h6></div>
                    <div class="erp-card-body p-0">
                        <table class="table erp-table mb-0">
                            <thead><tr><th>Account</th><th class="num">Amount</th></tr></thead>
                            <tbody>
                            <?php foreach ($data['income'] as $r): ?>
                                <tr><td><?= $this->e($r['code'] . ' — ' . $r['name']) ?></td><td class="num"><?= $this->e(format_money($r['net'])) ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot><tr class="fw-bold"><td>Total income</td><td class="num"><?= $this->e(format_money($data['total_income'])) ?></td></tr></tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="erp-card h-100">
                    <div class="erp-card-header"><h6><i class="bi bi-graph-down-arrow me-1 text-danger"></i> Expenses</h6></div>
                    <div class="erp-card-body p-0">
                        <table class="table erp-table mb-0">
                            <thead><tr><th>Account</th><th class="num">Amount</th></tr></thead>
                            <tbody>
                            <?php foreach ($data['expenses'] as $r): ?>
                                <tr><td><?= $this->e($r['code'] . ' — ' . $r['name']) ?></td><td class="num"><?= $this->e(format_money(abs($r['net']))) ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot><tr class="fw-bold"><td>Total expenses</td><td class="num"><?= $this->e(format_money(abs($data['total_expense']))) ?></td></tr></tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="stat-card" style="max-width:360px">
                    <div class="stat-icon <?= $data['net_profit'] >= 0 ? 'icon-green' : 'icon-rose' ?>"><i class="bi bi-graph-up"></i></div>
                    <div>
                        <div class="stat-label">Net profit / (loss)</div>
                        <div class="stat-value"><?= $this->e(format_money($data['net_profit'])) ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($data['assets'])): ?>
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="erp-card h-100">
                    <div class="erp-card-header"><h6><i class="bi bi-coin me-1 text-info"></i> Assets <small class="text-muted">as of <?= $this->e(format_date($data['as_of'])) ?></small></h6></div>
                    <div class="erp-card-body p-0">
                        <table class="table erp-table mb-0">
                            <thead><tr><th>Account</th><th class="num">Amount</th></tr></thead>
                            <tbody>
                            <?php foreach ($data['assets'] as $r): ?>
                                <tr><td><?= $this->e($r['code'] . ' — ' . $r['name']) ?></td><td class="num"><?= $this->e(format_money($r['balance'])) ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot><tr class="fw-bold"><td>Total assets</td><td class="num"><?= $this->e(format_money($data['total_assets'])) ?></td></tr></tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="erp-card h-100">
                    <div class="erp-card-header"><h6><i class="bi bi-bank me-1 text-warning"></i> Liabilities & Equity</h6></div>
                    <div class="erp-card-body p-0">
                        <table class="table erp-table mb-0">
                            <thead><tr><th>Account</th><th class="num">Amount</th></tr></thead>
                            <tbody>
                            <?php foreach (array_merge($data['liabilities'], $data['equity']) as $r): ?>
                                <tr><td><?= $this->e($r['code'] . ' — ' . $r['name']) ?></td><td class="num"><?= $this->e(format_money($r['balance'])) ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot><tr class="fw-bold"><td>Total liabilities & equity</td><td class="num"><?= $this->e(format_money($data['total_liab_equity'])) ?></td></tr></tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>

<div class="erp-card">
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <?php foreach ($data['columns'] as $key => $label): ?>
                            <th class="sortable <?= $this->e(in_array($key, ['debit', 'credit', 'balance', 'total', 'amount', 'outstanding', 'payable', 'qty', 'value', 'subtotal', 'tax', 'cogs', 'profit', 'received_amount', 'paid_amount', 'invoices', 'discount_total', 'avg_rate', 'net'], true) ? 'num' : '') ?>"
                                data-sort="<?= $this->e($key) ?>">
                                <?= $this->e($label) ?>
                                <?php if ($activeSort === $key): ?>
                                    <i class="bi bi-arrow-<?= $filters['dir'] === 'DESC' ? 'down' : 'up' ?>"></i>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['rows'] as $row): ?>
                    <tr>
                        <?php foreach (array_keys($data['columns']) as $key): ?>
                            <?php $v = $row[$key] ?? ''; ?>
                            <td class="<?= in_array($key, ['debit', 'credit', 'balance', 'total', 'amount', 'outstanding', 'payable', 'qty', 'value', 'subtotal', 'tax', 'cogs', 'profit', 'received_amount', 'paid_amount', 'invoices', 'discount_total', 'avg_rate', 'net'], true) ? 'num' : '' ?>">
                                <?= is_numeric($v) ? $this->e(format_money($v)) : $this->e($v) ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['rows']): ?>
                    <tr><td colspan="<?= count($data['columns']) ?>"><div class="empty-state"><i class="bi bi-file-earmark-bar-graph"></i>No data for the selected filters.</div></td></tr>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($data['totals'])): ?>
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="<?= max(1, count($data['columns']) - count($data['totals'])) ?>">Totals</td>
                        <?php foreach ($data['columns'] as $key => $label): ?>
                            <?php if (isset($data['totals'][$key])): ?>
                                <td class="num"><?= $this->e(format_money($data['totals'][$key])) ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<script>
document.querySelectorAll('.sortable[data-sort]').forEach((th) => {
    th.addEventListener('click', () => {
        const sort = th.dataset.sort;
        const dir = '<?= $this->e($filters['dir']) ?>' === 'ASC' ? 'DESC' : 'ASC';
        const url = new URL(window.location.href);
        url.searchParams.set('sort', sort);
        url.searchParams.set('dir', dir);
        window.location = url.toString();
    });
});
</script>
