<?php /** @var app\Core\View $this */ ?>
<?php $this->layout('auth'); ?>
<form method="post" action="<?= $this->url('/login') ?>" autocomplete="off">
    <?= $this->csrfField() ?>

    <div class="mb-3">
        <label class="form-label" for="email">Email address</label>
        <input type="email" class="form-control" id="email" name="email" required autofocus
               value="<?= $this->e(old('email')) ?>" placeholder="you@company.com">
    </div>

    <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input type="password" class="form-control" id="password" name="password" required
               placeholder="••••••••">
    </div>

    <button type="submit" class="btn btn-primary w-100 btn-lg">
        <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
    </button>
</form>
