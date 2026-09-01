<?php /** @var app\Core\View $this */
$customers = $this->data['customers'];
$warehouses = $this->data['warehouses'];
$paymentModes = $this->data['paymentModes'];
$fromOrder = $this->data['fromOrder'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>New Sales Invoice</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/sales/invoices') ?>">Sales Invoices</a></li>
                <li class="breadcrumb-item active">New</li>
            </ol>
        </nav>
    </div>
    <?php if ($fromOrder): ?>
        <div class="actions"><span class="badge badge-soft-info"><i class="bi bi-link-45deg me-1"></i>Created from Sales Order #<?= (int) $fromOrder ?></span></div>
    <?php endif; ?>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-info-circle"></i>
    <span>Saving this invoice immediately issues stock (COGS), posts balanced accounting
        (Cash/Receivable Dr / Sales Cr / Tax Payable Cr, plus COGS Dr / Inventory Cr) and, when a payment
        mode + amount is given, records the receipt. No draft step.</span>
</div>

<form method="post" action="<?= $this->url('/sales/invoices') ?>">
    <?= $this->csrfField() ?>
    <input type="hidden" name="reference_order_id" value="<?= (int) $fromOrder ?>">

    <div class="erp-card mb-3">
        <div class="erp-card-header"><h6><i class="bi bi-person-badge me-1 text-primary"></i> Customer & details</h6></div>
        <div class="erp-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="customer_id">Customer</label>
                    <select class="form-select" id="customer_id" name="customer_id">
                        <option value="0">— Walk-in / cash customer —</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= $this->e($c['code'] . ' — ' . $c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint mt-1"><a href="<?= $this->url('/customers/new') ?>" target="_blank"><i class="bi bi-plus-circle"></i> Add new customer</a></div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="invoice_date">Invoice date <span class="required-star">*</span></label>
                    <input type="date" class="form-control" id="invoice_date" name="invoice_date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="narration">Narration</label>
                    <input type="text" class="form-control" id="narration" name="narration" maxlength="500" placeholder="Remarks…">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="warehouse_id">Sell from warehouse <span class="required-star">*</span></label>
                    <select class="form-select" id="warehouse_id" name="warehouse_id" required>
                        <option value="">— Select warehouse —</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= (int) $w['id'] ?>"><?= $this->e($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="payment_mode_id">Payment mode (optional)</label>
                    <select class="form-select" id="payment_mode_id" name="payment_mode_id">
                        <option value="0">— Credit sale —</option>
                        <?php foreach ($paymentModes as $pm): ?>
                            <option value="<?= (int) $pm['id'] ?>"><?= $this->e($pm['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="received_amount">Received amount</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="received_amount" name="received_amount" value="0.00">
                    <div class="form-hint">Full payment = cash sale; partial = credit sale + receipt. Selecting a mode posts the receipt immediately.</div>
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
        $totalsId = 'siTotals';
        include APP_ROOT . '/app/Views/purchase/_lines.php';
        ?>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> Post Sales Invoice</button>
        <a href="<?= $this->url('/sales/invoices') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
