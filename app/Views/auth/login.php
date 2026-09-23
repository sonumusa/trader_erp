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
        <div class="input-group">
            <input type="password" class="form-control" id="password" name="password" required
                   placeholder="••••••••">
            <button type="button" class="btn btn-outline-secondary" id="togglePassword"
                    aria-label="Show password" title="Show password">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <button type="submit" class="btn btn-primary w-100 btn-lg">
        <i class="bi bi-box-arrow-in-right me-1"></i> Sign in
    </button>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const password = document.getElementById('password');
    const toggle = document.getElementById('togglePassword');
    if (!password || !toggle) return;
    toggle.addEventListener('click', () => {
        const visible = password.type === 'text';
        password.type = visible ? 'password' : 'text';
        toggle.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
        toggle.title = visible ? 'Show password' : 'Hide password';
        toggle.querySelector('i').className = visible ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
});
</script>
