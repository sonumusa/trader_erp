<?php /** @var app\Core\View $this */
$warehouse = $this->data['warehouse'];
$branches = $this->data['branches'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1><?= $warehouse ? 'Edit Warehouse' : 'New Warehouse' ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/warehouses') ?>">Warehouses</a></li>
                <li class="breadcrumb-item active"><?= $warehouse ? 'Edit' : 'New' ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="erp-card">
            <div class="erp-card-body">
                <form method="post" action="<?= $warehouse ? $this->url('/warehouses/' . (int) $warehouse['id']) : $this->url('/warehouses') ?>">
                    <?= $this->csrfField() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Warehouse name <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required maxlength="120"
                                   value="<?= $this->e($warehouse['name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="code">Code <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="code" name="code" required maxlength="30"
                                   value="<?= $this->e($warehouse['code'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address">Address</label>
                            <input type="text" class="form-control" id="address" name="address"
                                   value="<?= $this->e($warehouse['address'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="branch_id">Branch</label>
                            <select class="form-select" id="branch_id" name="branch_id">
                                <option value="">— Head Office —</option>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?= (int) $b['id'] ?>" <?= (int) ($warehouse['branch_id'] ?? 0) === (int) $b['id'] ? 'selected' : '' ?>>
                                        <?= $this->e($b['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($warehouse): ?>
                        <div class="col-md-6">
                            <label class="form-label" for="status">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?= $warehouse['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $warehouse['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        <?php if (!$warehouse): ?>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1">
                                <label class="form-check-label small" for="is_default">Set as default warehouse</label>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i class="bi bi-check-lg"></i> <?= $warehouse ? 'Save Changes' : 'Create Warehouse' ?>
                        </button>
                        <a href="<?= $this->url('/warehouses') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
