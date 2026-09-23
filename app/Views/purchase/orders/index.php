<?php /** @var app\Core\View $this */
$data = $this->data['data'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Purchase Orders</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Purchase Orders</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <a href="<?= $this->url('/purchase/quotations') ?>" class="btn btn-outline-secondary btn-icon"><i class="bi bi-file-earmark-text"></i> Quotations</a>
        <?php if ($this->data['canCreate']): ?>
            <a href="<?= $this->url('/purchase/orders/new') ?>" class="btn btn-primary btn-icon"><i class="bi bi-plus-lg"></i> New Purchase Order</a>
        <?php endif; ?>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <th>Order No.</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>From quotation</th>
                        <th>Invoiced / Remaining</th>
                        <th>Status</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['rows'] as $o): ?>
                    <tr>
                        <td class="fw-semibold"><?= $this->e($o['order_no']) ?></td>
                        <td><?= $this->e(format_date($o['order_date'])) ?></td>
                        <td><?= $this->e($o['supplier_name']) ?></td>
                        <td class="small"><?= $o['reference_quotation_id'] ? 'PQ #' . (int) $o['reference_quotation_id'] : '—' ?></td>
                        <td class="small"><?= $this->e(number_format((float) $o['invoiced_qty'], 2)) ?> / <?= $this->e(number_format(max(0, (float) $o['ordered_qty'] - (float) $o['invoiced_qty']), 2)) ?> <span class="text-muted">of <?= $this->e(number_format((float) $o['ordered_qty'], 2)) ?></span></td>
                        <td>
                            <?php if ($o['status'] === 'posted' && (float) $o['ordered_qty'] <= (float) $o['invoiced_qty'] + 0.0001): ?>
                                <span class="badge badge-soft-info">Fully Invoiced</span>
                            <?php elseif ($o['status'] === 'posted'): ?>
                                <span class="badge badge-soft-success">Posted</span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger">Cancelled</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <?php if ($this->data['canEdit']): ?><a href="<?= $this->url('/purchase/orders/' . (int) $o['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Edit"><i class="bi bi-pencil"></i></a><?php endif; ?>
                            <?php if ($o['status'] === 'posted' && (float) $o['ordered_qty'] > (float) $o['invoiced_qty']): ?>
                                <a href="<?= $this->url('/purchase/invoices/new?from_order=' . (int) $o['id']) ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Create invoice from this order">
                                    <i class="bi bi-receipt-cutoff"></i>
                                </a>
                                <form method="post" action="<?= $this->url('/purchase/orders/' . (int) $o['id'] . '/cancel') ?>" class="d-inline" data-confirm="Cancel this purchase order?">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="reason" value="Manual cancellation">
                                    <button class="btn btn-sm btn-outline-danger btn-icon" title="Cancel"><i class="bi bi-x-circle"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['rows']): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="bi bi-clipboard-check"></i>No purchase orders yet.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($data['pages'] > 1): ?>
            <nav class="p-2"><ul class="pagination">
                <?php for ($i = 1; $i <= $data['pages']; $i++): ?>
                    <li class="page-item <?= $i === $data['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= $this->url('/purchase/orders?page=' . $i) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>
</div>
