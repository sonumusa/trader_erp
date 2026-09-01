<?php /** @var app\Core\View $this */
$steps = $this->data['steps'];
$state = $this->data['state'];
$company = \app\Services\CompanyContextService::currentCompany();
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Setup Wizard</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Setup Wizard</li>
            </ol>
        </nav>
    </div>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-magic"></i>
    <span>Work through the steps to configure TradeERP for your business. Steps marked <b>Phase X</b> become active when that module is built.</span>
</div>

<div class="row g-3">
    <?php foreach ($steps as $i => $step): ?>
        <?php [$key, $label, $phase] = $step;
        $done = (bool) ($state[$key] ?? false);
        $live = $phase === null;
        $n = $i + 1;
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="erp-card h-100">
                <div class="erp-card-body d-flex align-items-start gap-3">
                    <div class="stat-icon <?= $done ? 'icon-green' : ($live ? 'icon-blue' : 'icon-slate') ?>" style="width:40px;height:40px">
                        <?php if ($done): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php else: ?>
                            <i class="bi bi-<?= $n ?>"></i>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold d-flex align-items-center gap-2">
                            <?= $this->e($label) ?>
                            <?php if ($done): ?>
                                <span class="badge badge-soft-success">Done</span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted mt-1">
                            <?php if ($live): ?>
                                Ready to configure
                            <?php else: ?>
                                Available with <?= $this->e($phase) ?>
                            <?php endif; ?>
                        </div>
                        <?php if ($live): ?>
                            <button class="btn btn-sm btn-outline-primary btn-icon mt-2"
                                    data-bs-toggle="modal" data-bs-target="#stepModal"
                                    data-step="<?= $n ?>" data-label="<?= $this->e($label) ?>">
                                <i class="bi bi-gear"></i> Configure
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Step modal -->
<div class="modal fade" id="stepModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="/setup/step/1" id="stepForm">
                <?= $this->csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="stepModalTitle">Configure</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="stepModalBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save step</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const STEP_FORMS = {
    1: `<div class="mb-3"><label class="form-label">Company name</label><input class="form-control" name="name" required value="<?= $this->e($company['name'] ?? '') ?>"></div>
        <div class="row g-2"><div class="col-6"><label class="form-label">Code</label><input class="form-control" name="code" value="<?= $this->e($company['code'] ?? '') ?>"></div>
        <div class="col-6"><label class="form-label">Currency</label>
        <select class="form-select" name="currency"><option>PKR</option><option>USD</option><option>SAR</option><option>AED</option></select></div></div>
        <div class="mt-2"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= $this->e($company['address'] ?? '') ?>"></div>
        <div class="row g-2 mt-1"><div class="col-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= $this->e($company['phone'] ?? '') ?>"></div>
        <div class="col-6"><label class="form-label">Email</label><input class="form-control" name="email" type="email" value="<?= $this->e($company['email'] ?? '') ?>"></div></div>`,
    2: `<div class="row g-2"><div class="col-6"><label class="form-label">Start date</label><input type="date" class="form-control" name="start_date" required></div>
        <div class="col-6"><label class="form-label">End date</label><input type="date" class="form-control" name="end_date" required></div></div>
        <p class="form-hint mt-2">Used for document numbering and reporting periods.</p>`,
    3: `<div class="mb-3"><label class="form-label">Branch name</label><input class="form-control" name="name" required placeholder="e.g. Lahore Branch"></div>
        <div class="mb-3"><label class="form-label">Branch code</label><input class="form-control" name="code" required maxlength="20" placeholder="e.g. LHR"></div>
        <label class="form-label">Address</label><input class="form-control" name="address">`,
    6: `<div class="mb-3"><label class="form-label">Warehouse name</label><input class="form-control" name="name" required></div>
        <div class="mb-3"><label class="form-label">Code</label><input class="form-control" name="code" required maxlength="30"></div>`,
    7: `<div class="mb-3"><label class="form-label">UOM name</label><input class="form-control" name="name" required placeholder="e.g. Piece"></div>
        <div class="mb-3"><label class="form-label">Code</label><input class="form-control" name="code" required maxlength="20" placeholder="e.g. PCS"></div>`,
    4: `<p class="text-muted">The installer creates a complete default Chart of Accounts (Assets, Liabilities, Equity, Income, Expenses with sub-groups).</p>
        <p class="text-muted mb-0">If it is missing, click <b>Create default COA</b> to seed it now.</p>`,
    5: `<p class="text-muted">Accounting Defaults map automatic postings (Cash → Cash in Hand, Sales → Sales, etc.) so users never pick GL accounts manually.</p>
        <p class="text-muted mb-0">Open the <b>Accounting Defaults</b> screen to review the mappings.</p>`,
    8: `<div class="mb-3"><label class="form-label">Tax name</label><input class="form-control" name="name" required placeholder="e.g. Sales Tax"></div>
        <div class="mb-3"><label class="form-label">Rate (%)</label><input class="form-control" name="rate" type="number" step="0.0001" min="0" max="100" required value="0"></div>
        <p class="form-hint">Attach tax categories to items; sales & purchase documents use them automatically.</p>`,
    11: `<p class="text-muted">Opening stock records starting quantities AND posts the value to the books (Dr Inventory / Cr Opening Balance Equity).</p>
        <p class="text-muted mb-0">Continue to the <b>Opening Stock</b> page to enter items, quantities and rates.</p>`
};

document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('stepModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', (e) => {
        const btn = e.relatedTarget;
        const n = btn.dataset.step;
        document.getElementById('stepModalTitle').textContent = btn.dataset.label;
        document.getElementById('stepModalBody').innerHTML = STEP_FORMS[n] || '<p class="text-muted">This step is not yet available.</p>';
        const form = document.getElementById('stepForm');
        form.action = '/setup/step/' + n;
        // Step 5 (default accounts) opens the defaults screen instead of posting
        const submitBtn = form.querySelector('button[type="submit"]');
        if (n === '5') {
            submitBtn.textContent = 'Open Defaults';
            submitBtn.onclick = (ev) => { ev.preventDefault(); window.location = '/accounts/defaults'; };
        } else {
            submitBtn.textContent = 'Save step';
            submitBtn.onclick = null;
        }
    });
});
</script>
