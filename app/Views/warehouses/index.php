<?php /** @var app\Core\View $this */ ?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Warehouses</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Warehouses</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <a href="<?= $this->url('/warehouses/new') ?>" class="btn btn-primary btn-icon">
            <i class="bi bi-plus-lg"></i> New Warehouse
        </a>
    </div>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-info-circle"></i>
    <span>Warehouse stock movement, stock ledger and valuation arrive with the inventory engine (Phase 5).</span>
</div>

<div class="erp-card">
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <th>Warehouse</th>
                        <th>Code</th>
                        <th>Branch</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->data['warehouses'] as $w): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= $this->e($w['name']) ?></div>
                            <div class="small text-muted"><?= $this->e($w['address'] ?: 'No address') ?></div>
                        </td>
                        <td><code><?= $this->e($w['code']) ?></code></td>
                        <td><?= $this->e($w['branch_name'] ?? '—') ?></td>
                        <td>
                            <?php if ((int) $w['is_default'] === 1): ?>
                                <span class="badge badge-soft-info">Default</span>
                            <?php else: ?>
                                <span class="badge badge-soft-neutral">Standard</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($w['status'] === 'active'): ?>
                                <span class="badge badge-soft-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <a href="<?= $this->url('/warehouses/' . (int) $w['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ((int) $w['is_default'] !== 1): ?>
                                <form method="post" action="<?= $this->url('/warehouses/' . (int) $w['id'] . '/delete') ?>" class="d-inline" data-confirm="Delete warehouse '<?= $this->e($w['name']) ?>'?">
                                    <?= $this->csrfField() ?>
                                    <button class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$this->data['warehouses']): ?>
                    <tr><td colspan="6"><div class="empty-state"><i class="bi bi-building"></i>No warehouses yet.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
