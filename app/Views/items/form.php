<?php /** @var app\Core\View $this */
$item = $this->data['item'];
$old = \app\Core\Session::get('_old_input', []);
$val = fn(string $k) => $this->e($old[$k] ?? ($item[$k] ?? ''));
$itemUoms = $this->data['itemUoms'];
$altUoms = array_values(array_filter($itemUoms, fn($u) => (int) $u['is_stock_uom'] !== 1));
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1><?= $item ? 'Edit Item' : 'New Item' ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/items') ?>">Items</a></li>
                <li class="breadcrumb-item active"><?= $item ? 'Edit' : 'New' ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-10">
        <form method="post" action="<?= $item ? $this->url('/items/' . (int) $item['id']) : $this->url('/items') ?>">
            <?= $this->csrfField() ?>

            <div class="erp-card mb-3">
                <div class="erp-card-header"><h6><i class="bi bi-box me-1 text-primary"></i> Basics</h6></div>
                <div class="erp-card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label" for="item_code">Item code <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="item_code" name="item_code" required maxlength="60" value="<?= $val('item_code') ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="name">Item name <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required maxlength="150" value="<?= $val('name') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="barcode">Barcode</label>
                            <input type="text" class="form-control" id="barcode" name="barcode" maxlength="80" value="<?= $val('barcode') ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label" for="brand">Brand</label>
                            <input type="text" class="form-control" id="brand" name="brand" maxlength="80" value="<?= $val('brand') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="item_group_id">Item group</label>
                            <select class="form-select" id="item_group_id" name="item_group_id">
                                <option value="">— None —</option>
                                <?php foreach ($this->data['groups'] as $g): ?>
                                    <option value="<?= (int) $g['id'] ?>" <?= (int) ($item['item_group_id'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>><?= $this->e($g['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="tax_id">Tax category</label>
                            <select class="form-select" id="tax_id" name="tax_id">
                                <option value="">— No tax —</option>
                                <?php foreach ($this->data['taxes'] as $t): ?>
                                    <option value="<?= (int) $t['id'] ?>" <?= (int) ($item['tax_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>>
                                        <?= $this->e($t['name'] . ' (' . rtrim(rtrim(number_format((float) $t['rate'], 2), '0'), '.') . '%)') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="status">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?= ($item['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($item['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="description">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2"><?= $val('description') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="erp-card mb-3">
                <div class="erp-card-header"><h6><i class="bi bi-rulers me-1 text-primary"></i> UOM — only the UOMs below will appear for this item</h6></div>
                <div class="erp-card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="stock_uom_id">Stock UOM <span class="required-star">*</span></label>
                            <select class="form-select" id="stock_uom_id" name="stock_uom_id" required>
                                <option value="">— Select —</option>
                                <?php foreach ($this->data['uoms'] as $u): ?>
                                    <option value="<?= (int) $u['id'] ?>" <?= (int) ($item['stock_uom_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>>
                                        <?= $this->e($u['code'] . ' — ' . $u['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alternative UOMs (1 alt = X stock units)</label>
                            <div id="altUomRows">
                                <?php foreach ($altUoms as $au): ?>
                                    <div class="row g-2 mb-2 alt-uom-row">
                                        <div class="col-md-4">
                                            <select class="form-select form-select-sm" name="alt_uom_id[]">
                                                <option value="">— Select UOM —</option>
                                                <?php foreach ($this->data['uoms'] as $u): ?>
                                                    <option value="<?= (int) $u['id'] ?>" <?= (int) $au['uom_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= $this->e($u['code'] . ' — ' . $u['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">1 =</span>
                                                <input type="number" step="0.0001" min="0.0001" class="form-control" name="alt_factor[]" value="<?= $this->e($au['conversion_factor']) ?>">
                                                <span class="input-group-text">stock units</span>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-sm btn-outline-danger alt-uom-remove"><i class="bi bi-x-lg"></i></button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-icon" id="addAltUom">
                                <i class="bi bi-plus-lg"></i> Add alternative UOM
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="erp-card mb-3">
                <div class="erp-card-header"><h6><i class="bi bi-tags me-1 text-primary"></i> Pricing & stock levels</h6></div>
                <div class="erp-card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label" for="default_purchase_rate">Default purchase rate</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="default_purchase_rate" name="default_purchase_rate" value="<?= $val('default_purchase_rate') ?: '0.00' ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="default_sales_rate">Default sales rate</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="default_sales_rate" name="default_sales_rate" value="<?= $val('default_sales_rate') ?: '0.00' ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="min_stock">Minimum stock</label>
                            <input type="number" step="0.0001" min="0" class="form-control" id="min_stock" name="min_stock" value="<?= $val('min_stock') ?: '0' ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="reorder_level">Reorder level</label>
                            <input type="number" step="0.0001" min="0" class="form-control" id="reorder_level" name="reorder_level" value="<?= $val('reorder_level') ?: '0' ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="erp-card mb-3">
                <div class="erp-card-header"><h6><i class="bi bi-shield-check me-1 text-primary"></i> Optional tracking</h6></div>
                <div class="erp-card-body">
                    <div class="d-flex gap-4 flex-wrap">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="batch_enabled" name="batch_enabled" value="1"
                                   <?= (int) ($item['batch_enabled'] ?? 0) === 1 ? 'checked' : '' ?>
                                   <?= $this->data['batchEnabled'] ? '' : 'disabled' ?>>
                            <label class="form-check-label small" for="batch_enabled">Batch numbers</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="serial_enabled" name="serial_enabled" value="1"
                                   <?= (int) ($item['serial_enabled'] ?? 0) === 1 ? 'checked' : '' ?>
                                   <?= $this->data['serialEnabled'] ? '' : 'disabled' ?>>
                            <label class="form-check-label small" for="serial_enabled">Serial numbers</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="expiry_enabled" name="expiry_enabled" value="1"
                                   <?= (int) ($item['expiry_enabled'] ?? 0) === 1 ? 'checked' : '' ?>
                                   <?= $this->data['expiryEnabled'] ? '' : 'disabled' ?>>
                            <label class="form-check-label small" for="expiry_enabled">Expiry dates</label>
                        </div>
                    </div>
                    <?php if (!$this->data['batchEnabled'] || !$this->data['serialEnabled'] || !$this->data['expiryEnabled']): ?>
                        <div class="form-hint mt-2">Batch / serial / expiry features are disabled system-wide. Enable them under Settings → Features to use them on items.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="erp-card mb-3">
                <div class="erp-card-header"><h6><i class="bi bi-diagram-3 me-1 text-primary"></i> Account overrides (optional)</h6></div>
                <div class="erp-card-body">
                    <p class="form-hint">Leave blank to use the Item Group or company defaults.</p>
                    <div class="row g-3">
                        <?php foreach ([
                            'inventory_account_id' => 'Inventory',
                            'cogs_account_id' => 'COGS',
                            'sales_account_id' => 'Sales',
                            'purchase_account_id' => 'Purchase',
                        ] as $field => $label): ?>
                            <div class="col-md-6">
                                <label class="form-label" for="<?= $field ?>"><?= $label ?> account</label>
                                <select class="form-select" id="<?= $field ?>" name="<?= $field ?>">
                                    <option value="">— Company default —</option>
                                    <?php foreach ($this->data['accounts'] as $acc): ?>
                                        <option value="<?= (int) $acc['id'] ?>" <?= (int) ($item[$field] ?? 0) === (int) $acc['id'] ? 'selected' : '' ?>>
                                            <?= $this->e($acc['code'] . ' — ' . $acc['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mb-4">
                <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> <?= $item ? 'Save Changes' : 'Create Item' ?></button>
                <a href="<?= $this->url('/items') ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const rows = document.getElementById('altUomRows');
    const uomOptions = Array.from(document.querySelectorAll('#stock_uom_id option')).map(o => o.cloneNode(true));
    function addRow(sel, factor) {
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2 alt-uom-row';
        const selWrap = document.createElement('div');
        selWrap.className = 'col-md-4';
        const select = document.createElement('select');
        select.className = 'form-select form-select-sm';
        select.name = 'alt_uom_id[]';
        const opt0 = document.createElement('option');
        opt0.value = ''; opt0.textContent = '— Select UOM —';
        select.appendChild(opt0);
        uomOptions.forEach((o) => { if (o.value) select.appendChild(o.cloneNode(true)); });
        if (sel) select.value = sel;
        selWrap.appendChild(select);
        const facWrap = document.createElement('div');
        facWrap.className = 'col-md-4';
        facWrap.innerHTML = '<div class="input-group input-group-sm"><span class="input-group-text">1 =</span>' +
            '<input type="number" step="0.0001" min="0.0001" class="form-control" name="alt_factor[]" value="' + (factor || 1) + '">' +
            '<span class="input-group-text">stock units</span></div>';
        const btnWrap = document.createElement('div');
        btnWrap.className = 'col-md-2';
        btnWrap.innerHTML = '<button type="button" class="btn btn-sm btn-outline-danger alt-uom-remove"><i class="bi bi-x-lg"></i></button>';
        div.append(selWrap, facWrap, btnWrap);
        div.querySelector('.alt-uom-remove').addEventListener('click', () => div.remove());
        rows.appendChild(div);
    }
    document.getElementById('addAltUom').addEventListener('click', () => addRow('', 1));
    rows.querySelectorAll('.alt-uom-remove').forEach((b) => b.addEventListener('click', () => b.closest('.alt-uom-row').remove()));
})();
</script>
