<?php /** @var app\Core\View $this */
$data = $this->data['data'];
$account = $data['account'];
$from = $this->data['from'];
$to = $this->data['to'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Ledger — <?= $this->e($account['name']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/accounts') ?>">Chart of Accounts</a></li>
                <li class="breadcrumb-item active"><?= $this->e($account['code'] . ' ' . $account['name']) ?></li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <a href="<?= $this->url('/accounts') ?>" class="btn btn-outline-secondary btn-icon">
            <i class="bi bi-arrow-left"></i> Chart of Accounts
        </a>
    </div>
</div>

<div class="erp-card mb-3">
    <div class="erp-card-body">
        <form method="get" action="<?= $this->url('/accounts/' . (int) $account['id'] . '/ledger') ?>" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label mb-0">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?= $this->e($from) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label mb-0">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?= $this->e($to) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary">Apply</button>
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
                        <th>Voucher No.</th>
                        <th>Description</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Balance</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['rows'] as $row): ?>
                    <tr>
                        <td class="text-nowrap"><?= $this->e(format_date($row['entry_date'])) ?></td>
                        <td>
                            <span class="fw-semibold"><?= $this->e($row['voucher_no']) ?></span>
                            <?php if (str_contains((string) $row['voucher_no'], '(R)')): ?>
                                <span class="badge badge-soft-warning ms-1">Reversal</span>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?= $this->e($row['voucher_type'] ?: '') ?>
                            <?php if ($row['party_type']): ?>
                                <span class="badge badge-soft-neutral ms-1"><?= $this->e($row['party_type']) ?> #<?= (int) $row['party_id'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= (float) $row['debit'] > 0 ? $this->e(format_money($row['debit'])) : '—' ?></td>
                        <td class="num"><?= (float) $row['credit'] > 0 ? $this->e(format_money($row['credit'])) : '—' ?></td>
                        <td class="num fw-semibold"><?= $this->e(format_money($row['balance'])) ?></td>
                        <td class="small text-muted">
                            <?php if ($row['source_document_type']): ?>
                                <?= $this->e(str_replace('_', ' ', $row['source_document_type'])) ?>
                                <?php if ($row['source_document_id']): ?>#<?= (int) $row['source_document_id'] ?><?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['rows']): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="bi bi-journal-text"></i>No ledger entries in this range.</div></td></tr>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="3">Totals (filtered range)</td>
                        <td class="num"><?= $this->e(format_money($data['debit_total'])) ?></td>
                        <td class="num"><?= $this->e(format_money($data['credit_total'])) ?></td>
                        <td class="num"><?= $this->e(format_money($data['debit_total'] - $data['credit_total'])) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if ($data['pages'] > 1): ?>
            <nav class="p-2">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $data['pages']; $i++): ?>
                        <li class="page-item <?= $i === $data['page'] ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $this->url('/accounts/' . (int) $account['id'] . '/ledger?page=' . $i . '&from=' . urlencode($from) . '&to=' . urlencode($to)) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>
