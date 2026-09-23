<?php /** @var app\Core\View $this */
$suppliers = $this->data['suppliers'];
$quotations = $this->data['quotations'];
$reference = $this->data['reference'];
$order = $this->data['order'] ?? null;
$isEdit = (bool) ($this->data['isEdit'] ?? false);
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1><?= $isEdit ? 'Edit' : 'New' ?> Purchase Order</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/purchase/orders') ?>">Purchase Orders</a></li>
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
    <span>A purchase order is a pre-invoice document — no stock or accounting effect until you create the invoice.</span>
</div>

<form method="post" action="<?= $isEdit ? $this->url('/purchase/orders/' . (int) $order['id']) : $this->url('/purchase/orders') ?>">
    <?= $this->csrfField() ?>

    <div class="erp-card mb-3">
        <div class="erp-card-header"><h6><i class="bi bi-person-lines-fill me-1 text-primary"></i> Order details</h6></div>
        <div class="erp-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="supplier_id">Supplier <span class="required-star">*</span></label>
                    <select class="form-select" id="supplier_id" name="supplier_id" required>
                        <option value="">— Select supplier —</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (int) ($order['supplier_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= $this->e($s['code'] . ' — ' . $s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="order_date">Order date <span class="required-star">*</span></label>
                    <input type="date" class="form-control" id="order_date" name="order_date" required value="<?= $this->e($order['order_date'] ?? date('Y-m-d')) ?>">
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
                    <input type="text" class="form-control" id="narration" name="narration" maxlength="500" value="<?= $this->e($order['narration'] ?? '') ?>">
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
        $totalsId = 'poTotals';
        include APP_ROOT . '/app/Views/purchase/_lines.php';
        ?>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> <?= $isEdit ? 'Save Changes' : 'Save Purchase Order' ?></button>
        <a href="<?= $this->url('/purchase/orders') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
