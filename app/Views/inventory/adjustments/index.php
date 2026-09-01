<?php /** @var app\Core\View $this */
$data = $this->data['data'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Stock Adjustments</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/inventory') ?>">Inventory</a></li>
                <li class="breadcrumb-item active">Stock Adjustments</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <a href="<?= $this->url('/inventory/adjustments/new') ?>" class="btn btn-primary btn-icon">
            <i class="bi bi-plus-lg"></i> New Adjustment
        </a>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <th>Adjustment No.</th>
                        <th>Date</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Created by</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['rows'] as $a): ?>
                    <tr>
                        <td class="fw-semibold"><?= $this->e($a['adjustment_no']) ?></td>
                        <td><?= $this->e(format_date($a['adjustment_date'])) ?></td>
                        <td class="small"><?= $this->e($a['reason'] ?: '—') ?></td>
                        <td>
                            <?php if ($a['status'] === 'posted'): ?>
                                <span class="badge badge-soft-success">Posted</span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger">Cancelled</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= $this->e($a['created_name'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['rows']): ?>
                    <tr><td colspan="5"><div class="empty-state"><i class="bi bi-sliders"></i>No adjustments yet.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($data['pages'] > 1): ?>
            <nav class="p-2"><ul class="pagination">
                <?php for ($i = 1; $i <= $data['pages']; $i++): ?>
                    <li class="page-item <?= $i === $data['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $this->url('/inventory/adjustments?page=' . $i) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>
</div>
