<?php /** @var app\Core\View $this */
$closedTo = $this->data['closedTo'];
$canSet = $this->data['canSet'];
$canOverride = $this->data['canOverride'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Closing Period</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/settings') ?>">Settings</a></li>
                <li class="breadcrumb-item active">Closing Period</li>
            </ol>
        </nav>
    </div>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-lock"></i>
    <span>Set a <b>Closed To</b> date to lock all transactions on or before that date. Normal users cannot
        create, edit, delete or cancel documents inside the closed period. Authorized roles can override — every
        override is recorded in the audit trail.</span>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="erp-card">
            <div class="erp-card-header"><h6><i class="bi bi-calendar-lock me-1 text-primary"></i> Closed To Date</h6></div>
            <div class="erp-card-body">
                <?php if ($closedTo): ?>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge badge-soft-danger" style="font-size:.95rem">
                            <i class="bi bi-lock-fill me-1"></i>Closed to <?= $this->e(format_date($closedTo)) ?>
                        </span>
                    </div>
                    <p class="small text-muted">Transactions dated <b><?= $this->e(format_date($closedTo)) ?></b> or earlier are locked for normal users.</p>
                <?php else: ?>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge badge-soft-success" style="font-size:.95rem">
                            <i class="bi bi-unlock me-1"></i>No closing period set
                        </span>
                    </div>
                    <p class="small text-muted">All dates are currently open.</p>
                <?php endif; ?>

                <?php if ($canSet): ?>
                    <form method="post" action="<?= $this->url('/settings/closing') ?>" class="row g-2 align-items-end">
                        <?= $this->csrfField() ?>
                        <div class="col-auto">
                            <label class="form-label mb-0" for="closed_to"><?= $closedTo ? 'Change closed-to date' : 'Set closed-to date' ?></label>
                            <input type="date" class="form-control" id="closed_to" name="closed_to" value="<?= $this->e($closedTo ?? '') ?>">
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-primary btn-icon"><i class="bi bi-lock"></i> <?= $closedTo ? 'Update' : 'Close period' ?></button>
                        </div>
                        <?php if ($closedTo): ?>
                            <div class="col-auto">
                                <button class="btn btn-outline-secondary btn-icon" type="submit" name="closed_to" value=""
                                        onclick="return confirm('Clear the closing period? All dates will become open again.');">
                                    <i class="bi bi-unlock"></i> Clear
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <p class="form-hint">You do not have permission to change the closing period.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="erp-card">
            <div class="erp-card-header"><h6><i class="bi bi-shield-check me-1 text-primary"></i> Who can override</h6></div>
            <div class="erp-card-body">
                <?php if ($canOverride): ?>
                    <p class="small">Your role can <b>override</b> the closing period. Any transaction you save
                        inside the closed period is recorded in the audit trail as an override.</p>
                <?php else: ?>
                    <p class="small">Your role <b>cannot</b> override the closing period. Attempting to save a
                        transaction inside the closed period will be blocked with a clear message.</p>
                <?php endif; ?>
                <div class="audit-section mt-3">
                    <span><b>Rule:</b> transactions ≤ closed-to date are locked</span>
                    <span><b>Override:</b> permission <code>settings.closing.override</code></span>
                </div>
            </div>
        </div>
    </div>
</div>
