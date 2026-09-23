<?php /** @var app\Core\View $this */
$voucher = $this->data['voucher'];
$typeMeta = \app\Services\VoucherService::TYPES[$voucher['voucher_type']];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1><?= $this->e($typeMeta['label']) ?> — <?= $this->e($voucher['voucher_no']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/vouchers') ?>">Vouchers</a></li>
                <li class="breadcrumb-item active"><?= $this->e($voucher['voucher_no']) ?></li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <?php if ($this->data['canPrint']): ?>
            <a href="<?= $this->url('/vouchers/' . (int) $voucher['id'] . '/print') ?>" class="btn btn-outline-secondary btn-icon"><i class="bi bi-printer"></i> Print</a>
        <?php endif; ?>
        <?php if (($this->data['canEdit'] ?? false) && $voucher['status'] === 'posted'): ?>
            <a href="<?= $this->url('/vouchers/' . (int) $voucher['id'] . '/edit') ?>" class="btn btn-outline-primary btn-icon"><i class="bi bi-pencil"></i> Edit</a>
        <?php endif; ?>
        <a href="<?= $this->url('/vouchers/' . (int) $voucher['id'] . '/journal') ?>" class="btn btn-outline-primary btn-icon"><i class="bi bi-journal-text"></i> View Accounting</a>
        <?php if ($voucher['party_type'] === 'customer'): ?>
            <a href="<?= $this->url('/customers/' . (int) $voucher['party_id'] . '/ledger') ?>" class="btn btn-outline-primary btn-icon"><i class="bi bi-journal-arrow-down"></i> Customer Ledger</a>
        <?php elseif ($voucher['party_type'] === 'supplier'): ?>
            <a href="<?= $this->url('/suppliers/' . (int) $voucher['party_id'] . '/ledger') ?>" class="btn btn-outline-primary btn-icon"><i class="bi bi-journal-arrow-down"></i> Supplier Ledger</a>
        <?php endif; ?>
        <?php if ($this->data['canCancel'] && $voucher['status'] === 'posted'): ?>
            <form method="post" action="<?= $this->url('/vouchers/' . (int) $voucher['id'] . '/cancel') ?>" class="d-inline"
                  data-confirm="Cancel this voucher? The accounting entry will be reversed.">
                <?= $this->csrfField() ?>
                <input type="hidden" name="reason" value="Manual cancellation">
                <button class="btn btn-outline-danger btn-icon"><i class="bi bi-x-circle"></i> Cancel Voucher</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($voucher['status'] === 'cancelled'): ?>
    <div class="erp-alert erp-alert-danger">
        <i class="bi bi-x-circle"></i>
        <span><b>CANCELLED</b> — <?= $this->e($voucher['cancelled_reason'] ?? '') ?></span>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon icon-blue"><i class="bi bi-receipt"></i></div><div><div class="stat-label">Voucher type</div><div class="stat-value small"><?= $this->e($typeMeta['label']) ?></div></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon icon-green"><i class="bi bi-calendar3"></i></div><div><div class="stat-label">Date</div><div class="stat-value small"><?= $this->e(format_date($voucher['voucher_date'])) ?></div></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon icon-amber"><i class="bi bi-person-lines-fill"></i></div><div><div class="stat-label">Party</div><div class="stat-value small"><?= $this->e($voucher['party']['name'] ?? '—') ?></div></div></div></div>
</div>

<div class="erp-card">
    <div class="erp-card-header">
        <h6><i class="bi bi-journal-text me-1 text-primary"></i> Accounting lines</h6>
        <div class="ms-auto small text-muted"><?= $this->e($voucher['narration'] ?: '') ?></div>
    </div>
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Narration</th>
                        <th class="num">Debit</th>
                        <th class="num">Credit</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($voucher['lines'] as $line): ?>
                    <tr>
                        <td>
                            <?= $this->e($line['account_code'] . ' — ' . $line['account_name']) ?>
                            <?php if ($line['party_type']): ?>
                                <span class="badge badge-soft-info ms-1"><?= $this->e($line['party_type']) ?> #<?= (int) $line['party_id'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= $this->e($line['narration'] ?: '') ?></td>
                        <td class="num"><?= (float) $line['debit'] > 0 ? $this->e(format_money($line['debit'])) : '—' ?></td>
                        <td class="num"><?= (float) $line['credit'] > 0 ? $this->e(format_money($line['credit'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="2">Totals</td>
                        <td class="num"><?= $this->e(format_money(array_sum(array_column($voucher['lines'], 'debit')))) ?></td>
                        <td class="num"><?= $this->e(format_money(array_sum(array_column($voucher['lines'], 'credit')))) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="audit-section mt-3">
    <span><b>Created:</b> <?= $this->e(format_datetime($voucher['created_at'])) ?></span>
    <span><b>Modified:</b> <?= $this->e(format_datetime($voucher['updated_at'])) ?></span>
    <?php if ($voucher['status'] === 'cancelled'): ?>
        <span><b>Cancelled:</b> <?= $this->e(format_datetime($voucher['cancelled_at'])) ?></span>
    <?php endif; ?>
</div>
