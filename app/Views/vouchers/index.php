<?php /** @var app\Core\View $this */
$data = $this->data['data'];
$types = $this->data['types'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Vouchers</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Vouchers</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <?php if ($this->data['canCreate']): ?>
            <div class="dropdown">
                <button class="btn btn-primary btn-icon dropdown-toggle" data-bs-toggle="dropdown" type="button">
                    <i class="bi bi-plus-lg"></i> New Voucher
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <?php foreach ($types as $key => $meta): ?>
                        <li><a class="dropdown-item" href="<?= $this->url('/vouchers/new?type=' . $key) ?>"><?= $this->e($meta['label']) ?> (<?= $this->e($meta['prefix']) ?>)</a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body">
        <form method="get" action="<?= $this->url('/vouchers') ?>" class="row g-2 align-items-center mb-2">
            <div class="col-auto">
                <select name="type" class="form-select form-select-sm">
                    <option value="">All types</option>
                    <?php foreach ($types as $key => $meta): ?>
                        <option value="<?= $key ?>" <?= $this->data['type'] === $key ? 'selected' : '' ?>><?= $this->e($meta['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= $this->e($this->data['from']) ?>"></div>
            <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= $this->e($this->data['to']) ?>"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-primary" type="submit">Filter</button></div>
        </form>

        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table">
                <thead>
                    <tr>
                        <th>Voucher No.</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Party</th>
                        <th>Narration</th>
                        <th class="num">Amount</th>
                        <th>Status</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['rows'] as $v): ?>
                    <tr>
                        <td class="fw-semibold"><?= $this->e($v['voucher_no']) ?></td>
                        <td><span class="badge badge-soft-info"><?= $this->e($types[$v['voucher_type']]['label'] ?? $v['voucher_type']) ?></span></td>
                        <td><?= $this->e(format_date($v['voucher_date'])) ?></td>
                        <td class="small"><?= $this->e($v['party_name'] ?? '—') ?></td>
                        <td class="small text-muted"><?= $this->e(mb_strimwidth($v['narration'] ?: '—', 0, 40, '…')) ?></td>
                        <td class="num fw-semibold"><?= $this->e(format_money($v['amount'])) ?></td>
                        <td>
                            <?php if ($v['status'] === 'posted'): ?>
                                <span class="badge badge-soft-success">Posted</span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger">Cancelled</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <a href="<?= $this->url('/vouchers/' . (int) $v['id']) ?>" class="btn btn-sm btn-outline-primary btn-icon" title="View"><i class="bi bi-eye"></i></a>
                            <a href="<?= $this->url('/vouchers/' . (int) $v['id'] . '/print') ?>" class="btn btn-sm btn-outline-secondary btn-icon" title="Print"><i class="bi bi-printer"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['rows']): ?>
                    <tr><td colspan="8"><div class="empty-state"><i class="bi bi-wallet2"></i>No vouchers found.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($data['pages'] > 1): ?>
            <nav><ul class="pagination">
                <?php for ($i = 1; $i <= $data['pages']; $i++): ?>
                    <li class="page-item <?= $i === $data['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $this->url('/vouchers?page=' . $i . '&type=' . urlencode($this->data['type'])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>
</div>
