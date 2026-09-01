<?php /** @var app\Core\View $this */
$data = $this->data['data'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Stock Transfers</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/inventory') ?>">Inventory</a></li>
                <li class="breadcrumb-item active">Stock Transfers</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <a href="<?= $this->url('/inventory/transfers/new') ?>" class="btn btn-primary btn-icon">
            <i class="bi bi-plus-lg"></i> New Transfer
        </a>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <th>Transfer No.</th>
                        <th>Date</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Status</th>
                        <th>Created by</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['rows'] as $t): ?>
                    <tr>
                        <td class="fw-semibold"><?= $this->e($t['transfer_no']) ?></td>
                        <td><?= $this->e(format_date($t['transfer_date'])) ?></td>
                        <td><?= $this->e($t['from_wh']) ?></td>
                        <td><i class="bi bi-arrow-right text-muted"></i> <?= $this->e($t['to_wh']) ?></td>
                        <td>
                            <?php if ($t['status'] === 'posted'): ?>
                                <span class="badge badge-soft-success">Posted</span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger">Cancelled</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= $this->e($t['created_name'] ?? '—') ?></td>
                        <td class="actions-cell">
                            <?php if ($t['status'] === 'posted'): ?>
                                <form method="post" action="<?= $this->url('/inventory/transfers/' . (int) $t['id'] . '/reverse') ?>" class="d-inline"
                                      data-confirm="Cancel this transfer? Stock will move back to the source warehouse.">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="reason" value="Manual cancellation">
                                    <button class="btn btn-sm btn-outline-danger btn-icon" title="Cancel & reverse"><i class="bi bi-arrow-counterclockwise"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['rows']): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="bi bi-arrow-left-right"></i>No transfers yet.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($data['pages'] > 1): ?>
            <nav class="p-2"><ul class="pagination">
                <?php for ($i = 1; $i <= $data['pages']; $i++): ?>
                    <li class="page-item <?= $i === $data['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $this->url('/inventory/transfers?page=' . $i) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>
</div>
