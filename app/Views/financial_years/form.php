<?php /** @var app\Core\View $this */
$year = $this->data['year'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1><?= $year ? 'Edit Financial Year' : 'New Financial Year' ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/settings/financial-years') ?>">Financial Years</a></li>
                <li class="breadcrumb-item active"><?= $year ? 'Edit' : 'New' ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="erp-card">
            <div class="erp-card-body">
                <form method="post" action="<?= $year ? $this->url('/settings/financial-years/' . (int) $year['id']) : $this->url('/settings/financial-years') ?>">
                    <?= $this->csrfField() ?>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="name">Name <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required
                                   value="<?= $this->e($year['name'] ?? '') ?>" placeholder="e.g. 2026-2027">
                            <div class="form-hint">A label for the period, e.g. 2026-2027.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="start_date">Start date <span class="required-star">*</span></label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required
                                   value="<?= $this->e($year['start_date'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="end_date">End date <span class="required-star">*</span></label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required
                                   value="<?= $this->e($year['end_date'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                       <?= !$year || (int) $year['is_active'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="is_active">Make this the active financial year</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i class="bi bi-check-lg"></i> <?= $year ? 'Save Changes' : 'Create Financial Year' ?>
                        </button>
                        <a href="<?= $this->url('/settings/financial-years') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
