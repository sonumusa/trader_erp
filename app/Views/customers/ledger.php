<?php /** @var app\Core\View $this */
$data = $this->data['data'];
$customer = $this->data['customer'];
$aging = $this->data['aging'];
$from = $this->data['from'];
$to = $this->data['to'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Customer Ledger — <?= $this->e($customer['name']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/customers') ?>">Customers</a></li>
                <li class="breadcrumb-item active"><?= $this->e($customer['code']) ?></li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <a href="<?= $this->url('/customers') ?>" class="btn btn-outline-secondary btn-icon">
            <i class="bi bi-arrow-left"></i> Customers
        </a>
        <a href="<?= $this->url('/customers/' . (int) $customer['id'] . '/edit') ?>" class="btn btn-outline-primary btn-icon">
            <i class="bi bi-pencil"></i> Edit
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon icon-rose"><i class="bi bi-arrow-down-circle"></i></div>
            <div>
                <div class="stat-label">Outstanding</div>
                <div class="stat-value"><?= $this->e(format_money($aging['total'])) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon icon-blue"><i class="bi bi-wallet2"></i></div>
            <div>
                <div class="stat-label">Opening (filter)</div>
                <div class="stat-value"><?= $this->e(format_money($data['opening'])) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon icon-slate"><i class="bi bi-cash-stack"></i></div>
            <div>
                <div class="stat-label">Closing (filter)</div>
                <div class="stat-value"><?= $this->e(format_money($data['closing'])) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-icon icon-amber"><i class="bi bi-clock-history"></i></div>
            <div>
                <div class="stat-label">Over 60 days</div>
                <div class="stat-value"><?= $this->e(format_money($aging['buckets']['61-90'] + $aging['buckets']['90+'])) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="erp-card h-100">
            <div class="erp-card-header"><h6><i class="bi bi-hourglass-split me-1 text-primary"></i> Aging</h6></div>
            <div class="erp-card-body p-0">
                <table class="table erp-table mb-0">
                    <tbody>
                    <?php foreach ($aging['buckets'] as $label => $amount): ?>
                        <tr>
                            <td class="small text-muted"><?= $this->e($label . ' days') ?></td>
                            <td class="num fw-semibold"><?= $this->e(format_money($amount)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="erp-card h-100">
            <div class="erp-card-header"><h6><i class="bi bi-funnel me-1 text-primary"></i> Filter</h6></div>
            <div class="erp-card-body">
                <form method="get" action="<?= $this->url('/customers/' . (int) $customer['id'] . '/ledger') ?>" class="row g-2 align-items-end">
                    <div class="col-auto"><label class="form-label mb-0">From</label><input type="date" name="from" class="form-control form-control-sm" value="<?= $this->e($from) ?>"></div>
                    <div class="col-auto"><label class="form-label mb-0">To</label><input type="date" name="to" class="form-control form-control-sm" value="<?= $this->e($to) ?>"></div>
                    <div class="col-auto"><button class="btn btn-sm btn-primary">Apply</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Voucher No.</th>
                        <th>Description</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Balance</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['rows'] as $row): ?>
                    <tr>
                        <td class="text-nowrap"><?= $this->e(format_date($row['entry_date'])) ?></td>
                        <td>
                            <span class="fw-semibold"><?= $this->e($row['voucher_no']) ?></span>
                            <?php if (str_contains((string) $row['voucher_no'], '(R)')): ?>
                                <span class="badge badge-soft-warning ms-1">Rev</span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= $this->e($row['voucher_type'] ?: '') ?></td>
                        <td class="num"><?= (float) $row['debit'] > 0 ? $this->e(format_money($row['debit'])) : '—' ?></td>
                        <td class="num"><?= (float) $row['credit'] > 0 ? $this->e(format_money($row['credit'])) : '—' ?></td>
                        <td class="num fw-semibold"><?= $this->e(format_money($row['balance'])) ?></td>
                        <td class="small text-muted">
                            <?= $row['source_document_type'] ? $this->e(str_replace('_', ' ', $row['source_document_type'])) : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['rows']): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="bi bi-journal-text"></i>No ledger entries in this range.</div></td></tr>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="3">Totals</td>
                        <td class="num"><?= $this->e(format_money($data['debit_total'])) ?></td>
                        <td class="num"><?= $this->e(format_money($data['credit_total'])) ?></td>
                        <td class="num"><?= $this->e(format_money($data['closing'])) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php if ($data['pages'] > 1): ?>
            <nav class="p-2"><ul class="pagination">
                <?php for ($i = 1; $i <= $data['pages']; $i++): ?>
                    <li class="page-item <?= $i === $data['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $this->url('/customers/' . (int) $customer['id'] . '/ledger?page=' . $i . '&from=' . urlencode($from) . '&to=' . urlencode($to)) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>
</div>
