<?php /** @var app\Core\View $this */
$current = $this->data['current'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Financial Years</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/settings') ?>">Settings</a></li>
                <li class="breadcrumb-item active">Financial Years</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <a href="<?= $this->url('/settings/financial-years/new') ?>" class="btn btn-primary btn-icon">
            <i class="bi bi-plus-lg"></i> New Financial Year
        </a>
    </div>
</div>

<?php if ($current): ?>
    <div class="erp-alert erp-alert-info">
        <i class="bi bi-calendar3"></i>
        <span>Active financial year: <b><?= $this->e($current['name']) ?></b>
            (<?= $this->e(format_date($current['start_date'])) ?> → <?= $this->e(format_date($current['end_date'])) ?>)</span>
    </div>
<?php endif; ?>

<div class="erp-card">
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Status</th>
                        <th>Closing</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->data['years'] as $y): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= $this->e($y['name']) ?></div>
                            <?php if ($current && (int) $y['id'] === (int) $current['id']): ?>
                                <span class="badge badge-soft-info mt-1">Current period</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $this->e(format_date($y['start_date'])) ?></td>
                        <td><?= $this->e(format_date($y['end_date'])) ?></td>
                        <td>
                            <?php if ((int) $y['is_active'] === 1): ?>
                                <span class="badge badge-soft-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-soft-neutral">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $y['is_closed'] === 1): ?>
                                <span class="badge badge-soft-danger">Closed</span>
                            <?php else: ?>
                                <span class="badge badge-soft-neutral">Open</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <?php if ((int) $y['is_active'] !== 1): ?>
                                <form method="post" action="<?= $this->url('/settings/financial-years/' . (int) $y['id'] . '/activate') ?>" class="d-inline">
                                    <?= $this->csrfField() ?>
                                    <button class="btn btn-sm btn-outline-primary btn-icon" title="Set active"><i class="bi bi-check2-circle"></i></button>
                                </form>
                            <?php endif; ?>
                            <a href="<?= $this->url('/settings/financial-years/' . (int) $y['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="post" action="<?= $this->url('/settings/financial-years/' . (int) $y['id'] . '/toggle-close') ?>" class="d-inline"
                                  data-confirm="<?= (int) $y['is_closed'] === 1 ? 'Reopen this financial year?' : 'Close this financial year? Transactions in the period will be locked by the closing rule.' ?>">
                                <?= $this->csrfField() ?>
                                <button class="btn btn-sm btn-outline-<?= (int) $y['is_closed'] === 1 ? 'success' : 'danger' ?> btn-icon" title="<?= (int) $y['is_closed'] === 1 ? 'Reopen' : 'Close' ?>">
                                    <i class="bi bi-<?= (int) $y['is_closed'] === 1 ? 'unlock' : 'lock' ?>"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$this->data['years']): ?>
                    <tr><td colspan="6"><div class="empty-state"><i class="bi bi-calendar-x"></i>No financial years yet.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
