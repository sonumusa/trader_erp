<?php /** @var app\Core\View $this */
$doc = $this->data['doc'];
$company = $this->data['company'];
?>
<?php $this->layout('print'); ?>

<div class="print-head d-flex justify-content-between align-items-start">
    <div>
        <div class="print-title"><?= $this->e($company['name'] ?? 'TradeERP') ?></div>
        <div class="small text-muted"><?= $this->e($company['address'] ?? '') ?></div>
        <div class="small text-muted"><?= $this->e($company['phone'] ?? '') ?> <?= $this->e($company['email'] ?? '') ?></div>
    </div>
    <div class="text-end">
        <div class="print-title">SALES RETURN</div>
        <div class="small">No: <b><?= $this->e($doc['return_no']) ?></b></div>
        <div class="small">Date: <b><?= $this->e(format_date($doc['return_date'])) ?></b></div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-6">
        <div class="small text-muted">Customer</div>
        <div class="fw-bold"><?= $this->e($doc['customer']['name'] ?? 'Walk-in customer') ?></div>
        <div class="small"><?= $this->e($doc['customer']['address'] ?? '') ?></div>
        <div class="small"><?= $this->e($doc['customer']['phone'] ?? '') ?> <?= $this->e($doc['customer']['email'] ?? '') ?></div>
    </div>
    <div class="col-6 text-end">
        <div class="small">Reference invoice: <b><?= $doc['reference_invoice_id'] ? 'SI #' . (int) $doc['reference_invoice_id'] : '—' ?></b></div>
        <div class="small"><?= $this->e($doc['narration'] ?: '') ?></div>
    </div>
</div>

<table class="table table-bordered print-table">
    <thead>
        <tr>
            <th class="text-center" style="width:40px">#</th>
            <th>Item</th>
            <th class="text-center">UOM</th>
            <th class="text-end">Qty</th>
            <th class="text-end">Rate</th>
            <th class="text-end">Tax</th>
            <th class="text-end">Amount</th>
        </tr>
    </thead>
    <tbody>
    <?php $i = 0; foreach ($doc['items'] as $line): $i++; ?>
        <tr>
            <td class="text-center"><?= $i ?></td>
            <td><?= $this->e($line['item_name']) ?><div class="small text-muted"><?= $this->e($line['item_code']) ?></div></td>
            <td class="text-center"><?= $this->e($line['uom_code'] ?? '') ?></td>
            <td class="text-end"><?= $this->e(rtrim(rtrim(number_format((float) $line['quantity'], 4), '0'), '.')) ?></td>
            <td class="text-end"><?= $this->e(format_money($line['rate'])) ?></td>
            <td class="text-end"><?= (float) $line['tax_amount'] > 0 ? $this->e(format_money($line['tax_amount'])) : '—' ?></td>
            <td class="text-end"><?= $this->e(format_money($line['amount'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr><td colspan="6" class="text-end"><b>Total</b></td><td class="text-end"><b><?= $this->e(format_money($doc['total'])) ?></b></td></tr>
    </tfoot>
</table>

<?php if ($doc['status'] === 'cancelled'): ?>
    <div class="alert alert-danger">CANCELLED — <?= $this->e($doc['cancelled_reason'] ?? '') ?></div>
<?php endif; ?>

<div class="row print-sign text-center">
    <div class="col-4">Received back by</div>
    <div class="col-4">Prepared by</div>
    <div class="col-4">Authorised by</div>
</div>
