<?php /** @var app\Core\View $this */
$entries = $this->data['entries'];
$back = $this->data['back'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Journal Entries</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->e($back) ?>">Back to document</a></li>
                <li class="breadcrumb-item active">Journal</li>
            </ol>
        </nav>
    </div>
</div>

<?php foreach ($entries as $entry): ?>
    <div class="erp-card mb-3">
        <div class="erp-card-header">
            <h6>
                <i class="bi bi-journal-text me-1 text-primary"></i>
                <?= $this->e($entry['entry_no']) ?>
                <span class="badge badge-soft-neutral ms-2"><?= $this->e(str_replace('_', ' ', $entry['voucher_type'])) ?></span>
                <span class="badge badge-soft-<?= $entry['status'] === 'posted' ? 'success' : 'warning' ?> ms-1"><?= $this->e($entry['status']) ?></span>
            </h6>
            <div class="ms-auto small text-muted"><?= $this->e(format_date($entry['entry_date'])) ?></div>
        </div>
        <div class="erp-card-body p-0">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Narration</th>
                        <th class="num">Debit</th>
                        <th class="num">Credit</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entry['lines'] as $line): ?>
                    <tr>
                        <td>
                            <?= $this->e($line['account_code'] . ' — ' . $line['account_name']) ?>
                            <?php if ($line['party_type']): ?>
                                <span class="badge badge-soft-info ms-1"><?= $this->e($line['party_type']) ?> #<?= (int) $line['party_id'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= $this->e($line['narration'] ?: '') ?></td>
                        <td class="num"><?= (float) $line['debit'] > 0 ? $this->e(format_money($line['debit'])) : '—' ?></td>
                        <td class="num"><?= (float) $line['credit'] > 0 ? $this->e(format_money($line['credit'])) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="erp-card-footer small text-muted"><?= $this->e($entry['narration']) ?></div>
    </div>
<?php endforeach; ?>

<?php if (!$entries): ?>
    <div class="erp-card"><div class="erp-card-body"><div class="empty-state"><i class="bi bi-journal-text"></i>No journal entries.</div></div></div>
<?php endif; ?>
