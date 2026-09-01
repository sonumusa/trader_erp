<?php /** @var app\Core\View $this */
$doc = $this->data['doc'];
$type = $this->data['type'];
$company = $this->data['company'];
$isInvoice = $type === 'purchase_invoice';
$isReturn = $type === 'purchase_return';
$no = $isInvoice ? $doc['invoice_no'] : ($isReturn ? $doc['return_no'] : '');
$date = $isInvoice ? $doc['invoice_date'] : ($isReturn ? $doc['return_date'] : '');
?>
<?php $this->layout('print'); ?>

<div class="print-head d-flex justify-content-between align-items-start">
    <div>
        <div class="print-title"><?= $this->e($company['name'] ?? 'TradeERP') ?></div>
        <div class="small text-muted"><?= $this->e($company['address'] ?? '') ?></div>
        <div class="small text-muted"><?= $this->e($company['phone'] ?? '') ?> <?= $this->e($company['email'] ?? '') ?></div>
    </div>
    <div class="text-end">
        <div class="print-title"><?= $isInvoice ? 'PURCHASE INVOICE' : ($isReturn ? 'PURCHASE RETURN' : 'PURCHASE DOCUMENT') ?></div>
        <div class="small">No: <b><?= $this->e($no) ?></b></div>
        <div class="small">Date: <b><?= $this->e(format_date($date)) ?></b></div>
    </div>
</div>

<div class="row mb-3">
    <div class="col-6">
        <div class="small text-muted">Supplier</div>
        <div class="fw-bold"><?= $this->e($doc['supplier']['name'] ?? '') ?></div>
        <div class="small"><?= $this->e($doc['supplier']['address'] ?? '') ?></div>
        <div class="small"><?= $this->e($doc['supplier']['phone'] ?? '') ?> <?= $this->e($doc['supplier']['email'] ?? '') ?></div>
        <div class="small">NTN: <?= $this->e($doc['supplier']['tax_number'] ?? '') ?></div>
    </div>
    <div class="col-6 text-end">
        <?php if ($isInvoice && $doc['payment_mode']): ?>
            <div class="small">Payment: <b><?= $this->e($doc['payment_mode']['name']) ?></b></div>
            <div class="small">Paid: <b><?= $this->e(format_money($doc['paid_amount'])) ?></b></div>
        <?php endif; ?>
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
            <th class="text-end">Discount</th>
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
            <td class="text-end"><?= (float) $line['discount'] > 0 ? $this->e(format_money($line['discount'])) : '—' ?></td>
            <td class="text-end"><?= (float) $line['tax_amount'] > 0 ? $this->e(format_money($line['tax_amount'])) : '—' ?></td>
            <td class="text-end"><?= $this->e(format_money($line['amount'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr><td colspan="7" class="text-end"><b>Subtotal</b></td><td class="text-end"><b><?= $this->e(format_money($doc['subtotal'])) ?></b></td></tr>
        <tr><td colspan="7" class="text-end">Discount</td><td class="text-end"><?= $this->e(format_money($doc['discount_total'])) ?></td></tr>
        <tr><td colspan="7" class="text-end">Tax</td><td class="text-end"><?= $this->e(format_money($doc['tax_total'])) ?></td></tr>
        <tr><td colspan="7" class="text-end"><b>Total</b></td><td class="text-end"><b><?= $this->e(format_money($doc['total'])) ?></b></td></tr>
        <?php if ($isInvoice && $doc['paid_amount'] > 0): ?>
            <tr><td colspan="7" class="text-end">Paid</td><td class="text-end"><?= $this->e(format_money($doc['paid_amount'])) ?></td></tr>
            <tr><td colspan="7" class="text-end">Balance</td><td class="text-end"><?= $this->e(format_money($doc['total'] - $doc['paid_amount'])) ?></td></tr>
        <?php endif; ?>
    </tfoot>
</table>

<?php if ($doc['status'] === 'cancelled'): ?>
    <div class="alert alert-danger">CANCELLED — <?= $this->e($doc['cancelled_reason'] ?? '') ?></div>
<?php endif; ?>

<div class="row print-sign text-center">
    <div class="col-3">Received by</div>
    <div class="col-3">Prepared by</div>
    <div class="col-3">Checked by</div>
    <div class="col-3">Authorised by</div>
</div>
