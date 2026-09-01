<?php /** @var app\Core\View $this */
$supplier = $this->data['supplier'];
$old = \app\Core\Session::get('_old_input', []);
$val = fn(string $k, string $col = '') => $this->e($old[$k] ?? ($supplier[$col ?: $k] ?? ''));
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1><?= $supplier ? 'Edit Supplier' : 'New Supplier' ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/suppliers') ?>">Suppliers</a></li>
                <li class="breadcrumb-item active"><?= $supplier ? 'Edit' : 'New' ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-9">
        <div class="erp-card">
            <div class="erp-card-body">
                <form method="post" action="<?= $supplier ? $this->url('/suppliers/' . (int) $supplier['id']) : $this->url('/suppliers') ?>">
                    <?= $this->csrfField() ?>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="name">Supplier name <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required maxlength="150" value="<?= $val('name') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="code">Code</label>
                            <input type="text" class="form-control" id="code" name="code" maxlength="30" value="<?= $val('code') ?>" placeholder="Auto if empty">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="type">Type</label>
                            <select class="form-select" id="type" name="type">
                                <option value="business" <?= ($supplier['type'] ?? 'business') === 'business' ? 'selected' : '' ?>>Business</option>
                                <option value="individual" <?= ($supplier['type'] ?? '') === 'individual' ? 'selected' : '' ?>>Individual</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="phone">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?= $val('phone') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="mobile">Mobile</label>
                            <input type="text" class="form-control" id="mobile" name="mobile" value="<?= $val('mobile') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= $val('email') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="city">City</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?= $val('city') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address">Address</label>
                            <input type="text" class="form-control" id="address" name="address" value="<?= $val('address') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="tax_number">Tax number (NTN)</label>
                            <input type="text" class="form-control" id="tax_number" name="tax_number" value="<?= $val('tax_number') ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label" for="status">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?= ($supplier['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($supplier['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>

                        <?php if (!$supplier): ?>
                        <div class="col-12 mt-1"><hr class="my-2"></div>
                        <div class="col-md-6">
                            <label class="form-label" for="opening_balance">Opening balance</label>
                            <input type="number" step="0.01" class="form-control" id="opening_balance" name="opening_balance" value="0.00">
                            <div class="form-hint">Positive = you owe the supplier. Posted to Accounts Payable with the opening equity entry.</div>
                        </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label" for="notes">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"><?= $val('notes') ?></textarea>
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i class="bi bi-check-lg"></i> <?= $supplier ? 'Save Changes' : 'Create Supplier' ?>
                        </button>
                        <a href="<?= $this->url('/suppliers') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($supplier): ?>
    <div class="col-lg-3">
        <div class="erp-card">
            <div class="erp-card-header"><h6><i class="bi bi-info-circle me-1 text-primary"></i> Summary</h6></div>
            <div class="erp-card-body small">
                <div class="d-flex justify-content-between"><span class="text-muted">Code</span><b><?= $this->e($supplier['code']) ?></b></div>
                <div class="d-flex justify-content-between mt-1"><span class="text-muted">Opening balance</span><b><?= $this->e(format_money($supplier['opening_balance'])) ?></b></div>
                <hr class="my-2">
                <a href="<?= $this->url('/suppliers/' . (int) $supplier['id'] . '/ledger') ?>" class="btn btn-sm btn-outline-primary w-100 btn-icon">
                    <i class="bi bi-journal-text"></i> View Ledger
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
