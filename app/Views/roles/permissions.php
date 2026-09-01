<?php /** @var app\Core\View $this */
$role = $this->data['role'];
$catalogue = $this->data['catalogue'];
$grantedKeys = $this->data['grantedKeys'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Permissions — <?= $this->e($role['name']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/roles') ?>">Roles</a></li>
                <li class="breadcrumb-item active"><?= $this->e($role['name']) ?></li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="checkAllBtn">Check all</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllBtn">Clear all</button>
    </div>
</div>

<?php if ((int) $role['is_system'] === 1 && $role['slug'] === 'super-admin'): ?>
    <div class="erp-alert erp-alert-info">
        <i class="bi bi-shield-lock"></i>
        <span>Super Admin always has full access to every module. This role's permissions cannot be changed.</span>
    </div>
<?php else: ?>
    <form method="post" action="<?= $this->url('/roles/' . (int) $role['id'] . '/permissions') ?>" id="permForm">
        <?= $this->csrfField() ?>

        <div class="row g-3">
            <?php foreach ($catalogue as $module => $docs): ?>
                <div class="col-lg-6 col-xl-4">
                    <div class="erp-card h-100">
                        <div class="erp-card-header">
                            <h6 class="text-uppercase" style="font-size:.72rem"><?= $this->e(ucfirst($module)) ?></h6>
                            <label class="ms-auto form-check-label small mb-0">
                                <input type="checkbox" class="form-check-input module-toggle" data-module="<?= $this->e($module) ?>">
                                All
                            </label>
                        </div>
                        <div class="erp-card-body">
                            <?php foreach ($docs as $document => $actions): ?>
                                <div class="mb-2">
                                    <div class="fw-semibold small mb-1"><?= $this->e(str_replace('_', ' ', ucfirst($document))) ?></div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($actions as $action): ?>
                                            <?php $key = $module . '.' . $document . '.' . $action; ?>
                                            <label class="form-check form-check-inline mb-1">
                                                <input class="form-check-input perm-check module-<?= $this->e($module) ?>"
                                                       type="checkbox" name="perms[]"
                                                       value="<?= $this->e($key) ?>"
                                                       <?= isset($grantedKeys[$key]) ? 'checked' : '' ?>>
                                                <span class="form-check-label small"><?= $this->e(ucfirst($action)) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> Save Permissions</button>
            <a href="<?= $this->url('/roles') ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
<?php endif; ?>

<script>
(function () {
    const form = document.getElementById('permForm');
    if (!form) return;

    // Module "All" toggles
    document.querySelectorAll('.module-toggle').forEach((cb) => {
        cb.addEventListener('change', () => {
            document.querySelectorAll('.perm-check.module-' + cb.dataset.module).forEach((p) => { p.checked = cb.checked; });
        });
    });

    document.getElementById('checkAllBtn').addEventListener('click', () => {
        form.querySelectorAll('.perm-check').forEach((p) => { p.checked = true; });
    });
    document.getElementById('clearAllBtn').addEventListener('click', () => {
        form.querySelectorAll('.perm-check').forEach((p) => { p.checked = false; });
    });
})();
</script>
