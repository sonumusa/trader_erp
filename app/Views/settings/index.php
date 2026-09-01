<?php /** @var app\Core\View $this */
$groups = $this->data['groups'];
$current = [];
foreach ($groups as $name => $rows) {
    foreach ($rows as $row) {
        $current[$row['key']] = $row['value'];
    }
}
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Settings</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Settings</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="erp-card">
            <div class="erp-card-header">
                <h6><i class="bi bi-building me-1 text-primary"></i> Company &amp; General</h6>
            </div>
            <div class="erp-card-body">
                <form method="post" action="<?= $this->url('/settings') ?>">
                    <?= $this->csrfField() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="company_name">Company name <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="company_name" name="company_name" required
                                   value="<?= $this->e($current['company_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="company_code">Company code</label>
                            <input type="text" class="form-control" id="company_code" name="company_code" maxlength="20"
                                   value="<?= $this->e($current['company_code'] ?? '') ?>">
                            <div class="form-hint">Used in document numbers, e.g. LHR-SI-26-000001.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="currency">Currency</label>
                            <select class="form-select" id="currency" name="currency">
                                <?php foreach (['PKR' => 'PKR — Pakistani Rupee', 'USD' => 'USD — US Dollar', 'SAR' => 'SAR — Saudi Riyal', 'AED' => 'AED — UAE Dirham', 'GBP' => 'GBP — British Pound', 'EUR' => 'EUR — Euro'] as $code => $label): ?>
                                    <option value="<?= $code ?>" <?= ($current['currency'] ?? 'PKR') === $code ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="timezone">Timezone</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <option value="Asia/Karachi" <?= ($current['timezone'] ?? 'Asia/Karachi') === 'Asia/Karachi' ? 'selected' : '' ?>>Asia/Karachi (PKT)</option>
                                <option value="Asia/Dubai" <?= ($current['timezone'] ?? '') === 'Asia/Dubai' ? 'selected' : '' ?>>Asia/Dubai</option>
                                <option value="Asia/Riyadh" <?= ($current['timezone'] ?? '') === 'Asia/Riyadh' ? 'selected' : '' ?>>Asia/Riyadh</option>
                                <option value="UTC" <?= ($current['timezone'] ?? '') === 'UTC' ? 'selected' : '' ?>>UTC</option>
                            </select>
                        </div>

                        <div class="col-12 mt-1"><hr class="my-2"></div>

                        <div class="col-md-6">
                            <label class="form-label" for="inventory_cost_method">Inventory costing method</label>
                            <div class="input-group">
                                <select class="form-select" id="inventory_cost_method" disabled>
                                    <?php foreach (['moving_average' => 'Moving Average', 'weighted_average' => 'Weighted Average', 'fifo' => 'FIFO — First In, First Out', 'lifo' => 'LIFO — Last In, First Out'] as $val => $label): ?>
                                        <option value="<?= $val ?>" <?= ($current['inventory_cost_method'] ?? 'moving_average') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#costingModal">
                                    <i class="bi bi-arrow-repeat"></i> Change
                                </button>
                            </div>
                            <div class="form-hint">Changing the method replays the entire stock history through the new method and is recorded in the audit trail.</div>

                            <?php $costingHistory = \app\Services\InventoryEngine::costingHistory($this->data['company']['id'] ?? 0); ?>
                            <?php if ($costingHistory): ?>
                                <div class="mt-3 small">
                                    <div class="fw-semibold text-muted mb-1">Costing change history</div>
                                    <?php foreach ($costingHistory as $h): ?>
                                        <div class="d-flex justify-content-between border-bottom py-1">
                                            <span>
                                                <?= $this->e(\app\Services\InventoryEngine::costingMethodLabel($h['old_method'])) ?>
                                                <i class="bi bi-arrow-right"></i>
                                                <?= $this->e(\app\Services\InventoryEngine::costingMethodLabel($h['new_method'])) ?>
                                                <span class="text-muted">(<?= (int) $h['movements_replayed'] ?> movements)</span>
                                            </span>
                                            <span class="text-muted"><?= $this->e(format_datetime($h['created_at'])) ?> by <?= $this->e($h['changed_name'] ?? '—') ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-block">Allow negative stock</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="allow_negative_stock" name="allow_negative_stock" value="1"
                                       <?= ($current['allow_negative_stock'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="allow_negative_stock">Permit stock to go below zero</label>
                            </div>
                            <div class="form-hint">When disabled, transactions that would create negative stock are blocked with a clear message.</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="erp-card mb-3">
            <div class="erp-card-header"><h6><i class="bi bi-toggle-on me-1 text-primary"></i> Modules</h6></div>
            <div class="erp-card-body">
                <p class="small text-muted">Enable or disable application modules.</p>
                <a href="<?= $this->url('/settings/features') ?>" class="btn btn-sm btn-outline-primary btn-icon">
                    <i class="bi bi-gear"></i> Manage features
                </a>
            </div>
        </div>
        <div class="erp-card">
            <div class="erp-card-header"><h6><i class="bi bi-info-circle me-1 text-primary"></i> About</h6></div>
            <div class="erp-card-body small">
                <div class="d-flex justify-content-between"><span class="text-muted">Version</span><b><?= $this->e(APP_VERSION) ?></b></div>
                <div class="d-flex justify-content-between mt-1"><span class="text-muted">Environment</span><b><?= $this->e(strtoupper((string) config('app.env'))) ?></b></div>
                <div class="d-flex justify-content-between mt-1"><span class="text-muted">Timezone</span><b><?= $this->e((string) date_default_timezone_get()) ?></b></div>
            </div>
        </div>
    </div>
</div>

<!-- Costing method change modal — controlled revaluation -->
<div class="modal fade" id="costingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= $this->url('/inventory/costing/update') ?>">
                <?= $this->csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-repeat me-1 text-warning"></i> Change costing method</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="erp-alert erp-alert-warning mb-3">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span><b>Warn:</b> changing the costing method replays the entire stock history through the new method.
                            Historical valuation and profit figures will be re-calculated. This action is recorded in the audit trail
                            and cannot be undone automatically.</span>
                    </div>
                    <label class="form-label" for="cm-method">New costing method <span class="required-star">*</span></label>
                    <select class="form-select" id="cm-method" name="inventory_cost_method" required>
                        <?php foreach (['moving_average' => 'Moving Average', 'weighted_average' => 'Weighted Average', 'fifo' => 'FIFO — First In, First Out', 'lifo' => 'LIFO — Last In, First Out'] as $val => $label): ?>
                            <option value="<?= $val ?>" <?= ($current['inventory_cost_method'] ?? 'moving_average') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label mt-3" for="cm-note">Note (optional)</label>
                    <input type="text" class="form-control" id="cm-note" name="note" maxlength="255" placeholder="Why is the method changing?">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-arrow-repeat"></i> Change & Revalue</button>
                </div>
            </form>
        </div>
    </div>
</div>
