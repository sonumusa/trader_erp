<?php /** @var app\Core\View $this */
$data = $this->data['data'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Purchase Quotations</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Purchase Quotations</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <?php if ($this->data['canCreate']): ?>
            <a href="<?= $this->url('/purchase/quotations/new') ?>" class="btn btn-primary btn-icon"><i class="bi bi-plus-lg"></i> New Quotation</a>
        <?php endif; ?>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <th>Quotation No.</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Status</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['rows'] as $q): ?>
                    <tr>
                        <td class="fw-semibold"><?= $this->e($q['quotation_no']) ?></td>
                        <td><?= $this->e(format_date($q['quotation_date'])) ?></td>
                        <td><?= $this->e($q['supplier_name']) ?></td>
                        <td>
                            <?php if ($q['status'] === 'posted'): ?>
                                <span class="badge badge-soft-success">Posted</span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger">Cancelled</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <?php if ($q['status'] === 'posted'): ?>
                                <a href="<?= $this->url('/purchase/orders/new?from_quotation=' . (int) $q['id']) ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Create order from this quotation">
                                    <i class="bi bi-clipboard-check"></i>
                                </a>
                                <form method="post" action="<?= $this->url('/purchase/quotations/' . (int) $q['id'] . '/cancel') ?>" class="d-inline" data-confirm="Cancel this quotation?">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="reason" value="Manual cancellation">
                                    <button class="btn btn-sm btn-outline-danger btn-icon" title="Cancel"><i class="bi bi-x-circle"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['rows']): ?>
                    <tr><td colspan="5"><div class="empty-state"><i class="bi bi-file-earmark-text"></i>No purchase quotations yet.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($data['pages'] > 1): ?>
            <nav class="p-2"><ul class="pagination">
                <?php for ($i = 1; $i <= $data['pages']; $i++): ?>
                    <li class="page-item <?= $i === $data['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= $this->url('/purchase/quotations?page=' . $i) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>
</div>
