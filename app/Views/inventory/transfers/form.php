<?php /** @var app\Core\View $this */
$warehouses = $this->data['warehouses'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>New Stock Transfer</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/inventory/transfers') ?>">Stock Transfers</a></li>
                <li class="breadcrumb-item active">New</li>
            </ol>
        </nav>
    </div>
</div>

<form method="post" action="<?= $this->url('/inventory/transfers') ?>">
    <?= $this->csrfField() ?>

    <div class="erp-card mb-3">
        <div class="erp-card-header"><h6><i class="bi bi-arrow-left-right me-1 text-primary"></i> Movement</h6></div>
        <div class="erp-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="transfer_date">Transfer date <span class="required-star">*</span></label>
                    <input type="date" class="form-control" id="transfer_date" name="transfer_date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="from_warehouse_id">From warehouse <span class="required-star">*</span></label>
                    <select class="form-select" id="from_warehouse_id" name="from_warehouse_id" required>
                        <option value="">— Select —</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= (int) $w['id'] ?>"><?= $this->e($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="to_warehouse_id">To warehouse <span class="required-star">*</span></label>
                    <select class="form-select" id="to_warehouse_id" name="to_warehouse_id" required>
                        <option value="">— Select —</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= (int) $w['id'] ?>"><?= $this->e($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="narration">Narration</label>
                    <input type="text" class="form-control" id="narration" name="narration" maxlength="255">
                </div>
            </div>
        </div>
    </div>

    <div class="erp-card mb-3">
        <div class="erp-card-header">
            <h6><i class="bi bi-list-check me-1 text-primary"></i> Items</h6>
            <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="addLine"><i class="bi bi-plus-lg"></i> Add item</button>
        </div>
        <div class="erp-card-body" id="lines"></div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> Post Transfer</button>
        <a href="<?= $this->url('/inventory/transfers') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script src="<?= $this->asset('js/item-picker.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('lines');
    document.getElementById('addLine').addEventListener('click', () => window.ItemPicker.addRow(container));
    window.ItemPicker.addRow(container);
});
</script>
