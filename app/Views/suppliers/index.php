<?php /** @var app\Core\View $this */ ?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Suppliers</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Suppliers</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <?php if ($this->data['canCreate']): ?>
            <a href="<?= $this->url('/suppliers/new') ?>" class="btn btn-primary btn-icon">
                <i class="bi bi-plus-lg"></i> New Supplier
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body">
        <form method="get" action="<?= $this->url('/suppliers') ?>" class="row g-2 align-items-center mb-2">
            <div class="col-auto flex-grow-1" style="max-width:360px">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" class="form-control" placeholder="Search by name, code, phone…"
                           value="<?= $this->e($this->data['search']) ?>">
                </div>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-primary" type="submit">Search</button>
                <?php if ($this->data['search'] !== ''): ?>
                    <a href="<?= $this->url('/customers') ?>" class="btn btn-sm btn-link">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table">
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Contact</th>
                        <th>City</th>
                        <th class="num">Outstanding</th>
                        <th>Status</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->data['rows'] as $c): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= $this->e($c['name']) ?></div>
                            <div class="small text-muted"><?= $this->e($c['code']) ?></div>
                        </td>
                        <td class="small">
                            <?= $this->e($c['phone'] ?: ($c['mobile'] ?: '—')) ?>
                            <div class="text-muted"><?= $this->e($c['email'] ?: '') ?></div>
                        </td>
                        <td><?= $this->e($c['city'] ?: '—') ?></td>
                        <td class="num">
                            <?php $bal = (float) $c['outstanding'];
                            if ($bal > 0.001): ?>
                                <span class="badge badge-soft-danger"><?= $this->e(format_money($bal)) ?></span>
                            <?php elseif ($bal < -0.001): ?>
                                <span class="badge badge-soft-info"><?= $this->e(format_money(abs($bal))) ?> credit</span>
                            <?php else: ?>
                                <span class="text-muted">0.00</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($c['status'] === 'active'): ?>
                                <span class="badge badge-soft-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <a href="<?= $this->url('/suppliers/' . (int) $c['id'] . '/ledger') ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Ledger">
                                <i class="bi bi-journal-text"></i>
                            </a>
                            <?php if ($this->data['canEdit']): ?>
                                <a href="<?= $this->url('/suppliers/' . (int) $c['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary btn-icon" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$this->data['rows']): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="bi bi-truck"></i>No suppliers found.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($this->data['pages'] > 1): ?>
            <nav><ul class="pagination">
                <?php for ($i = 1; $i <= $this->data['pages']; $i++): ?>
                    <li class="page-item <?= $i === $this->data['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $this->url('/suppliers?page=' . $i . '&q=' . urlencode($this->data['search'])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>
</div>
