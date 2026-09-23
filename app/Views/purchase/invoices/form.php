<?php /** @var app\Core\View $this */
$suppliers = $this->data['suppliers'];
$warehouses = $this->data['warehouses'];
$paymentModes = $this->data['paymentModes'];
$fromOrder = $this->data['fromOrder'];
$invoice = $this->data['invoice'] ?? null;
$isEdit = (bool) ($this->data['isEdit'] ?? false);
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1><?= $isEdit ? 'Edit' : 'New' ?> Purchase Invoice</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/purchase/invoices') ?>">Purchase Invoices</a></li>
                <li class="breadcrumb-item active">New</li>
            </ol>
        </nav>
    </div>
    <?php if ($fromOrder): ?>
        <div class="actions"><span class="badge badge-soft-info"><i class="bi bi-link-45deg me-1"></i>Created from Purchase Order #<?= (int) $fromOrder ?></span></div>
    <?php endif; ?>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-info-circle"></i>
    <span>Saving this invoice immediately receives stock and posts balanced accounting
        (Inventory Dr / Tax Receivable Dr / Accounts Payable Cr). No draft step.</span>
</div>

<form method="post" action="<?= $isEdit ? $this->url('/purchase/invoices/' . (int) $invoice['id']) : $this->url('/purchase/invoices') ?>">
    <?= $this->csrfField() ?>
    <input type="hidden" name="reference_order_id" value="<?= (int) $fromOrder ?>">

    <div class="erp-card mb-3">
        <div class="erp-card-header"><h6><i class="bi bi-person-lines-fill me-1 text-primary"></i> Supplier & details</h6></div>
        <div class="erp-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="supplier_id">Supplier <span class="required-star">*</span></label>
                    <select class="form-select" id="supplier_id" name="supplier_id" required>
                        <option value="">— Select supplier —</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= (int) ($invoice['supplier_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= $this->e($s['code'] . ' — ' . $s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint mt-1"><a href="<?= $this->url('/suppliers/new') ?>" target="_blank"><i class="bi bi-plus-circle"></i> Add new supplier</a></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="invoice_date">Invoice date <span class="required-star">*</span></label>
                    <input type="date" class="form-control" id="invoice_date" name="invoice_date" required value="<?= $this->e($invoice['invoice_date'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="narration">Narration / reference</label>
                    <input type="text" class="form-control" id="narration" name="narration" maxlength="500" placeholder="Supplier bill no., remarks…">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="warehouse_id">Receive into warehouse <span class="required-star">*</span></label>
                    <select class="form-select" id="warehouse_id" name="warehouse_id" required>
                        <option value="">— Select warehouse —</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= (int) $w['id'] ?>" <?= (int) ($invoice['warehouse_id'] ?? 0) === (int) $w['id'] ? 'selected' : '' ?>><?= $this->e($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="payment_mode_id">Payment mode (optional)</label>
                    <select class="form-select" id="payment_mode_id" name="payment_mode_id">
                        <option value="0">— Credit purchase —</option>
                        <?php foreach ($paymentModes as $pm): ?>
                            <option value="<?= (int) $pm['id'] ?>" <?= (int) ($invoice['payment_mode_id'] ?? 0) === (int) $pm['id'] ? 'selected' : '' ?>><?= $this->e($pm['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="paid_amount">Paid amount</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="paid_amount" name="paid_amount" value="<?= $this->e($invoice['paid_amount'] ?? '0.00') ?>">
                    <div class="form-hint">Selecting a payment mode + amount records the cash/bank payment immediately.</div>
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
        $totalsId = 'piTotals';
        include APP_ROOT . '/app/Views/purchase/_lines.php';
        ?>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> <?= $isEdit ? 'Save Changes' : 'Post Purchase Invoice' ?></button>
        <a href="<?= $this->url('/purchase/invoices') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
