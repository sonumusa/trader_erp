<?php /** @var app\Core\View $this */ ?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Item Groups</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/items') ?>">Items</a></li>
                <li class="breadcrumb-item active">Item Groups</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="erp-card">
            <div class="erp-card-header"><h6><i class="bi bi-plus-circle me-1 text-primary"></i> Add group</h6></div>
            <div class="erp-card-body">
                <form method="post" action="<?= $this->url('/item-groups') ?>">
                    <?= $this->csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label" for="name">Group name <span class="required-star">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required maxlength="120" placeholder="e.g. Electronics">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="code">Code</label>
                        <input type="text" class="form-control" id="code" name="code" maxlength="30" placeholder="e.g. ELEC">
                    </div>
                    <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-plus-lg"></i> Add group</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="erp-card">
            <div class="erp-card-body p-0">
                <div class="table-responsive erp-table-wrap">
                    <table class="table erp-table mb-0">
                        <thead>
                            <tr><th>Group</th><th>Code</th><th>Items</th><th class="actions-cell">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->data['groups'] as $g): ?>
                            <tr>
                                <td class="fw-semibold"><?= $this->e($g['name']) ?></td>
                                <td><code><?= $this->e($g['code'] ?: '—') ?></code></td>
                                <td><span class="badge badge-soft-neutral"><?= (int) $g['item_count'] ?></span></td>
                                <td class="actions-cell">
                                    <a href="<?= $this->url('/item-groups/' . (int) $g['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Edit (incl. account overrides)">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ((int) $g['item_count'] === 0): ?>
                                        <form method="post" action="<?= $this->url('/item-groups/' . (int) $g['id'] . '/delete') ?>" class="d-inline" data-confirm="Delete group '<?= $this->e($g['name']) ?>'?">
                                            <?= $this->csrfField() ?>
                                            <button class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$this->data['groups']): ?>
                            <tr><td colspan="4"><div class="empty-state"><i class="bi bi-folder2"></i>No item groups yet.</div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
