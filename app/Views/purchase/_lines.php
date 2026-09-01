<?php
/**
 * Shared purchase line-rows partial.
 * Renders the item-picker container (with server-prefilled rows when given)
 * and wires live totals.
 * Expects: $prefillLines (array), $mode ('purchase'), $warehouseSelect (bool),
 *          $warehouseOptions (html string), $totalsId (string)
 */
use app\Services\UomService;

$whOptions = $warehouseOptions ?? '';
if ($warehouseSelect && $whOptions === '') {
    $whOptions = '<option value="">— Warehouse —</option>';
}
?>
<div class="erp-card-body">
    <div id="<?= $this->e($totalsId) ?>-lines"
         data-line-mode="<?= $this->e($mode) ?>"
         data-warehouse-select="<?= $warehouseSelect ? '1' : '0' ?>"
         data-warehouse-options="<?= $this->e($whOptions) ?>">

        <?php if ($prefillLines): ?>
            <?php foreach ($prefillLines as $line): ?>
                <?php
                $itemId = (int) ($line['item_id'] ?? 0);
                $uoms = $itemId ? UomService::itemUoms($itemId) : [];
                $prefillJson = json_encode([
                    'id'          => $itemId,
                    'item_code'   => $line['prefill_item_code'] ?? '',
                    'name'        => $line['prefill_item_name'] ?? '',
                    'uom_code'    => $line['prefill_uom_code'] ?? '',
                    'uoms'        => array_map(fn($u) => [
                        'uom_id' => (int) $u['uom_id'],
                        'uom_code' => $u['uom_code'],
                        'uom_name' => $u['uom_name'],
                        'conversion_factor' => $u['conversion_factor'],
                        'is_stock_uom' => $u['is_stock_uom'],
                    ], $uoms),
                    'tax_rate'    => $line['tax_rate'] ?? 0,
                ], JSON_UNESCAPED_UNICODE);
                ?>
                <div class="item-picker-row row g-2 align-items-center mb-2 border rounded-3 p-2 bg-white"
                     data-prefill="<?= $this->e($prefillJson) ?>" data-uom-id="<?= (int) ($line['uom_id'] ?? 0) ?>">
                    <div class="col-md-3 col-12">
                        <input type="text" class="form-control form-control-sm item-search" value="<?= $this->e(($line['prefill_item_name'] ?? '') . ' (' . ($line['prefill_item_code'] ?? '') . ')') ?>" autocomplete="off">
                        <input type="hidden" class="item-id" name="items[item_id][]" value="<?= $itemId ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <select class="form-select form-select-sm item-uom" name="items[uom_id][]">
                            <option value="">UOM</option>
                            <?php foreach ($uoms as $u): ?>
                                <option value="<?= (int) $u['uom_id'] ?>" data-factor="<?= $this->e($u['conversion_factor']) ?>"
                                        <?= (int) ($line['uom_id'] ?? 0) === (int) $u['uom_id'] ? 'selected' : '' ?>>
                                    <?= $this->e($u['uom_code']) ?><?= (int) $u['is_stock_uom'] === 1 ? ' (stock)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-1">
                        <input type="number" step="0.0001" min="0" class="form-control form-control-sm item-qty" name="items[quantity][]" value="<?= $this->e($line['quantity'] ?? '') ?>" placeholder="Qty">
                    </div>
                    <div class="col-6 col-md-2">
                        <input type="number" step="0.0001" min="0" class="form-control form-control-sm item-rate" name="items[rate][]" value="<?= $this->e($line['rate'] ?? '') ?>" placeholder="Rate">
                    </div>
                    <div class="col-6 col-md-1">
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm item-discount" name="items[discount][]" value="<?= $this->e($line['discount'] ?? '') ?>" placeholder="Disc.">
                    </div>
                    <?php if ($warehouseSelect): ?>
                    <div class="col-6 col-md-2">
                        <select class="form-select form-select-sm item-warehouse" name="items[warehouse_id][]"><?= $whOptions ?></select>
                    </div>
                    <?php endif; ?>
                    <div class="col-12 col-md-1">
                        <div class="d-flex gap-1">
                            <span class="item-info small text-muted align-self-center" style="min-width:52px"></span>
                            <button type="button" class="btn btn-sm btn-outline-danger item-remove ms-auto" title="Remove row"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>
                    <div class="col-12 item-batch-fields d-none">
                        <div class="row g-2">
                            <div class="col-6 col-md-3"><input type="text" class="form-control form-control-sm" name="items[batch_no][]" placeholder="Batch no"></div>
                            <div class="col-6 col-md-3"><input type="date" class="form-control form-control-sm" name="items[mfg_date][]" placeholder="Mfg date"></div>
                            <div class="col-6 col-md-3"><input type="date" class="form-control form-control-sm" name="items[expiry_date][]" placeholder="Expiry date"></div>
                            <div class="col-6 col-md-3"><input type="text" class="form-control form-control-sm" name="items[serials][]" placeholder="Serials (comma separated)"></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <button type="button" class="btn btn-sm btn-outline-primary btn-icon" data-add-line>
        <i class="bi bi-plus-lg"></i> Add item
    </button>
</div>

<div class="erp-card-footer" id="<?= $this->e($totalsId) ?>">
    <div class="row justify-content-end g-2">
        <div class="col-md-4">
            <table class="table table-sm erp-table mb-0">
                <tr><td class="small text-muted">Subtotal</td><td class="num fw-semibold t-subtotal">0.00</td></tr>
                <tr><td class="small text-muted">Discount</td><td class="num t-discount">0.00</td></tr>
                <tr><td class="small text-muted">Tax</td><td class="num t-tax">0.00</td></tr>
                <tr class="border-top"><td><b>Total</b></td><td class="num fw-bold t-total">0.00</td></tr>
            </table>
        </div>
    </div>
</div>

<script src="<?= $this->asset('js/item-picker.js') ?>"></script>
<script>
(function () {
    const lines = document.getElementById('<?= $this->e($totalsId) ?>-lines');
    if (!lines) return;
    const opts = { warehouseSelect: lines.dataset.warehouseSelect === '1', mode: lines.dataset.lineMode || null };
    const renderTotals = () => {
        const t = window.ItemPicker.totals(lines);
        const set = (sel, v) => { const el = document.querySelector('#' + '<?= $this->e($totalsId) ?> .' + sel); if (el) el.textContent = Number(v).toFixed(2); };
        set('t-subtotal', t.subtotal); set('t-discount', t.discount); set('t-tax', t.tax); set('t-total', t.total);
    };
    opts.onChange = renderTotals;
    document.querySelector('[data-add-line]').addEventListener('click', () => window.ItemPicker.addRow(lines, opts));
    window.ItemPicker.bindExisting(lines, opts);
    renderTotals();
})();
</script>
