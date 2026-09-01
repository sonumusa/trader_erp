<?php /** @var app\Core\View $this */
$data = $this->data['data'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Sales Returns</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Sales Returns</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <?php if ($this->data['canCreate']): ?>
            <a href="<?= $this->url('/sales/returns/new') ?>" class="btn btn-primary btn-icon"><i class="bi bi-plus-lg"></i> New Sales Return</a>
        <?php endif; ?>
    </div>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-arrow-counterclockwise"></i>
    <span>A sales return brings goods back into stock, reduces what the customer owes (or refunds cash), reverses COGS and reverses output tax. You may reference the original invoice or create it without one.</span>
</div>

<div class="erp-card">
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <th>Return No.</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Reference invoice</th>
                        <th class="num">Total</th>
                        <th>Status</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['rows'] as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= $this->e($r['return_no']) ?></td>
                        <td><?= $this->e(format_date($r['return_date'])) ?></td>
                        <td><?= $this->e($r['customer_name'] ?? 'Walk-in') ?></td>
                        <td class="small"><?= $r['reference_invoice_id'] ? 'SI #' . (int) $r['reference_invoice_id'] : '—' ?></td>
                        <td class="num fw-semibold"><?= $this->e(format_money($r['total'])) ?></td>
                        <td>
                            <?php if ($r['status'] === 'posted'): ?>
                                <span class="badge badge-soft-success">Posted</span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger">Cancelled</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <a href="<?= $this->url('/sales/returns/' . (int) $r['id']) ?>" class="btn btn-sm btn-outline-primary btn-icon" title="View"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['rows']): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="bi bi-arrow-counterclockwise"></i>No sales returns yet.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($data['pages'] > 1): ?>
            <nav class="p-2"><ul class="pagination">
                <?php for ($i = 1; $i <= $data['pages']; $i++): ?>
                    <li class="page-item <?= $i === $data['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= $this->url('/sales/returns?page=' . $i) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>
</div>
