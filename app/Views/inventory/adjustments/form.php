<?php /** @var app\Core\View $this */
$warehouses = $this->data['warehouses'];
$whOptions = '<option value="">— Warehouse —</option>';
foreach ($warehouses as $w) {
    $whOptions .= '<option value="' . (int) $w['id'] . '">' . htmlspecialchars((string) $w['name'], ENT_QUOTES, 'UTF-8') . '</option>';
}
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>New Stock Adjustment</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/inventory/adjustments') ?>">Stock Adjustments</a></li>
                <li class="breadcrumb-item active">New</li>
            </ol>
        </nav>
    </div>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-info-circle"></i>
    <span>Positive quantity = stock <b>increase</b> (Dr Inventory / Cr Stock Adjustment). Negative quantity = stock <b>decrease</b> (Dr Stock Adjustment / Cr Inventory). Every adjustment posts real, balanced accounting.</span>
</div>

<form method="post" action="<?= $this->url('/inventory/adjustments') ?>">
    <?= $this->csrfField() ?>

    <div class="erp-card mb-3">
        <div class="erp-card-header"><h6><i class="bi bi-sliders me-1 text-primary"></i> Adjustment</h6></div>
        <div class="erp-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="adjustment_date">Adjustment date <span class="required-star">*</span></label>
                    <input type="date" class="form-control" id="adjustment_date" name="adjustment_date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="reason">Reason <span class="required-star">*</span></label>
                    <input type="text" class="form-control" id="reason" name="reason" required maxlength="255" placeholder="e.g. Damaged goods, count difference…">
                </div>
            </div>
        </div>
    </div>

    <div class="erp-card mb-3">
        <div class="erp-card-header">
            <h6><i class="bi bi-list-check me-1 text-primary"></i> Items</h6>
            <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="addLine"><i class="bi bi-plus-lg"></i> Add item</button>
        </div>
        <div class="erp-card-body" id="lines" data-warehouse-options="<?= $this->e($whOptions) ?>"></div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> Post Adjustment</button>
        <a href="<?= $this->url('/inventory/adjustments') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script src="<?= $this->asset('js/item-picker.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('lines');
    document.getElementById('addLine').addEventListener('click', () => window.ItemPicker.addRow(container, { warehouseSelect: true }));
    window.ItemPicker.addRow(container, { warehouseSelect: true });
});
</script>
