<?php /** @var app\Core\View $this */
$account = $this->data['account'];
$groups = $this->data['groups'];
$types = ['asset' => 'Asset', 'liability' => 'Liability', 'equity' => 'Equity', 'income' => 'Income', 'expense' => 'Expense'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1><?= $account ? 'Edit Account' : 'New Account' ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/accounts') ?>">Chart of Accounts</a></li>
                <li class="breadcrumb-item active"><?= $account ? 'Edit' : 'New' ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="erp-card">
            <div class="erp-card-body">
                <form method="post" action="<?= $account ? $this->url('/accounts/' . (int) $account['id']) : $this->url('/accounts') ?>">
                    <?= $this->csrfField() ?>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="code">Account code <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="code" name="code" required maxlength="30"
                                   value="<?= $this->e($account['code'] ?? '') ?>" placeholder="e.g. 6100">
                            <div class="form-hint">Unique within the company.</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="name">Account name <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required maxlength="150"
                                   value="<?= $this->e($account['name'] ?? '') ?>" placeholder="e.g. Office Rent">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="account_type">Account type <span class="required-star">*</span></label>
                            <select class="form-select" id="account_type" name="account_type" required>
                                <?php foreach ($types as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= ($account['account_type'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="parent_id">Parent group</label>
                            <select class="form-select" id="parent_id" name="parent_id">
                                <option value="">— None (top level) —</option>
                                <?php foreach ($groups as $g): ?>
                                    <option value="<?= (int) $g['id'] ?>"
                                            <?= (int) ($account['parent_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>>
                                        <?= $this->e($g['code'] . ' — ' . $g['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_group" name="is_group" value="1"
                                       <?= $account && (int) $account['is_group'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="is_group">Group account (has sub-accounts)</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_cash" name="is_cash" value="1"
                                       <?= $account && (int) $account['is_cash'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="is_cash">Cash account</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_bank" name="is_bank" value="1"
                                       <?= $account && (int) $account['is_bank'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="is_bank">Bank account</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                                       <?= !$account || (int) $account['is_active'] === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="is_active">Active</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="opening_balance">Opening balance</label>
                            <input type="number" step="0.01" class="form-control" id="opening_balance" name="opening_balance"
                                   value="<?= $this->e(number_format((float) ($account['opening_balance'] ?? 0), 2, '.', '')) ?>">
                            <div class="form-hint">Recorded as a reference; actual posting happens via Opening Balances (Phase 4 wizard).</div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i class="bi bi-check-lg"></i> <?= $account ? 'Save Changes' : 'Create Account' ?>
                        </button>
                        <a href="<?= $this->url('/accounts') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
