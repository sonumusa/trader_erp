<?php /** @var app\Core\View $this */
$customers = $this->data['customers'];
$quotations = $this->data['quotations'];
$reference = $this->data['reference'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>New Sales Order</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/sales/orders') ?>">Sales Orders</a></li>
                <li class="breadcrumb-item active">New</li>
            </ol>
        </nav>
    </div>
    <?php if ($reference): ?>
        <div class="actions"><span class="badge badge-soft-info"><i class="bi bi-link-45deg me-1"></i>From Quotation <?= $this->e($reference['quotation_no']) ?></span></div>
    <?php endif; ?>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-info-circle"></i>
    <span>A sales order is a pre-invoice document — no stock or accounting effect until you create the invoice.</span>
</div>

<form method="post" action="<?= $this->url('/sales/orders') ?>">
    <?= $this->csrfField() ?>

    <div class="erp-card mb-3">
        <div class="erp-card-header"><h6><i class="bi bi-person-badge me-1 text-primary"></i> Order details</h6></div>
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
                    <label class="form-label" for="order_date">Order date <span class="required-star">*</span></label>
                    <input type="date" class="form-control" id="order_date" name="order_date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="reference_quotation_id">From quotation (optional)</label>
                    <select class="form-select" id="reference_quotation_id" name="reference_quotation_id">
                        <option value="0">— None —</option>
                        <?php foreach ($quotations as $q): ?>
                            <option value="<?= (int) $q['id'] ?>" <?= $reference && (int) $reference['id'] === (int) $q['id'] ? 'selected' : '' ?>>
                                <?= $this->e($q['quotation_no'] . ' — ' . format_date($q['quotation_date'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
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
        $totalsId = 'soTotals';
        include APP_ROOT . '/app/Views/purchase/_lines.php';
        ?>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> Save Sales Order</button>
        <a href="<?= $this->url('/sales/orders') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
