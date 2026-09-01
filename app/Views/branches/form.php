<?php /** @var app\Core\View $this */
$branch = $this->data['branch'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1><?= $branch ? 'Edit Branch' : 'New Branch' ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/branches') ?>">Branches</a></li>
                <li class="breadcrumb-item active"><?= $branch ? 'Edit' : 'New' ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="erp-card">
            <div class="erp-card-body">
                <form method="post" action="<?= $branch ? $this->url('/branches/' . (int) $branch['id']) : $this->url('/branches') ?>">
                    <?= $this->csrfField() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Branch name <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required
                                   value="<?= $this->e($branch['name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="code">Branch code <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="code" name="code" maxlength="20" required
                                   value="<?= $this->e($branch['code'] ?? '') ?>">
                            <div class="form-hint">Used in document numbers, e.g. KHI-SI-26-000001.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address">Address</label>
                            <input type="text" class="form-control" id="address" name="address"
                                   value="<?= $this->e($branch['address'] ?? '') ?>">
                        </div>
                        <?php if ($branch): ?>
                        <div class="col-md-6">
                            <label class="form-label" for="is_active">Status</label>
                            <select class="form-select" id="is_active" name="is_active">
                                <option value="1" <?= (int) $branch['is_active'] === 1 ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= (int) $branch['is_active'] === 0 ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i class="bi bi-check-lg"></i> <?= $branch ? 'Save Changes' : 'Create Branch' ?>
                        </button>
                        <a href="<?= $this->url('/branches') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
