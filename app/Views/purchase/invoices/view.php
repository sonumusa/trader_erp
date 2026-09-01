<?php /** @var app\Core\View $this */
$invoice = $this->data['invoice'];
$journalEntry = $this->data['journalEntry'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Purchase Invoice — <?= $this->e($invoice['invoice_no']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/purchase/invoices') ?>">Purchase Invoices</a></li>
                <li class="breadcrumb-item active"><?= $this->e($invoice['invoice_no']) ?></li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <?php if ($this->data['canPrint']): ?>
            <a href="<?= $this->url('/purchase/invoices/' . (int) $invoice['id'] . '/print') ?>" class="btn btn-outline-secondary btn-icon"><i class="bi bi-printer"></i> Print</a>
        <?php endif; ?>
        <a href="<?= $this->url('/purchase/invoices/' . (int) $invoice['id'] . '/journal') ?>" class="btn btn-outline-primary btn-icon"><i class="bi bi-journal-text"></i> View Accounting</a>
        <a href="<?= $this->url('/suppliers/' . (int) $invoice['supplier_id'] . '/ledger') ?>" class="btn btn-outline-primary btn-icon"><i class="bi bi-journal-arrow-down"></i> Supplier Ledger</a>
        <?php if ($this->data['canCancel'] && $invoice['status'] === 'posted'): ?>
            <form method="post" action="<?= $this->url('/purchase/invoices/' . (int) $invoice['id'] . '/cancel') ?>" class="d-inline"
                  data-confirm="Cancel this purchase invoice? Stock and accounting entries will be reversed.">
                <?= $this->csrfField() ?>
                <input type="hidden" name="reason" value="Manual cancellation">
                <button class="btn btn-outline-danger btn-icon"><i class="bi bi-x-circle"></i> Cancel Invoice</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($invoice['status'] === 'cancelled'): ?>
    <div class="erp-alert erp-alert-danger">
        <i class="bi bi-x-circle"></i>
        <span><b>CANCELLED</b> — <?= $this->e($invoice['cancelled_reason'] ?? '') ?>
            by <?= $this->e($invoice['cancelled_by'] ?? '') ?> on <?= $this->e(format_datetime($invoice['cancelled_at'])) ?></span>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon icon-blue"><i class="bi bi-receipt"></i></div><div><div class="stat-label">Total</div><div class="stat-value"><?= $this->e(format_money($invoice['total'])) ?></div></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon icon-amber"><i class="bi bi-percent"></i></div><div><div class="stat-label">Tax</div><div class="stat-value"><?= $this->e(format_money($invoice['tax_total'])) ?></div></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon icon-green"><i class="bi bi-cash-coin"></i></div><div><div class="stat-label">Paid</div><div class="stat-value"><?= $this->e(format_money($invoice['paid_amount'])) ?></div></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon icon-rose"><i class="bi bi-wallet2"></i></div><div><div class="stat-label">Balance</div><div class="stat-value"><?= $this->e(format_money($invoice['total'] - $invoice['paid_amount'])) ?></div></div></div></div>
</div>

<div class="erp-card">
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>UOM</th>
                        <th class="num">Qty</th>
                        <th class="num">Rate</th>
                        <th class="num">Discount</th>
                        <th class="num">Tax</th>
                        <th class="num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($invoice['items'] as $line): ?>
                    <tr>
                        <td><?= $this->e($line['item_name']) ?><div class="small text-muted"><?= $this->e($line['item_code']) ?></div></td>
                        <td><?= $this->e($line['uom_code'] ?? '—') ?></td>
                        <td class="num"><?= $this->e(rtrim(rtrim(number_format((float) $line['quantity'], 4), '0'), '.')) ?></td>
                        <td class="num"><?= $this->e(format_money($line['rate'])) ?></td>
                        <td class="num"><?= (float) $line['discount'] > 0 ? $this->e(format_money($line['discount'])) : '—' ?></td>
                        <td class="num"><?= (float) $line['tax_amount'] > 0 ? $this->e(format_money($line['tax_amount'])) : '—' ?></td>
                        <td class="num fw-semibold"><?= $this->e(format_money($line['amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="6" class="text-end">Subtotal / Discount / Tax / Total</td>
                        <td class="num">
                            <?= $this->e(format_money($invoice['subtotal'])) ?> /
                            <?= $this->e(format_money($invoice['discount_total'])) ?> /
                            <?= $this->e(format_money($invoice['tax_total'])) ?> /
                            <?= $this->e(format_money($invoice['total'])) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<div class="audit-section mt-3">
    <span><b>Created by:</b> <?= $this->e($invoice['created_by'] ?? '—') ?> <?= $this->e(format_datetime($invoice['created_at'])) ?></span>
    <span><b>Modified:</b> <?= $this->e(format_datetime($invoice['updated_at'])) ?></span>
    <?php if ($invoice['status'] === 'cancelled'): ?>
        <span><b>Cancelled by:</b> <?= $this->e($invoice['cancelled_by'] ?? '—') ?> <?= $this->e(format_datetime($invoice['cancelled_at'])) ?></span>
    <?php endif; ?>
</div>
