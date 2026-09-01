<?php /** @var app\Core\View $this */ ?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Branches — <?= $this->e($this->data['company']['name'] ?? '') ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Branches</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <a href="<?= $this->url('/branches/new') ?>" class="btn btn-primary btn-icon">
            <i class="bi bi-plus-lg"></i> New Branch
        </a>
    </div>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-diagram-2"></i>
    <span>Branch-specific accounting and inventory remain identifiable. Reports support Consolidated / Head Office / Branch views (reporting phase).</span>
</div>

<div class="row g-3">
<?php foreach ($this->data['branches'] as $b): ?>
    <div class="col-md-6 col-xl-4">
        <div class="erp-card h-100">
            <div class="erp-card-body">
                <div class="d-flex align-items-start gap-2">
                    <span class="stat-icon icon-blue" style="width:38px;height:38px"><i class="bi bi-building"></i></span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">
                            <?= $this->e($b['name']) ?>
                            <?php if ((int) $b['is_head_office'] === 1): ?>
                                <span class="badge badge-soft-info">Head Office</span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted">Code: <b><?= $this->e($b['code']) ?></b></div>
                        <div class="small text-muted mt-1"><?= $this->e($b['address'] ?: 'No address') ?></div>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3 align-items-center">
                    <?php if ((int) $b['id'] === (int) $this->data['currentId']): ?>
                        <span class="badge badge-soft-success">Active context</span>
                    <?php else: ?>
                        <form method="post" action="<?= $this->url('/settings/branch/switch') ?>" class="d-inline">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="branch_id" value="<?= (int) $b['id'] ?>">
                            <button class="btn btn-sm btn-outline-primary btn-icon"><i class="bi bi-arrow-right-circle"></i> Use</button>
                        </form>
                    <?php endif; ?>
                    <a href="<?= $this->url('/branches/' . (int) $b['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary btn-icon ms-auto">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                    <?php if ((int) $b['is_head_office'] !== 1): ?>
                        <form method="post" action="<?= $this->url('/branches/' . (int) $b['id'] . '/delete') ?>" class="d-inline"
                              data-confirm="Delete branch '<?= $this->e($b['name']) ?>'? Transactions are kept for audit but the branch is removed.">
                            <?= $this->csrfField() ?>
                            <button class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if (!$this->data['branches']): ?>
    <div class="col-12">
        <div class="erp-card"><div class="erp-card-body">
            <div class="empty-state"><i class="bi bi-building"></i>No branches yet. Add your first branch.</div>
        </div></div>
    </div>
<?php endif; ?>
</div>
