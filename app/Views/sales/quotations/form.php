<?php /** @var app\Core\View $this */
$customers = $this->data['customers'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>New Sales Quotation</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/sales/quotations') ?>">Sales Quotations</a></li>
                <li class="breadcrumb-item active">New</li>
            </ol>
        </nav>
    </div>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-info-circle"></i>
    <span>Prepare a quotation for a customer. It has no stock or accounting effect.</span>
</div>

<form method="post" action="<?= $this->url('/sales/quotations') ?>">
    <?= $this->csrfField() ?>

    <div class="erp-card mb-3">
        <div class="erp-card-header"><h6><i class="bi bi-person-badge me-1 text-primary"></i> Quotation details</h6></div>
        <div class="erp-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="customer_id">Customer <span class="required-star">*</span></label>
                    <select class="form-select" id="customer_id" name="customer_id" required>
                        <option value="">— Select customer —</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= $this->e($c['code'] . ' — ' . $c['name']) ?></option>
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
        $mode = 'sales';
        $warehouseSelect = false;
        $warehouseOptions = '';
        $totalsId = 'sqTotals';
        include APP_ROOT . '/app/Views/purchase/_lines.php';
        ?>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> Save Quotation</button>
        <a href="<?= $this->url('/sales/quotations') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
