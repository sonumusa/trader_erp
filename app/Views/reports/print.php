<?php /** @var app\Core\View $this */
$data = $this->data['data'];
$filters = $this->data['filters'];
$company = $this->data['company'];
?>
<?php $this->layout('print'); ?>

<div class="print-head d-flex justify-content-between align-items-start">
    <div>
        <div class="print-title"><?= $this->e($company['name'] ?? 'TradeERP') ?></div>
        <div class="small text-muted"><?= $this->e($company['address'] ?? '') ?></div>
    </div>
    <div class="text-end">
        <div class="print-title"><?= $this->e($data['title']) ?></div>
        <div class="small">
            <?php if ($filters['from']): ?>From <b><?= $this->e(format_date($filters['from'])) ?></b><?php endif; ?>
            <?php if ($filters['to']): ?> To <b><?= $this->e(format_date($filters['to'])) ?></b><?php endif; ?>
            &nbsp;·&nbsp; <?= $this->e(format_date(date('Y-m-d'))) ?>
        </div>
    </div>
</div>

<?php if (isset($data['income'])): ?>
    <div class="row">
        <div class="col-6">
            <h6>Income</h6>
            <table class="table table-bordered print-table">
                <?php foreach ($data['income'] as $r): ?>
                    <tr><td><?= $this->e($r['code'] . ' — ' . $r['name']) ?></td><td class="text-end"><?= $this->e(format_money($r['net'])) ?></td></tr>
                <?php endforeach; ?>
                <tr class="fw-bold"><td>Total income</td><td class="text-end"><?= $this->e(format_money($data['total_income'])) ?></td></tr>
            </table>
        </div>
        <div class="col-6">
            <h6>Expenses</h6>
            <table class="table table-bordered print-table">
                <?php foreach ($data['expenses'] as $r): ?>
                    <tr><td><?= $this->e($r['code'] . ' — ' . $r['name']) ?></td><td class="text-end"><?= $this->e(format_money(abs($r['net']))) ?></td></tr>
                <?php endforeach; ?>
                <tr class="fw-bold"><td>Total expenses</td><td class="text-end"><?= $this->e(format_money(abs($data['total_expense']))) ?></td></tr>
            </table>
        </div>
        <div class="col-12"><h5>Net profit / (loss): <b><?= $this->e(format_money($data['net_profit'])) ?></b></h5></div>
    </div>
<?php elseif (isset($data['assets'])): ?>
    <div class="row">
        <div class="col-6">
            <h6>Assets <small>as of <?= $this->e(format_date($data['as_of'])) ?></small></h6>
            <table class="table table-bordered print-table">
                <?php foreach ($data['assets'] as $r): ?>
                    <tr><td><?= $this->e($r['code'] . ' — ' . $r['name']) ?></td><td class="text-end"><?= $this->e(format_money($r['balance'])) ?></td></tr>
                <?php endforeach; ?>
                <tr class="fw-bold"><td>Total assets</td><td class="text-end"><?= $this->e(format_money($data['total_assets'])) ?></td></tr>
            </table>
        </div>
        <div class="col-6">
            <h6>Liabilities & Equity</h6>
            <table class="table table-bordered print-table">
                <?php foreach (array_merge($data['liabilities'], $data['equity']) as $r): ?>
                    <tr><td><?= $this->e($r['code'] . ' — ' . $r['name']) ?></td><td class="text-end"><?= $this->e(format_money($r['balance'])) ?></td></tr>
                <?php endforeach; ?>
                <tr class="fw-bold"><td>Total liabilities & equity</td><td class="text-end"><?= $this->e(format_money($data['total_liab_equity'])) ?></td></tr>
            </table>
        </div>
    </div>
<?php else: ?>
    <table class="table table-bordered print-table">
        <thead>
            <tr>
                <?php foreach ($data['columns'] as $label): ?>
                    <th><?= $this->e($label) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($data['rows'] as $row): ?>
            <tr>
                <?php foreach (array_keys($data['columns']) as $key): ?>
                    <td><?= isset($row[$key]) && is_numeric($row[$key]) ? $this->e(format_money($row[$key])) : $this->e($row[$key] ?? '') ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<div class="row print-sign text-center">
    <div class="col-4">Prepared by</div>
    <div class="col-4">Checked by</div>
    <div class="col-4">Authorised by</div>
</div>
