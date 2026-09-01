<?php /** @var app\Core\View $this */
$item = $this->data['item'];
$data = $this->data['data'];
$warehouses = $this->data['warehouses'];
$warehouseId = $this->data['warehouseId'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Stock Ledger — <?= $this->e($item['name']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/items') ?>">Items</a></li>
                <li class="breadcrumb-item active"><?= $this->e($item['item_code']) ?></li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <a href="<?= $this->url('/items') ?>" class="btn btn-outline-secondary btn-icon"><i class="bi bi-arrow-left"></i> Items</a>
        <a href="<?= $this->url('/items/' . (int) $item['id'] . '/edit') ?>" class="btn btn-outline-primary btn-icon"><i class="bi bi-pencil"></i> Edit</a>
    </div>
</div>

<div class="stat-grid mb-3">
    <div class="stat-card">
        <div class="stat-icon icon-blue"><i class="bi bi-box-seam"></i></div>
        <div>
            <div class="stat-label">Available (selected wh)</div>
            <div class="stat-value"><?= $this->e(rtrim(rtrim(number_format($this->data['available'], 4), '0'), '.')) ?></div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-green"><i class="bi bi-cash-stack"></i></div>
        <div>
            <div class="stat-label">Valuation (selected wh)</div>
            <div class="stat-value"><?= $this->e(format_money($this->data['valuation'])) ?></div>
        </div>
    </div>
</div>

<div class="erp-card mb-3">
    <div class="erp-card-body">
        <form method="get" action="<?= $this->url('/items/' . (int) $item['id'] . '/ledger') ?>" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0">Warehouse</label>
                <select name="warehouse" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="0">All warehouses</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= (int) $w['id'] ?>" <?= $warehouseId === (int) $w['id'] ? 'selected' : '' ?>><?= $this->e($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Document</th>
                        <th>Warehouse</th>
                        <th class="num">Qty In</th>
                        <th class="num">Qty Out</th>
                        <th class="num">Balance</th>
                        <th class="num">Rate</th>
                        <th class="num">Value</th>
                        <th>Cost method</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['rows'] as $row): ?>
                    <tr>
                        <td class="text-nowrap"><?= $this->e(format_date($row['entry_date'])) ?></td>
                        <td class="small">
                            <span class="fw-semibold"><?= $this->e($row['document_no']) ?></span>
                            <div class="text-muted"><?= $this->e(str_replace('_', ' ', $row['document_type'])) ?></div>
                        </td>
                        <td><?= $this->e($row['warehouse_name']) ?></td>
                        <td class="num"><?= (float) $row['qty_in'] > 0 ? $this->e(rtrim(rtrim(number_format((float) $row['qty_in'], 4), '0'), '.')) : '—' ?></td>
                        <td class="num"><?= (float) $row['qty_out'] > 0 ? $this->e(rtrim(rtrim(number_format((float) $row['qty_out'], 4), '0'), '.')) : '—' ?></td>
                        <td class="num fw-semibold"><?= $this->e(rtrim(rtrim(number_format((float) $row['balance'], 4), '0'), '.')) ?></td>
                        <td class="num"><?= $this->e(format_money($row['rate'])) ?></td>
                        <td class="num"><?= $this->e(format_money($row['value'])) ?></td>
                        <td><span class="badge badge-soft-neutral"><?= $this->e($row['cost_method']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['rows']): ?>
                    <tr><td colspan="9"><div class="empty-state"><i class="bi bi-box-seam"></i>No stock movements yet.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($data['pages'] > 1): ?>
            <nav class="p-2"><ul class="pagination">
                <?php for ($i = 1; $i <= $data['pages']; $i++): ?>
                    <li class="page-item <?= $i === $data['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $this->url('/items/' . (int) $item['id'] . '/ledger?page=' . $i . '&warehouse=' . $warehouseId) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>
</div>
