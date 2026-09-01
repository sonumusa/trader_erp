<?php /** @var app\Core\View $this */
$options = $this->data['options'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Accounting Defaults</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/accounts') ?>">Chart of Accounts</a></li>
                <li class="breadcrumb-item active">Accounting Defaults</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <a href="<?= $this->url('/accounts') ?>" class="btn btn-outline-secondary btn-icon">
            <i class="bi bi-diagram-3"></i> Chart of Accounts
        </a>
    </div>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-magic"></i>
    <span>These defaults let the system <b>automatically choose the right account</b> for normal transactions — users never pick GL accounts manually.
        The engine falls back: Item account → Item Group account → Company default.</span>
</div>

<form method="post" action="<?= $this->url('/accounts/defaults') ?>">
    <?= $this->csrfField() ?>

    <div class="erp-card">
        <div class="erp-card-body">
            <div class="row g-3">
                <?php foreach ($options as $key => $opt): ?>
                    <div class="col-md-6 col-lg-4">
                        <label class="form-label" for="def-<?= $this->e($key) ?>"><?= $this->e($opt['label']) ?></label>
                        <select class="form-select" id="def-<?= $this->e($key) ?>" name="defaults[<?= $this->e($key) ?>]">
                            <option value="">— Not set —</option>
                            <?php foreach ($opt['choices'] as $acc): ?>
                                <option value="<?= (int) $acc['id'] ?>"
                                        <?= $opt['account'] && (int) $opt['account']['account_id'] === (int) $acc['id'] ? 'selected' : '' ?>>
                                    <?= $this->e($acc['code'] . ' — ' . $acc['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="erp-card-footer">
            <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> Save Defaults</button>
            <span class="form-hint ms-2">The Bank Account default is linked to Payment Modes in Phase 4.</span>
        </div>
    </div>
</form>
