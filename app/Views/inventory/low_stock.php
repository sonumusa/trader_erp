<?php /** @var app\Core\View $this */ ?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Low Stock</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/inventory') ?>">Inventory</a></li>
                <li class="breadcrumb-item active">Low Stock</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (!$this->data['rows']): ?>
    <div class="erp-alert erp-alert-success">
        <i class="bi bi-check-circle"></i>
        <span>All items are above their reorder levels.</span>
    </div>
<?php endif; ?>

<div class="erp-card">
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Code</th>
                        <th>Warehouse</th>
                        <th class="num">On hand</th>
                        <th class="num">Reorder level</th>
                        <th class="num">Minimum</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->data['rows'] as $r): ?>
                    <tr>
                        <td class="fw-semibold"><?= $this->e($r['name']) ?></td>
                        <td><code><?= $this->e($r['item_code']) ?></code></td>
                        <td><?= $this->e($r['warehouse_name']) ?></td>
                        <td class="num"><span class="badge badge-soft-danger"><?= $this->e(rtrim(rtrim(number_format((float) $r['qty'], 4), '0'), '.')) ?></span></td>
                        <td class="num"><?= $this->e(rtrim(rtrim(number_format((float) $r['reorder_level'], 4), '0'), '.')) ?></td>
                        <td class="num"><?= $this->e(rtrim(rtrim(number_format((float) $r['min_stock'], 4), '0'), '.')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$this->data['rows']): ?>
                    <tr><td colspan="6"><div class="empty-state"><i class="bi bi-check2-all"></i>Nothing below reorder level.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
