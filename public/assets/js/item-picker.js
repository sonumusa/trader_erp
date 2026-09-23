/* TradeERP — Item picker rows for inventory & purchase documents.
 * - Item search by code / name / barcode (AJAX /items/search)
 * - Shows ONLY the selected item's UOMs
 * - mode 'purchase': rate defaults to purchase rate, adds Discount field,
 *   shows tax badge, and live-recalculates document totals
 * - Supports server-prefilled rows (ItemPicker.bindExisting)
 */
(function () {
    'use strict';

    const SERIAL_FEATURE = document.body.dataset.serialFeature === '1';

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }
    function money(v) { return Number(v || 0).toFixed(2); }

    function rowTemplate(opts) {
        const showDiscount = opts.mode === 'purchase' || opts.mode === 'sales';
        const withWarehouse = !!opts.warehouseSelect;
        return `
            <div class="item-picker-row row g-2 align-items-center mb-2 border rounded-3 p-2 bg-white">
                <div class="col-md-3 col-12">
                    <input type="text" class="form-control form-control-sm item-search" placeholder="Search item (code / name / barcode)…" autocomplete="off">
                    <input type="hidden" class="item-id" name="items[item_id][]">
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm item-uom" name="items[uom_id][]"><option value="">UOM</option></select>
                </div>
                <div class="col-6 col-md-1">
                    <input type="number" step="0.0001" min="0" class="form-control form-control-sm item-qty" name="items[quantity][]" placeholder="Qty">
                </div>
                <div class="col-6 col-md-2">
                    <input type="number" step="0.0001" min="0" class="form-control form-control-sm item-rate" name="items[rate][]" placeholder="Rate">
                </div>
                ${showDiscount ? `
                <div class="col-6 col-md-1">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm item-discount" name="items[discount][]" placeholder="Disc.">
                </div>` : ''}
                ${withWarehouse ? `
                <div class="col-6 col-md-2">
                    <select class="form-select form-select-sm item-warehouse" name="items[warehouse_id][]">
                        ${opts.warehouseOptions || ''}
                    </select>
                </div>` : ''}
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
                        ${SERIAL_FEATURE ? `<div class="col-6 col-md-3"><input type="text" class="form-control form-control-sm" name="items[serials][]" placeholder="Serials (comma separated)"></div>` : ''}
                    </div>
                </div>
            </div>`;
    }

    function bindRow(row, container, opts) {
        const isPurchase = opts.mode === 'purchase';
        const search = row.querySelector('.item-search');
        const uomSel = row.querySelector('.item-uom');
        const qty = row.querySelector('.item-qty');
        const rate = row.querySelector('.item-rate');
        const discount = row.querySelector('.item-discount');
        const info = row.querySelector('.item-info');
        const batchFields = row.querySelector('.item-batch-fields');
        let currentItem = null;
        let timer = null;
        let listEl = null;

        const refresh = () => {
            if (!currentItem) return;
            const f = parseFloat(uomSel.selectedOptions[0]?.dataset.factor || '1');
            const q = parseFloat(qty.value) || 0;
            const r = parseFloat(rate.value) || 0;
            const d = parseFloat(discount?.value) || 0;
            const baseQty = q * f;
            const rateStock = f ? r / f : r;
            const gross = baseQty * rateStock;
            const disc = Math.min(d, gross);
            const net = gross - disc;
            const taxRate = parseFloat(currentItem.tax_rate || 0) || 0;
            const tax = net * taxRate / 100;
            const amount = net + tax;
            info.innerHTML = '<span class="d-block">= ' + baseQty.toFixed(2) + ' ' + (currentItem.uom_code || '') +
                (taxRate ? ' <span class="badge badge-soft-warning">T ' + taxRate + '%</span>' : '') + '</span>' +
                '<span class="d-block fw-semibold text-dark">' + money(amount) + '</span>';
            if (opts.onChange) opts.onChange();
        };

        row.querySelector('.item-remove').addEventListener('click', () => {
            if (container.querySelectorAll('.item-picker-row').length > 1) row.remove();
            else search.value = '';
            if (opts.onChange) opts.onChange();
        });

        search.addEventListener('input', () => {
            clearTimeout(timer);
            const q = search.value.trim();
            if (q.length < 1) { removeList(); return; }
            timer = setTimeout(async () => {
                try {
                    const res = await fetch('/items/search?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    if (!data.success) return;
                    showList(row, search, data.data || [], (item) => {
                        currentItem = item;
                        rate.dataset.taxRate = item.tax_rate || 0;
                        row.querySelector('.item-id').value = item.id;
                        search.value = item.name + ' (' + item.item_code + ')';
                        populateUoms(uomSel, item);
                        rate.value = opts.mode === 'purchase' ? (item.default_purchase_rate || '') : (item.default_sales_rate || '');
                        const needsBatch = item.batch_enabled === '1' || item.batch_enabled === 1;
                        const needsSerial = item.serial_enabled === '1' || item.serial_enabled === 1;
                        batchFields.classList.toggle('d-none', !needsBatch && !needsSerial);
                        if (needsBatch) batchFields.querySelector('[name="items[batch_no][]"]').required = true;
                        refresh();
                    });
                } catch (e) { /* non-blocking */ }
            }, 250);
        });

        [qty, rate, uomSel].forEach(el => el && el.addEventListener('input', refresh));
        uomSel.addEventListener('change', refresh);
        if (discount) discount.addEventListener('input', refresh);

        search.addEventListener('focus', () => { if (currentItem) search.select(); });
        // Enter picks the first suggestion (handy on mobile keyboards / scanners)
        search.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (listEl && listEl.querySelector('.list-group-item-action')) {
                    listEl.querySelector('.list-group-item-action').click();
                } else {
                    search.blur();
                }
            }
            if (e.key === 'Escape') removeList();
        });
        document.addEventListener('click', (e) => { if (!row.contains(e.target)) removeList(); });

        // If this row was server-prefilled, hook the stored data
        if (row.dataset.prefill) {
            try {
                const item = JSON.parse(row.dataset.prefill);
                currentItem = item;
                populateUoms(uomSel, item, row.dataset.uomId);
                search.value = (item.name || '') + ' (' + (item.item_code || '') + ')';
                refresh();
            } catch (e) { /* ignore */ }
        }

        function removeList() { if (listEl && listEl.parentNode) listEl.parentNode.remove(); listEl = null; }
        function showList(rowEl, input, items, onPick) {
            removeList();
            const wrap = document.createElement('div');
            wrap.className = 'position-relative';
            input.parentNode.appendChild(wrap);
            listEl = document.createElement('ul');
            listEl.className = 'list-group position-absolute shadow-sm item-suggest';
            listEl.style.cssText = 'z-index:1050;max-height:260px;overflow:auto;width:100%;top:100%;left:0';
            if (!items.length) {
                const li = document.createElement('li');
                li.className = 'list-group-item small text-muted';
                li.textContent = 'No items found';
                listEl.appendChild(li);
            }
            items.forEach((item) => {
                const li = document.createElement('li');
                li.className = 'list-group-item list-group-item-action small py-1 px-2';
                li.innerHTML = '<b>' + escapeHtml(item.item_code) + '</b> — ' + escapeHtml(item.name)
                    + (item.barcode ? ' <span class="text-muted">[' + escapeHtml(item.barcode) + ']</span>' : '');
                li.addEventListener('mousedown', (e) => { e.preventDefault(); onPick(item); removeList(); });
                listEl.appendChild(li);
            });
            wrap.appendChild(listEl);
        }
        function populateUoms(sel, item, selectedUom) {
            sel.innerHTML = '<option value="">UOM</option>';
            let stockUomId = '';
            (item.uoms || []).forEach((u) => {
                const o = document.createElement('option');
                o.value = u.uom_id;
                o.dataset.factor = u.conversion_factor;
                o.textContent = u.uom_code + (u.is_stock_uom === '1' || u.is_stock_uom === 1 ? ' (stock)' : '');
                sel.appendChild(o);
                if (u.is_stock_uom === '1' || u.is_stock_uom === 1) stockUomId = String(u.uom_id);
            });
            // New rows default to the item's stock UOM; existing rows keep their saved UOM.
            sel.value = selectedUom || stockUomId || (item.stock_uom_id ? String(item.stock_uom_id) : '');
        }
    }

    window.ItemPicker = {
        addRow(container, opts = {}) {
            const o = {
                mode: container.dataset.lineMode || opts.mode || null,
                warehouseSelect: opts.warehouseSelect || container.dataset.warehouseSelect === '1',
                warehouseOptions: opts.warehouseOptions || container.dataset.warehouseOptions || '',
                onChange: opts.onChange || null,
            };
            const row = document.createElement('div');
            row.innerHTML = rowTemplate(o);
            container.appendChild(row.firstElementChild);
            const el = container.lastElementChild;
            bindRow(el, container, o);
            el.querySelector('.item-search').focus();
            return el;
        },

        bindExisting(container, opts = {}) {
            const o = {
                mode: container.dataset.lineMode || opts.mode || null,
                warehouseSelect: opts.warehouseSelect || container.dataset.warehouseSelect === '1',
                warehouseOptions: opts.warehouseOptions || container.dataset.warehouseOptions || '',
                onChange: opts.onChange || null,
            };
            container.querySelectorAll('.item-picker-row').forEach((row) => bindRow(row, container, o));
        },

        /** Compute document totals from rows. Returns {subtotal, discount, tax, total} */
        totals(container) {
            let subtotal = 0, discount = 0, tax = 0, total = 0;
            container.querySelectorAll('.item-picker-row').forEach((row) => {
                const item = row.querySelector('.item-id');
                if (!item || !item.value) return;
                const f = parseFloat(row.querySelector('.item-uom').selectedOptions[0]?.dataset.factor || '1');
                const q = parseFloat(row.querySelector('.item-qty').value) || 0;
                const r = parseFloat(row.querySelector('.item-rate').value) || 0;
                const d = parseFloat(row.querySelector('.item-discount')?.value) || 0;
                const gross = (q * f) * (f ? r / f : r);
                const disc = Math.min(d, gross);
                const net = gross - disc;
                const tr = parseFloat(row.querySelector('.item-rate').dataset.taxRate || '0') || 0;
                const tx = net * tr / 100;
                subtotal += net;
                discount += disc;
                tax += tx;
                total += net + tx;
            });
            return { subtotal, discount, tax, total };
        },
    };
})();
