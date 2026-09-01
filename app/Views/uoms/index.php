<?php /** @var app\Core\View $this */ ?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Units of Measure</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">UOM</li>
            </ol>
        </nav>
    </div>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-info-circle"></i>
    <span>UOMs are the building blocks for items. Item-specific conversions (e.g. 1 Carton = 24 Pieces) are configured on each item in Phase 5.</span>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="erp-card">
            <div class="erp-card-header"><h6><i class="bi bi-plus-circle me-1 text-primary"></i> Add UOM</h6></div>
            <div class="erp-card-body">
                <form method="post" action="<?= $this->url('/uoms') ?>">
                    <?= $this->csrfField() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="name">Name <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required maxlength="60" placeholder="e.g. Piece">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="code">Code <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="code" name="code" required maxlength="20" placeholder="e.g. PCS">
                            <div class="form-hint">Short code, stored uppercase.</div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-plus-lg"></i> Add UOM</button>
                    </div>
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
                            <tr><th>Name</th><th>Code</th><th>Items using it</th><th class="actions-cell">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->data['uoms'] as $u): ?>
                            <tr>
                                <td class="fw-semibold"><?= $this->e($u['name']) ?></td>
                                <td><code><?= $this->e($u['code']) ?></code></td>
                                <td><span class="badge badge-soft-neutral"><?= (int) $u['item_count'] ?></span></td>
                                <td class="actions-cell">
                                    <button class="btn btn-sm btn-outline-primary btn-icon" title="Edit"
                                            data-bs-toggle="modal" data-bs-target="#editModal"
                                            data-id="<?= (int) $u['id'] ?>"
                                            data-name="<?= $this->e($u['name']) ?>"
                                            data-code="<?= $this->e($u['code']) ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ((int) $u['item_count'] === 0): ?>
                                        <form method="post" action="<?= $this->url('/uoms/' . (int) $u['id'] . '/delete') ?>" class="d-inline" data-confirm="Delete UOM '<?= $this->e($u['name']) ?>'?">
                                            <?= $this->csrfField() ?>
                                            <button class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$this->data['uoms']): ?>
                            <tr><td colspan="4"><div class="empty-state"><i class="bi bi-rulers"></i>No UOMs yet.</div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="" id="uomEditForm">
                <?= $this->csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Edit UOM</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="edit_name">Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required maxlength="60">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="edit_code">Code</label>
                        <input type="text" class="form-control" id="edit_code" name="code" required maxlength="20">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('editModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', (e) => {
        const btn = e.relatedTarget;
        document.getElementById('edit_name').value = btn.dataset.name;
        document.getElementById('edit_code').value = btn.dataset.code;
        document.getElementById('uomEditForm').action = '/uoms/' + btn.dataset.id;
    });
});
</script>
