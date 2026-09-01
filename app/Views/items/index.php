<?php /** @var app\Core\View $this */ ?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Items</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Items</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <a href="<?= $this->url('/item-groups') ?>" class="btn btn-outline-secondary btn-icon">
            <i class="bi bi-folder2"></i> Item Groups
        </a>
        <?php if ($this->data['canCreate']): ?>
            <a href="<?= $this->url('/items/new') ?>" class="btn btn-primary btn-icon">
                <i class="bi bi-plus-lg"></i> New Item
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body">
        <form method="get" action="<?= $this->url('/items') ?>" class="row g-2 align-items-center mb-2">
            <div class="col-auto flex-grow-1" style="max-width:380px">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" class="form-control" placeholder="Search by code, name or barcode…"
                           value="<?= $this->e($this->data['search']) ?>">
                </div>
            </div>
            <div class="col-auto">
                <select name="group" class="form-select form-select-sm">
                    <option value="0">All groups</option>
                    <?php foreach ($this->data['groups'] as $g): ?>
                        <option value="<?= (int) $g['id'] ?>" <?= $this->data['groupId'] === (int) $g['id'] ? 'selected' : '' ?>><?= $this->e($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-primary" type="submit">Search</button>
            </div>
        </form>

        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Code</th>
                        <th>Barcode</th>
                        <th>Group</th>
                        <th>Stock UOM</th>
                        <th class="num">Purchase</th>
                        <th class="num">Sales</th>
                        <th class="num">Stock</th>
                        <th>Status</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->data['rows'] as $i): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= $this->e($i['name']) ?></div>
                            <div class="small text-muted"><?= $this->e($i['brand'] ?: '') ?></div>
                        </td>
                        <td><code><?= $this->e($i['item_code']) ?></code></td>
                        <td class="small"><?= $this->e($i['barcode'] ?: '—') ?></td>
                        <td><span class="badge badge-soft-neutral"><?= $this->e($i['group_name'] ?: '—') ?></span></td>
                        <td><?= $this->e($i['uom_name'] ?? '—') ?></td>
                        <td class="num"><?= $this->e(format_money($i['default_purchase_rate'])) ?></td>
                        <td class="num"><?= $this->e(format_money($i['default_sales_rate'])) ?></td>
                        <td class="num">
                            <?php if ((float) $i['total_qty'] != 0): ?>
                                <b><?= $this->e(rtrim(rtrim(number_format((float) $i['total_qty'], 4), '0'), '.')) ?></b>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($i['status'] === 'active'): ?>
                                <span class="badge badge-soft-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <a href="<?= $this->url('/items/' . (int) $i['id'] . '/ledger') ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Stock ledger">
                                <i class="bi bi-journal-text"></i>
                            </a>
                            <?php if ($this->data['canEdit']): ?>
                                <a href="<?= $this->url('/items/' . (int) $i['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary btn-icon" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$this->data['rows']): ?>
                    <tr><td colspan="10"><div class="empty-state"><i class="bi bi-box"></i>No items found.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($this->data['pages'] > 1): ?>
            <nav><ul class="pagination">
                <?php for ($i = 1; $i <= $this->data['pages']; $i++): ?>
                    <li class="page-item <?= $i === $this->data['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $this->url('/items?page=' . $i . '&q=' . urlencode($this->data['search']) . '&group=' . $this->data['groupId']) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>
</div>
