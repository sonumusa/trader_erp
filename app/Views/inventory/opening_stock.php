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
        <h1>Opening Stock</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/inventory') ?>">Inventory</a></li>
                <li class="breadcrumb-item active">Opening Stock</li>
            </ol>
        </nav>
    </div>
</div>

<div class="erp-alert erp-alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    <span>Opening stock posts the total value to the books: <b>Dr Inventory / Cr Opening Balance Equity</b>. Post it once when you start using the system.</span>
</div>

<form method="post" action="<?= $this->url('/inventory/opening-stock') ?>">
    <?= $this->csrfField() ?>

    <div class="erp-card mb-3">
        <div class="erp-card-header">
            <h6><i class="bi bi-box-seam me-1 text-primary"></i> Opening stock lines</h6>
            <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="addLine"><i class="bi bi-plus-lg"></i> Add item</button>
        </div>
        <div class="erp-card-body" id="lines" data-warehouse-options="<?= $this->e($whOptions) ?>"></div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> Post Opening Stock</button>
        <a href="<?= $this->url('/') ?>" class="btn btn-outline-secondary">Cancel</a>
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
