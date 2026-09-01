<?php /** @var app\Core\View $this */
$user = $this->data['user'];
$editing = $this->data['editing'];
$roles = $this->data['roles'];
$companies = $this->data['companies'];
$old = \app\Core\Session::get('_old_input', []);
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1><?= $editing ? 'Edit User' : 'New User' ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/users') ?>">Users</a></li>
                <li class="breadcrumb-item active"><?= $editing ? 'Edit' : 'New' ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="erp-card">
            <div class="erp-card-body">
                <form method="post" action="<?= $editing ? $this->url('/users/' . (int) $user['id']) : $this->url('/users') ?>">
                    <?= $this->csrfField() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Full name <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required
                                   value="<?= $this->e($old['name'] ?? ($user['name'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email address <span class="required-star">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   value="<?= $this->e($old['email'] ?? ($user['email'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="role_id">Role <span class="required-star">*</span></label>
                            <select class="form-select" id="role_id" name="role_id" required>
                                <option value="">— Select role —</option>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= (int) $r['id'] ?>" <?= (int) ($old['role_id'] ?? ($user['role_id'] ?? 0)) === (int) $r['id'] ? 'selected' : '' ?>>
                                        <?= $this->e($r['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="company_id">Company</label>
                            <select class="form-select" id="company_id" name="company_id">
                                <option value="">— All companies —</option>
                                <?php foreach ($companies as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>" <?= (int) ($old['company_id'] ?? ($user['company_id'] ?? 0)) === (int) $c['id'] ? 'selected' : '' ?>>
                                        <?= $this->e($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password">
                                <?= $editing ? 'New password' : 'Password' ?> <span class="required-star"><?= $editing ? '' : '*' ?></span>
                            </label>
                            <input type="password" class="form-control" id="password" name="password"
                                   <?= $editing ? '' : 'required' ?> autocomplete="new-password"
                                   placeholder="<?= $editing ? 'Leave blank to keep current password' : '' ?>">
                            <div class="form-hint">Minimum <?= (int) config('security.password_min_length', 8) ?> characters with letters and numbers.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="status">Status <span class="required-star">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?= ($old['status'] ?? ($user['status'] ?? 'active')) === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($old['status'] ?? ($user['status'] ?? '')) === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i class="bi bi-check-lg"></i> <?= $editing ? 'Save Changes' : 'Create User' ?>
                        </button>
                        <a href="<?= $this->url('/users') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
