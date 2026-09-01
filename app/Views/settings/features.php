<?php /** @var app\Core\View $this */ ?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Features</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/settings') ?>">Settings</a></li>
                <li class="breadcrumb-item active">Features</li>
            </ol>
        </nav>
    </div>
</div>

<form method="post" action="<?= $this->url('/settings/features') ?>" id="featureForm">
    <?= $this->csrfField() ?>

    <div class="erp-card">
        <div class="erp-card-header">
            <h6><i class="bi bi-toggle-on me-1 text-primary"></i> Module toggles</h6>
        </div>
        <div class="erp-card-body">
            <p class="small text-muted mb-3">
                Disabled features are hidden from the interface and cannot be accessed. Core modules
                (Sales, Purchase, Inventory, Accounting) build out over the next phases — toggling them off
                hides them until then.
            </p>
            <div class="row g-2">
                <?php foreach ($this->data['features'] as $f): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="border rounded-3 p-3 h-100 d-flex align-items-start gap-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="feat-<?= $this->e($f['key']) ?>"
                                       name="features[<?= $this->e($f['key']) ?>]" value="1"
                                       <?= (int) $f['is_enabled'] === 1 ? 'checked' : '' ?>>
                            </div>
                            <div>
                                <label class="form-check-label fw-semibold" for="feat-<?= $this->e($f['key']) ?>">
                                    <?= $this->e($f['label']) ?>
                                </label>
                                <div class="small text-muted"><?= $this->e($f['description']) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="erp-card-footer d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> Save Features</button>
            <a href="<?= $this->url('/settings') ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </div>
</form>
