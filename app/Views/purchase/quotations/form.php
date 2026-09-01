<?php /** @var app\Core\View $this */
$suppliers = $this->data['suppliers'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>New Purchase Quotation</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/purchase/quotations') ?>">Purchase Quotations</a></li>
                <li class="breadcrumb-item active">New</li>
            </ol>
        </nav>
    </div>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-info-circle"></i>
    <span>Capture a supplier's quotation for comparison. It has no stock or accounting effect.</span>
</div>

<form method="post" action="<?= $this->url('/purchase/quotations') ?>">
    <?= $this->csrfField() ?>

    <div class="erp-card mb-3">
        <div class="erp-card-header"><h6><i class="bi bi-person-lines-fill me-1 text-primary"></i> Quotation details</h6></div>
        <div class="erp-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="supplier_id">Supplier <span class="required-star">*</span></label>
                    <select class="form-select" id="supplier_id" name="supplier_id" required>
                        <option value="">— Select supplier —</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= $this->e($s['code'] . ' — ' . $s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="quotation_date">Quotation date <span class="required-star">*</span></label>
                    <input type="date" class="form-control" id="quotation_date" name="quotation_date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="narration">Narration</label>
                    <input type="text" class="form-control" id="narration" name="narration" maxlength="500">
                </div>
            </div>
        </div>
    </div>

    <div class="erp-card mb-3">
        <div class="erp-card-header"><h6><i class="bi bi-list-check me-1 text-primary"></i> Items</h6></div>
        <?php
        $prefillLines = $this->data['prefillLines'] ?? [];
        $mode = 'purchase';
        $warehouseSelect = false;
        $warehouseOptions = '';
        $totalsId = 'pqTotals';
        include APP_ROOT . '/app/Views/purchase/_lines.php';
        ?>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> Save Quotation</button>
        <a href="<?= $this->url('/purchase/quotations') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
