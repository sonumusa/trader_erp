<?php /** @var app\Core\View $this */
$return = $this->data['return'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Purchase Return — <?= $this->e($return['return_no']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/purchase/returns') ?>">Purchase Returns</a></li>
                <li class="breadcrumb-item active"><?= $this->e($return['return_no']) ?></li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <?php if ($this->data['canPrint']): ?>
            <a href="<?= $this->url('/purchase/returns/' . (int) $return['id'] . '/print') ?>" class="btn btn-outline-secondary btn-icon"><i class="bi bi-printer"></i> Print</a>
        <?php endif; ?>
        <a href="<?= $this->url('/suppliers/' . (int) $return['supplier_id'] . '/ledger') ?>" class="btn btn-outline-primary btn-icon"><i class="bi bi-journal-arrow-down"></i> Supplier Ledger</a>
        <?php if ($this->data['canCancel'] && $return['status'] === 'posted'): ?>
            <form method="post" action="<?= $this->url('/purchase/returns/' . (int) $return['id'] . '/cancel') ?>" class="d-inline"
                  data-confirm="Cancel this purchase return? Stock and accounting will be reversed.">
                <?= $this->csrfField() ?>
                <input type="hidden" name="reason" value="Manual cancellation">
                <button class="btn btn-outline-danger btn-icon"><i class="bi bi-x-circle"></i> Cancel Return</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($return['status'] === 'cancelled'): ?>
    <div class="erp-alert erp-alert-danger">
        <i class="bi bi-x-circle"></i>
        <span><b>CANCELLED</b> — <?= $this->e($return['cancelled_reason'] ?? '') ?></span>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon icon-rose"><i class="bi bi-arrow-counterclockwise"></i></div><div><div class="stat-label">Return total</div><div class="stat-value"><?= $this->e(format_money($return['total'])) ?></div></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon icon-amber"><i class="bi bi-percent"></i></div><div><div class="stat-label">Tax reversed</div><div class="stat-value"><?= $this->e(format_money($return['tax_total'])) ?></div></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-icon icon-blue"><i class="bi bi-receipt"></i></div><div><div class="stat-label">Reference invoice</div><div class="stat-value small"><?= $return['reference_invoice_id'] ? 'PI #' . (int) $return['reference_invoice_id'] : '—' ?></div></div></div></div>
</div>

<div class="erp-card">
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr><th>Item</th><th>UOM</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Tax</th><th class="num">Amount</th></tr>
                </thead>
                <tbody>
                <?php foreach ($return['items'] as $line): ?>
                    <tr>
                        <td><?= $this->e($line['item_name']) ?><div class="small text-muted"><?= $this->e($line['item_code']) ?></div></td>
                        <td><?= $this->e($line['uom_code'] ?? '—') ?></td>
                        <td class="num"><?= $this->e(rtrim(rtrim(number_format((float) $line['quantity'], 4), '0'), '.')) ?></td>
                        <td class="num"><?= $this->e(format_money($line['rate'])) ?></td>
                        <td class="num"><?= (float) $line['tax_amount'] > 0 ? $this->e(format_money($line['tax_amount'])) : '—' ?></td>
                        <td class="num fw-semibold"><?= $this->e(format_money($line['amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="audit-section mt-3">
    <span><b>Created by:</b> <?= $this->e($return['created_by'] ?? '—') ?> <?= $this->e(format_datetime($return['created_at'])) ?></span>
    <?php if ($return['status'] === 'cancelled'): ?>
        <span><b>Cancelled:</b> <?= $this->e(format_datetime($return['cancelled_at'])) ?></span>
    <?php endif; ?>
</div>
