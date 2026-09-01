<?php /** @var app\Core\View $this */
$suppliers = $this->data['suppliers'];
$warehouses = $this->data['warehouses'];
$invoices = $this->data['invoices'];
$reference = $this->data['reference'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>New Purchase Return</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/purchase/returns') ?>">Purchase Returns</a></li>
                <li class="breadcrumb-item active">New</li>
            </ol>
        </nav>
    </div>
    <?php if ($reference): ?>
        <div class="actions"><span class="badge badge-soft-info"><i class="bi bi-link-45deg me-1"></i>From Invoice <?= $this->e($reference['invoice_no']) ?></span></div>
    <?php endif; ?>
</div>

<div class="erp-alert erp-alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    <span>Posting a return moves the goods out of stock, reduces what you owe the supplier and reverses input tax. Review quantities before saving.</span>
</div>

<form method="post" action="<?= $this->url('/purchase/returns') ?>">
    <?= $this->csrfField() ?>

    <div class="erp-card mb-3">
        <div class="erp-card-header"><h6><i class="bi bi-person-lines-fill me-1 text-primary"></i> Return details</h6></div>
        <div class="erp-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="supplier_id">Supplier <span class="required-star">*</span></label>
                    <select class="form-select" id="supplier_id" name="supplier_id" required>
                        <option value="">— Select supplier —</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int) $s['id'] ?>" <?= $reference && (int) $reference['supplier_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= $this->e($s['code'] . ' — ' . $s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="return_date">Return date <span class="required-star">*</span></label>
                    <input type="date" class="form-control" id="return_date" name="return_date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="narration">Narration / reason</label>
                    <input type="text" class="form-control" id="narration" name="narration" maxlength="500" placeholder="e.g. Damaged goods, wrong items…">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="warehouse_id">Return from warehouse <span class="required-star">*</span></label>
                    <select class="form-select" id="warehouse_id" name="warehouse_id" required>
                        <option value="">— Select warehouse —</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= (int) $w['id'] ?>"><?= $this->e($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="reference_invoice_id">Reference purchase invoice (optional)</label>
                    <select class="form-select" id="reference_invoice_id" name="reference_invoice_id">
                        <option value="0">— No reference —</option>
                        <?php foreach ($invoices as $inv): ?>
                            <option value="<?= (int) $inv['id'] ?>" <?= $reference && (int) $reference['id'] === (int) $inv['id'] ? 'selected' : '' ?>>
                                <?= $this->e($inv['invoice_no'] . ' — ' . format_date($inv['invoice_date']) . ' (total ' . format_money($inv['total']) . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="erp-card mb-3">
        <div class="erp-card-header"><h6><i class="bi bi-list-check me-1 text-primary"></i> Items being returned</h6></div>
        <?php
        $prefillLines = $this->data['prefillLines'] ?? [];
        $mode = 'purchase';
        $warehouseSelect = false;
        $warehouseOptions = '';
        $totalsId = 'prTotals';
        include APP_ROOT . '/app/Views/purchase/_lines.php';
        ?>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> Post Purchase Return</button>
        <a href="<?= $this->url('/purchase/returns') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
