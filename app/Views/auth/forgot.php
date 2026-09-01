<?php /** @var app\Core\View $this */ ?>
<?php $this->layout('auth'); ?>
<form method="post" action="<?= $this->url('/forgot-password') ?>" autocomplete="off">
    <?= $this->csrfField() ?>

    <div class="mb-3">
        <label class="form-label" for="email">Email address</label>
        <input type="email" class="form-control" id="email" name="email" required autofocus
               placeholder="you@company.com">
    </div>

    <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-envelope me-1"></i> Send reset link
    </button>
    <p class="text-center mt-3 mb-0">
        <a href="<?= $this->url('/login') ?>" class="small">Back to sign in</a>
    </p>
</form>
