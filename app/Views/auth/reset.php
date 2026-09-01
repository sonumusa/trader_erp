<?php /** @var app\Core\View $this */
$email = $this->data['email'];
$token = $this->data['token'];
?>
<?php $this->layout('auth'); ?>
<form method="post" action="<?= $this->url('/forgot-password/reset') ?>" autocomplete="off">
    <?= $this->csrfField() ?>
    <input type="hidden" name="email" value="<?= $this->e($email) ?>">
    <input type="hidden" name="token" value="<?= $this->e($token) ?>">

    <div class="mb-3">
        <label class="form-label" for="password">New password</label>
        <input type="password" class="form-control" id="password" name="password" required autofocus
               autocomplete="new-password" placeholder="At least <?= (int) config('security.password_min_length', 8) ?> characters">
    </div>
    <div class="mb-3">
        <label class="form-label" for="password_confirmation">Confirm new password</label>
        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required
               autocomplete="new-password" placeholder="Repeat the password">
    </div>

    <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-check-lg me-1"></i> Reset password
    </button>
</form>
