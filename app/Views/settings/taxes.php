<?php /** @var app\Core\View $this */ ?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Taxes</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/settings') ?>">Settings</a></li>
                <li class="breadcrumb-item active">Taxes</li>
            </ol>
        </nav>
    </div>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-info-circle"></i>
    <span>Tax rates are configurable per company (e.g. Sales Tax 17%). Attach a tax category to items; sales & purchase documents use it automatically in Phases 6–7.</span>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="erp-card">
            <div class="erp-card-header"><h6><i class="bi bi-plus-circle me-1 text-primary"></i> Add tax</h6></div>
            <div class="erp-card-body">
                <form method="post" action="<?= $this->url('/settings/taxes') ?>">
                    <?= $this->csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label" for="name">Name <span class="required-star">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required maxlength="80" placeholder="e.g. Sales Tax">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="tax_type">Tax type <span class="required-star">*</span></label>
                        <input type="text" class="form-control" id="tax_type" name="tax_type" required maxlength="40" value="sales_tax">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="rate">Rate (%) <span class="required-star">*</span></label>
                        <input type="number" step="0.0001" min="0" max="100" class="form-control" id="rate" name="rate" required value="0">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6"><label class="form-label" for="effective_from">Effective from</label><input type="date" class="form-control" id="effective_from" name="effective_from"></div>
                        <div class="col-md-6"><label class="form-label" for="effective_to">Effective to</label><input type="date" class="form-control" id="effective_to" name="effective_to"></div>
                    </div>
                    <div class="mb-3"><label class="form-label" for="description">Description</label><input type="text" class="form-control" id="description" name="description" maxlength="255"></div>
                    <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-plus-lg"></i> Add tax</button>
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
                            <tr><th>Name</th><th>Type</th><th class="num">Rate</th><th>Effective</th><th>Status</th><th class="actions-cell">Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->data['taxes'] as $t): ?>
                            <tr>
                                <td class="fw-semibold"><?= $this->e($t['name']) ?></td>
                                <td><?= $this->e($t['tax_type'] ?? 'sales_tax') ?></td>
                                <td class="num"><?= $this->e(rtrim(rtrim(number_format((float) $t['rate'], 4), '0'), '.')) ?>%</td>
                                <td class="small"><?= $this->e(($t['effective_from'] ?? '') ?: 'Any') ?><?= !empty($t['effective_to']) ? ' - ' . $this->e($t['effective_to']) : '' ?></td>
                                <td>
                                    <?php if ((int) $t['is_active'] === 1): ?>
                                        <span class="badge badge-soft-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-soft-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <button class="btn btn-sm btn-outline-primary btn-icon" title="Edit"
                                            data-bs-toggle="modal" data-bs-target="#taxEditModal"
                                            data-id="<?= (int) $t['id'] ?>"
                                            data-name="<?= $this->e($t['name']) ?>"
                                            data-rate="<?= $this->e($t['rate']) ?>"
                                            data-type="<?= $this->e($t['tax_type'] ?? 'sales_tax') ?>"
                                            data-from="<?= $this->e($t['effective_from'] ?? '') ?>"
                                            data-to="<?= $this->e($t['effective_to'] ?? '') ?>"
                                            data-description="<?= $this->e($t['description'] ?? '') ?>"
                                            data-active="<?= (int) $t['is_active'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="post" action="<?= $this->url('/settings/taxes/' . (int) $t['id'] . '/delete') ?>" class="d-inline" data-confirm="Delete tax '<?= $this->e($t['name']) ?>'?">
                                        <?= $this->csrfField() ?>
                                        <button class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$this->data['taxes']): ?>
                            <tr><td colspan="6"><div class="empty-state"><i class="bi bi-percent"></i>No taxes configured.</div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="taxEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="" id="taxEditForm">
                <?= $this->csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Edit tax</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="te-type">Tax type</label>
                        <input type="text" class="form-control" id="te-type" name="tax_type" maxlength="40" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="te-from">Effective from</label>
                            <input type="date" class="form-control" id="te-from" name="effective_from">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="te-to">Effective to</label>
                            <input type="date" class="form-control" id="te-to" name="effective_to">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="te-description">Description</label>
                        <input type="text" class="form-control" id="te-description" name="description" maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="te-name">Name</label>
                        <input type="text" class="form-control" id="te-name" name="name" required maxlength="80">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="te-rate">Rate (%)</label>
                        <input type="number" step="0.0001" min="0" max="100" class="form-control" id="te-rate" name="rate" required>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="te-active" name="is_active" value="1">
                        <label class="form-check-label small" for="te-active">Active</label>
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
    const modal = document.getElementById('taxEditModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', (e) => {
        const b = e.relatedTarget;
        document.getElementById('te-name').value = b.dataset.name;
        document.getElementById('te-rate').value = b.dataset.rate;
        document.getElementById('te-type').value = b.dataset.type || 'sales_tax';
        document.getElementById('te-from').value = b.dataset.from || '';
        document.getElementById('te-to').value = b.dataset.to || '';
        document.getElementById('te-description').value = b.dataset.description || '';
        document.getElementById('te-active').checked = b.dataset.active === '1';
        document.getElementById('taxEditForm').action = '/settings/taxes/' + b.dataset.id;
    });
});
</script>
