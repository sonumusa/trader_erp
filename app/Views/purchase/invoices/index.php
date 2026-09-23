<?php /** @var app\Core\View $this */
$data = $this->data['data'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Purchase Invoices</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Purchase Invoices</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <a href="<?= $this->url('/purchase/orders') ?>" class="btn btn-outline-secondary btn-icon"><i class="bi bi-clipboard-check"></i> Orders</a>
        <?php if ($this->data['canCreate']): ?>
            <a href="<?= $this->url('/purchase/invoices/new') ?>" class="btn btn-primary btn-icon"><i class="bi bi-plus-lg"></i> New Purchase Invoice</a>
        <?php endif; ?>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body">
        <form method="get" action="<?= $this->url('/purchase/invoices') ?>" class="row g-2 align-items-center mb-2">
            <div class="col-auto flex-grow-1" style="max-width:280px">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" class="form-control" placeholder="Invoice no. or supplier…" value="<?= $this->e($this->data['q']) ?>">
                </div>
            </div>
            <div class="col-auto"><input type="date" name="from" class="form-control form-control-sm" value="<?= $this->e($this->data['from']) ?>"></div>
            <div class="col-auto"><input type="date" name="to" class="form-control form-control-sm" value="<?= $this->e($this->data['to']) ?>"></div>
            <div class="col-auto"><button class="btn btn-sm btn-outline-primary" type="submit">Filter</button></div>
        </form>

        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table">
                <thead>
                    <tr>
                        <th>Invoice No.</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th class="num">Subtotal</th>
                        <th class="num">Tax</th>
                        <th class="num">Total</th>
                        <th class="num">Paid</th>
                        <th>Status</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['rows'] as $inv): ?>
                    <tr>
                        <td class="fw-semibold"><?= $this->e($inv['invoice_no']) ?></td>
                        <td><?= $this->e(format_date($inv['invoice_date'])) ?></td>
                        <td><?= $this->e($inv['supplier_name']) ?></td>
                        <td class="num"><?= $this->e(format_money($inv['subtotal'])) ?></td>
                        <td class="num"><?= $this->e(format_money($inv['tax_total'])) ?></td>
                        <td class="num fw-semibold"><?= $this->e(format_money($inv['total'])) ?></td>
                        <td class="num"><?= $inv['paid_amount'] > 0 ? $this->e(format_money($inv['paid_amount'])) : '—' ?></td>
                        <td>
                            <?php if ($inv['status'] === 'posted'): ?>
                                <span class="badge badge-soft-success">Posted</span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger">Cancelled</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <a href="<?= $this->url('/purchase/invoices/' . (int) $inv['id']) ?>" class="btn btn-sm btn-outline-primary btn-icon" title="View"><i class="bi bi-eye"></i></a>
                            <?php if (($this->data['canEdit'] ?? false) && $inv['status'] === 'posted'): ?><a href="<?= $this->url('/purchase/invoices/' . (int) $inv['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Edit"><i class="bi bi-pencil"></i></a><?php endif; ?>
                            <a href="<?= $this->url('/purchase/invoices/' . (int) $inv['id'] . '/print') ?>" class="btn btn-sm btn-outline-secondary btn-icon" title="Print"><i class="bi bi-printer"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['rows']): ?>
                    <tr><td colspan="9"><div class="empty-state"><i class="bi bi-receipt"></i>No purchase invoices found.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($data['pages'] > 1): ?>
            <nav><ul class="pagination">
                <?php for ($i = 1; $i <= $data['pages']; $i++): ?>
                    <li class="page-item <?= $i === $data['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $this->url('/purchase/invoices?page=' . $i . '&q=' . urlencode($this->data['q'])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    </div>
</div>
