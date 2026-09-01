<?php /** @var app\Core\View $this */
$invoice = $this->data['invoice'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Sales Invoice — <?= $this->e($invoice['invoice_no']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/sales/invoices') ?>">Sales Invoices</a></li>
                <li class="breadcrumb-item active"><?= $this->e($invoice['invoice_no']) ?></li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <?php if ($this->data['canPrint']): ?>
            <a href="<?= $this->url('/sales/invoices/' . (int) $invoice['id'] . '/print') ?>" class="btn btn-outline-secondary btn-icon"><i class="bi bi-printer"></i> Print</a>
        <?php endif; ?>
        <a href="<?= $this->url('/sales/invoices/' . (int) $invoice['id'] . '/journal') ?>" class="btn btn-outline-primary btn-icon"><i class="bi bi-journal-text"></i> View Accounting</a>
        <?php if ($invoice['customer_id']): ?>
            <a href="<?= $this->url('/customers/' . (int) $invoice['customer_id'] . '/ledger') ?>" class="btn btn-outline-primary btn-icon"><i class="bi bi-journal-arrow-down"></i> Customer Ledger</a>
        <?php endif; ?>
        <?php if ($this->data['canCancel'] && $invoice['status'] === 'posted'): ?>
            <form method="post" action="<?= $this->url('/sales/invoices/' . (int) $invoice['id'] . '/cancel') ?>" class="d-inline"
                  data-confirm="Cancel this sales invoice? Stock and accounting entries will be reversed.">
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
        <span><b>CANCELLED</b> — <?= $this->e($invoice['cancelled_reason'] ?? '') ?></span>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon icon-blue"><i class="bi bi-receipt"></i></div><div><div class="stat-label">Total</div><div class="stat-value"><?= $this->e(format_money($invoice['total'])) ?></div></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon icon-amber"><i class="bi bi-percent"></i></div><div><div class="stat-label">Tax</div><div class="stat-value"><?= $this->e(format_money($invoice['tax_total'])) ?></div></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon icon-rose"><i class="bi bi-box-seam"></i></div><div><div class="stat-label">COGS</div><div class="stat-value"><?= $this->e(format_money($invoice['cogs_total'])) ?></div></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-icon icon-green"><i class="bi bi-graph-up-arrow"></i></div><div><div class="stat-label">Gross profit</div><div class="stat-value"><?= $this->e(format_money($invoice['subtotal'] - $invoice['cogs_total'])) ?></div></div></div></div>
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
                        <th class="num">COGS</th>
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
                        <td class="num text-muted"><?= $this->e(format_money($line['cogs_amount'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="7" class="text-end">Subtotal / Discount / Tax / Total</td>
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


<div class="erp-card mt-3">
    <div class="erp-card-header">
        <h6><i class="bi bi-paperclip me-1 text-primary"></i> Attachments</h6>
    </div>
    <div class="erp-card-body">
        <?php if ($this->data['attachments']): ?>
            <div class="list-group list-group-flush mb-3">
                <?php foreach ($this->data['attachments'] as $att): ?>
                    <div class="list-group-item d-flex align-items-center gap-2 py-2 px-0">
                        <i class="bi bi-file-earmark text-muted"></i>
                        <div class="flex-grow-1">
                            <div class="small fw-semibold"><?= $this->e($att['original_name']) ?></div>
                            <div class="small text-muted">
                                <?= $this->e(number_format((int) $att['size_bytes'])) ?> bytes
                                &middot; <?= $this->e($att['uploaded_name'] ?? '—') ?>
                                &middot; <?= $this->e(format_datetime($att['created_at'])) ?>
                            </div>
                        </div>
                        <a href="<?= $this->url('/sales/invoices/' . (int) $invoice['id'] . '/attachments/' . (int) $att['id'] . '/download') ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Download">
                            <i class="bi bi-download"></i>
                        </a>
                        <?php if ($this->data['canEdit']): ?>
                            <form method="post" action="<?= $this->url('/sales/invoices/' . (int) $invoice['id'] . '/attachments/' . (int) $att['id'] . '/delete') ?>" class="d-inline"
                                  data-confirm="Remove this attachment?">
                                <?= $this->csrfField() ?>
                                <button class="btn btn-sm btn-outline-danger btn-icon" title="Remove"><i class="bi bi-trash"></i></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="small text-muted">No attachments yet.</p>
        <?php endif; ?>

        <?php if ($this->data['canEdit'] && $invoice['status'] === 'posted'): ?>
            <form method="post" action="<?= $this->url('/sales/invoices/' . (int) $invoice['id'] . '/attachments') ?>" enctype="multipart/form-data" class="row g-2 align-items-end">
                <?= $this->csrfField() ?>
                <div class="col-auto flex-grow-1" style="max-width:320px">
                    <input type="file" class="form-control form-control-sm" name="attachment" required
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.jpg,.jpeg,.png,.gif,.webp">
                </div>
                <div class="col-auto">
                    <select class="form-select form-select-sm" name="category">
                        <option value="document">Document</option>
                        <option value="image">Image</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-outline-primary btn-icon"><i class="bi bi-upload"></i> Upload</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="audit-section mt-3">
    <span><b>Created:</b> <?= $this->e(format_datetime($invoice['created_at'])) ?></span>
    <span><b>Modified:</b> <?= $this->e(format_datetime($invoice['updated_at'])) ?></span>
    <?php if ($invoice['status'] === 'cancelled'): ?>
        <span><b>Cancelled:</b> <?= $this->e(format_datetime($invoice['cancelled_at'])) ?></span>
    <?php endif; ?>
</div>
