<?php /** @var app\Core\View $this */
$rows = $this->data['rows'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Item-wise Cost</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/inventory/stock-balance') ?>">Inventory</a></li>
                <li class="breadcrumb-item active">Item-wise Cost</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <span class="badge badge-soft-info"><i class="bi bi-calculator me-1"></i>Costing: <?= $this->e(\app\Services\InventoryEngine::costingMethodLabel($this->data['method'])) ?></span>
    </div>
</div>

<div class="stat-card mb-3" style="max-width:320px">
    <div class="stat-icon icon-green"><i class="bi bi-box-seam"></i></div>
    <div>
        <div class="stat-label">Total valuation (filtered)</div>
        <div class="stat-value"><?= $this->e(format_money($this->data['totalValue'])) ?></div>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body">
        <form method="get" action="<?= $this->url('/inventory/item-cost') ?>" class="row g-2 align-items-center mb-2">
            <div class="col-auto flex-grow-1" style="max-width:340px">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" class="form-control" placeholder="Search item…" value="<?= $this->e($this->data['search']) ?>">
                </div>
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-primary" type="submit">Search</button></div>
        </form>

        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Code</th>
                        <th class="num">On hand</th>
                        <th class="num">Avg rate</th>
                        <th class="num">Valuation</th>
                        <th class="num">Last purchase</th>
                        <th class="num">Last sale</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $qty = (float) $r['on_hand_qty'];
                    $avg = $qty > 0 ? round((float) $r['valuation'] / $qty, 4) : 0.0;
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= $this->e($r['name']) ?></td>
                        <td><code><?= $this->e($r['item_code']) ?></code></td>
                        <td class="num"><?= $this->e(rtrim(rtrim(number_format($qty, 4), '0'), '.')) ?></td>
                        <td class="num"><?= $this->e(format_money($avg)) ?></td>
                        <td class="num fw-semibold"><?= $this->e(format_money($r['valuation'])) ?></td>
                        <td class="num"><?= (float) $r['last_purchase_rate'] > 0 ? $this->e(format_money($r['last_purchase_rate'])) : '—' ?></td>
                        <td class="num"><?= (float) $r['last_issue_rate'] > 0 ? $this->e(format_money($r['last_issue_rate'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="bi bi-calculator"></i>No items found.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
