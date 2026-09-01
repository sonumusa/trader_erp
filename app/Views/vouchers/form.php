<?php /** @var app\Core\View $this */
$types = $this->data['types'];
$voucherType = $this->data['voucherType'];
$customers = $this->data['customers'];
$suppliers = $this->data['suppliers'];
$bankModes = $this->data['bankModes'];
$accounts = $this->data['accounts'];
$cashBankAccounts = $this->data['cashBankAccounts'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>New <?= $this->e($types[$voucherType]['label']) ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/vouchers') ?>">Vouchers</a></li>
                <li class="breadcrumb-item active">New</li>
            </ol>
        </nav>
    </div>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-info-circle"></i>
    <span>Posting a voucher immediately creates a balanced journal entry — debit always equals credit.
        Party-based vouchers automatically use Accounts Receivable / Payable; bank vouchers use the linked
        account of the selected payment mode.</span>
</div>

<form method="post" action="<?= $this->url('/vouchers') ?>" id="voucherForm">
    <?= $this->csrfField() ?>

    <div class="erp-card mb-3">
        <div class="erp-card-header"><h6><i class="bi bi-wallet2 me-1 text-primary"></i> Voucher</h6></div>
        <div class="erp-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="voucher_type">Voucher type <span class="required-star">*</span></label>
                    <select class="form-select" id="voucher_type" name="voucher_type" required>
                        <?php foreach ($types as $key => $meta): ?>
                            <option value="<?= $key ?>" <?= $key === $voucherType ? 'selected' : '' ?>><?= $this->e($meta['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="voucher_date">Voucher date <span class="required-star">*</span></label>
                    <input type="date" class="form-control" id="voucher_date" name="voucher_date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="narration">Narration</label>
                    <input type="text" class="form-control" id="narration" name="narration" maxlength="500">
                </div>
            </div>
        </div>
    </div>

    <!-- Party + amount panel (cash/bank payment & receipt) -->
    <div class="erp-card mb-3" id="panel-simple">
        <div class="erp-card-header"><h6><i class="bi bi-person-lines-fill me-1 text-primary"></i> Party & amount</h6></div>
        <div class="erp-card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="party_type">Party type</label>
                    <select class="form-select" id="party_type" name="party_type">
                        <option value="">— None —</option>
                        <option value="customer">Customer</option>
                        <option value="supplier">Supplier</option>
                    </select>
                </div>
                <div class="col-md-4" id="party-customer-wrap" style="display:none">
                    <label class="form-label" for="party_id">Customer</label>
                    <select class="form-select" id="party_id" name="party_id">
                        <option value="0">— Select —</option>
                        <?php foreach ($customers as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= $this->e($c['code'] . ' — ' . $c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4" id="party-supplier-wrap" style="display:none">
                    <label class="form-label" for="party_id">Supplier</label>
                    <select class="form-select" id="party_id" name="party_id">
                        <option value="0">— Select —</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= $this->e($s['code'] . ' — ' . $s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2" id="bank-mode-wrap" style="display:none">
                    <label class="form-label" for="payment_mode_id">Bank / mode</label>
                    <select class="form-select" id="payment_mode_id" name="payment_mode_id">
                        <option value="0">— Default —</option>
                        <?php foreach ($bankModes as $pm): ?>
                            <option value="<?= (int) $pm['id'] ?>"><?= $this->e($pm['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="amount">Amount <span class="required-star">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount" required value="0.00">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="counter_account_id">
                        <span id="counter-label">Account</span> <span class="required-star">*</span>
                    </label>
                    <select class="form-select" id="counter_account_id" name="counter_account_id">
                        <option value="0">— Auto from party (or select) —</option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= (int) $acc['id'] ?>"><?= $this->e($acc['code'] . ' — ' . $acc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint mt-1" id="counter-hint">With a customer/supplier selected, the receivable/payable account is used automatically.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contra panel -->
    <div class="erp-card mb-3" id="panel-contra" style="display:none">
        <div class="erp-card-header"><h6><i class="bi bi-arrow-left-right me-1 text-primary"></i> Cash ↔ Bank transfer</h6></div>
        <div class="erp-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="from_account_id">From account <span class="required-star">*</span></label>
                    <select class="form-select" id="from_account_id" name="from_account_id">
                        <option value="0">— Select —</option>
                        <?php foreach ($cashBankAccounts as $acc): ?>
                            <option value="<?= (int) $acc['id'] ?>"><?= $this->e($acc['code'] . ' — ' . $acc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="to_account_id">To account <span class="required-star">*</span></label>
                    <select class="form-select" id="to_account_id" name="to_account_id">
                        <option value="0">— Select —</option>
                        <?php foreach ($cashBankAccounts as $acc): ?>
                            <option value="<?= (int) $acc['id'] ?>"><?= $this->e($acc['code'] . ' — ' . $acc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="amount">Amount <span class="required-star">*</span></label>
                    <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount" required value="0.00">
                </div>
            </div>
        </div>
    </div>

    <!-- Journal lines panel -->
    <div class="erp-card mb-3" id="panel-journal" style="display:none">
        <div class="erp-card-header">
            <h6><i class="bi bi-journal-text me-1 text-primary"></i> Journal lines</h6>
            <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="addJournalLine"><i class="bi bi-plus-lg"></i> Add line</button>
        </div>
        <div class="erp-card-body" id="journalLines">
            <div class="row g-2 align-items-center mb-2 jl-row">
                <div class="col-md-5">
                    <select class="form-select form-select-sm" name="lines[account_id][]" required>
                        <option value="0">— Select account —</option>
                        <?php foreach ($accounts as $acc): ?>
                            <option value="<?= (int) $acc['id'] ?>"><?= $this->e($acc['code'] . ' — ' . $acc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2"><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="lines[debit][]" placeholder="Debit"></div>
                <div class="col-md-2"><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="lines[credit][]" placeholder="Credit"></div>
                <div class="col-md-2"><input type="text" class="form-control form-control-sm" name="lines[narration][]" placeholder="Narration"></div>
                <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger jl-remove"><i class="bi bi-x-lg"></i></button></div>
            </div>
        </div>
        <div class="erp-card-footer">
            <span class="form-hint">Debit must equal credit for the voucher to post.</span>
        </div>
    </div>

    <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-check-lg"></i> Post Voucher</button>
        <a href="<?= $this->url('/vouchers') ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>

<script>
(function () {
    const typeSel = document.getElementById('voucher_type');
    const simple = document.getElementById('panel-simple');
    const contra = document.getElementById('panel-contra');
    const journal = document.getElementById('panel-journal');
    const partyType = document.getElementById('party_type');
    const custWrap = document.getElementById('party-customer-wrap');
    const supWrap = document.getElementById('party-supplier-wrap');
    const bankMode = document.getElementById('bank-mode-wrap');
    const counterLabel = document.getElementById('counter-label');

    function sync() {
        const t = typeSel.value;
        const isSimple = ['cash_payment', 'bank_payment', 'cash_receipt', 'bank_receipt'].includes(t);
        const isContra = t === 'contra';
        const isJournal = t === 'journal';
        simple.style.display = isSimple ? '' : 'none';
        contra.style.display = isContra ? '' : 'none';
        journal.style.display = isJournal ? '' : 'none';
        bankMode.style.display = (t === 'bank_payment' || t === 'bank_receipt') ? '' : 'none';
        counterLabel.textContent = (t === 'cash_payment' || t === 'bank_payment') ? 'Account to pay' : 'Account to receive';
        // clear hidden fields
        document.getElementById('from_account_id').disabled = !isContra;
        document.getElementById('to_account_id').disabled = !isContra;
    }

    function syncParty() {
        const t = partyType.value;
        custWrap.style.display = t === 'customer' ? '' : 'none';
        supWrap.style.display = t === 'supplier' ? '' : 'none';
    }

    typeSel.addEventListener('change', sync);
    partyType.addEventListener('change', syncParty);
    document.getElementById('addJournalLine').addEventListener('click', () => {
        const rows = document.getElementById('journalLines');
        const first = rows.querySelector('.jl-row');
        const clone = first.cloneNode(true);
        clone.querySelectorAll('input').forEach((i) => i.value = '');
        clone.querySelector('select').selectedIndex = 0;
        rows.appendChild(clone);
        clone.querySelector('.jl-remove').addEventListener('click', () => {
            if (rows.querySelectorAll('.jl-row').length > 1) clone.remove();
        });
    });
    document.querySelectorAll('.jl-remove').forEach((b) => b.addEventListener('click', () => {
        const rows = document.getElementById('journalLines');
        if (rows.querySelectorAll('.jl-row').length > 1) b.closest('.jl-row').remove();
    }));

    sync();
    syncParty();
})();
</script>
