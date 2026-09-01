<?php
/** @var app\Core\View $this */
$title = $this->data['title'] ?? 'TradeERP';
$flash = $this->data['flash'] ?? [];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $this->e($title) ?> — TradeERP</title>
<link rel="stylesheet" href="<?= $this->asset('vendor/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= $this->asset('vendor/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= $this->asset('css/app.css') ?>">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="brand-mark"><i class="bi bi-boxes"></i></div>
            <h4>TradeERP</h4>
            <p>Trading, Inventory &amp; Accounting</p>
        </div>

        <?php foreach ($flash as $type => $message): ?>
            <div class="erp-alert erp-alert-<?= $this->e($type) ?>" data-auto-dismiss>
                <i class="bi bi-<?= $type === 'success' ? 'check-circle' : 'x-circle' ?>"></i>
                <span><?= $this->e($message) ?></span>
            </div>
        <?php endforeach; ?>

        <?= $this->content() ?>

        <div class="auth-foot">
            &copy; <?= date('Y') ?> TradeERP &middot; v<?= $this->e(APP_VERSION) ?>
        </div>
    </div>
</div>
<script src="<?= $this->asset('vendor/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= $this->asset('js/app.js') ?>"></script>
</body>
</html>
