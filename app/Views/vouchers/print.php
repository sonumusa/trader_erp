<?php /** @var app\Core\View $this */
$voucher = $this->data['voucher'];
$typeMeta = \app\Services\VoucherService::TYPES[$voucher['voucher_type']];
$company = $this->data['company'];
?>
<?php $this->layout('print'); ?>

<div class="print-head d-flex justify-content-between align-items-start">
    <div>
        <div class="print-title"><?= $this->e($company['name'] ?? 'TradeERP') ?></div>
        <div class="small text-muted"><?= $this->e($company['address'] ?? '') ?></div>
        <div class="small text-muted"><?= $this->e($company['phone'] ?? '') ?> <?= $this->e($company['email'] ?? '') ?></div>
    </div>
    <div class="text-end">
        <div class="print-title"><?= $this->e($typeMeta['label']) ?></div>
        <div class="small">No: <b><?= $this->e($voucher['voucher_no']) ?></b></div>
        <div class="small">Date: <b><?= $this->e(format_date($voucher['voucher_date'])) ?></b></div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-6">
        <div class="small text-muted">Paid to / Received from</div>
        <div class="fw-bold"><?= $this->e($voucher['party']['name'] ?? '—') ?></div>
    </div>
    <div class="col-6 text-end">
        <div class="small"><?= $this->e($voucher['narration'] ?: '') ?></div>
        <?php if ($voucher['payment_mode']): ?>
            <div class="small">Mode: <b><?= $this->e($voucher['payment_mode']['name']) ?></b></div>
        <?php endif; ?>
    </div>
</div>

<table class="table table-bordered print-table">
    <thead>
        <tr>
            <th>Account</th>
            <th>Narration</th>
            <th class="text-end">Debit</th>
            <th class="text-end">Credit</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($voucher['lines'] as $line): ?>
        <tr>
            <td><?= $this->e($line['account_code'] . ' — ' . $line['account_name']) ?></td>
            <td class="small"><?= $this->e($line['narration'] ?: '') ?></td>
            <td class="text-end"><?= (float) $line['debit'] > 0 ? $this->e(format_money($line['debit'])) : '—' ?></td>
            <td class="text-end"><?= (float) $line['credit'] > 0 ? $this->e(format_money($line['credit'])) : '—' ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2" class="text-end"><b>Total</b></td>
            <td class="text-end"><b><?= $this->e(format_money(array_sum(array_column($voucher['lines'], 'debit')))) ?></b></td>
            <td class="text-end"><b><?= $this->e(format_money(array_sum(array_column($voucher['lines'], 'credit')))) ?></b></td>
        </tr>
    </tfoot>
</table>

<?php if ($voucher['status'] === 'cancelled'): ?>
    <div class="alert alert-danger">CANCELLED — <?= $this->e($voucher['cancelled_reason'] ?? '') ?></div>
<?php endif; ?>

<div class="row print-sign text-center">
    <div class="col-4">Prepared by</div>
    <div class="col-4">Checked by</div>
    <div class="col-4">Authorised by</div>
</div>
