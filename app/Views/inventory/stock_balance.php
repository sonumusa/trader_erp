<?php /** @var app\Core\View $this */
$rows = $this->data['rows'];
$warehouses = $this->data['warehouses'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Stock Balance</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/inventory') ?>">Inventory</a></li>
                <li class="breadcrumb-item active">Stock Balance</li>
            </ol>
        </nav>
    </div>
</div>

<div class="stat-card mb-3" style="max-width:320px">
    <div class="stat-icon icon-green"><i class="bi bi-box-seam"></i></div>
    <div>
        <div class="stat-label">Total stock value (filtered)</div>
        <div class="stat-value"><?= $this->e(format_money($this->data['totalValue'])) ?></div>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body">
        <form method="get" action="<?= $this->url('/inventory/stock-balance') ?>" class="row g-2 align-items-center mb-2">
            <div class="col-auto flex-grow-1" style="max-width:340px">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" class="form-control" placeholder="Search item…" value="<?= $this->e($this->data['search']) ?>">
                </div>
            </div>
            <div class="col-auto">
                <select name="warehouse" class="form-select form-select-sm">
                    <option value="0">All warehouses</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= (int) $w['id'] ?>" <?= $this->data['warehouseId'] === (int) $w['id'] ? 'selected' : '' ?>><?= $this->e($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-primary" type="submit">Filter</button>
            </div>
        </form>

        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Code</th>
                        <th>Warehouse</th>
                        <th class="num">Quantity</th>
                        <th class="num">Valuation</th>
                        <th class="actions-cell">Ledger</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= $this->e($r['name']) ?></td>
                        <td><code><?= $this->e($r['item_code']) ?></code></td>
                        <td><?= $this->e($r['warehouse_name']) ?></td>
                        <td class="num"><?= $this->e(rtrim(rtrim(number_format((float) $r['qty'], 4), '0'), '.')) ?></td>
                        <td class="num"><?= $this->e(format_money($r['value'])) ?></td>
                        <td class="actions-cell">
                            <a href="<?= $this->url('/items/' . (int) $r['id'] . '/ledger?warehouse=' . (int) $r['warehouse_id']) ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Ledger">
                                <i class="bi bi-journal-text"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="6"><div class="empty-state"><i class="bi bi-box-seam"></i>No stock in the selected view.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
