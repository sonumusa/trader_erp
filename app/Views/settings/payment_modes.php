<?php /** @var app\Core\View $this */
$modes = $this->data['modes'];
$accounts = $this->data['accounts'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Payment Modes</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/settings') ?>">Settings</a></li>
                <li class="breadcrumb-item active">Payment Modes</li>
            </ol>
        </nav>
    </div>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-wallet2"></i>
    <span>Each payment mode can carry a default account. When a user selects <b>Cash</b>, the system auto-selects Cash in Hand; select <b>Meezan Bank</b> and the Meezan account is used. Users never choose GL accounts manually.</span>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="erp-card">
            <div class="erp-card-header"><h6><i class="bi bi-plus-circle me-1 text-primary"></i> Add payment mode</h6></div>
            <div class="erp-card-body">
                <form method="post" action="<?= $this->url('/settings/payment-modes') ?>">
                    <?= $this->csrfField() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="pm-name">Name <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="pm-name" name="name" required maxlength="80" placeholder="e.g. Meezan Bank">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="pm-code">Code <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="pm-code" name="code" required maxlength="30" placeholder="e.g. MZB">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="pm-account">Default account</label>
                            <select class="form-select" id="pm-account" name="account_id">
                                <option value="">— None —</option>
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?= (int) $acc['id'] ?>"><?= $this->e($acc['code'] . ' — ' . $acc['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="pm-cash" name="is_cash" value="1">
                                <label class="form-check-label small" for="pm-cash">Cash mode</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="pm-bank" name="is_bank" value="1">
                                <label class="form-check-label small" for="pm-bank">Bank mode</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary btn-icon"><i class="bi bi-plus-lg"></i> Add mode</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="erp-card">
            <div class="erp-card-body p-0">
                <div class="table-responsive erp-table-wrap">
                    <table class="table erp-table mb-0">
                        <thead>
                            <tr>
                                <th>Mode</th>
                                <th>Code</th>
                                <th>Default account</th>
                                <th>Kind</th>
                                <th>Status</th>
                                <th class="actions-cell">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($modes as $m): ?>
                            <tr>
                                <td class="fw-semibold"><?= $this->e($m['name']) ?></td>
                                <td><code><?= $this->e($m['code']) ?></code></td>
                                <td class="small">
                                    <?php if ($m['account_id']): ?>
                                        <span class="badge badge-soft-info"><?= $this->e($m['account_code'] . ' ' . $m['account_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int) $m['is_cash'] === 1): ?><span class="badge badge-soft-success">Cash</span><?php endif; ?>
                                    <?php if ((int) $m['is_bank'] === 1): ?><span class="badge badge-soft-primary">Bank</span><?php endif; ?>
                                    <?php if ((int) $m['is_cash'] === 0 && (int) $m['is_bank'] === 0): ?><span class="badge badge-soft-neutral">Other</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int) $m['is_active'] === 1): ?>
                                        <span class="badge badge-soft-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-soft-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <button class="btn btn-sm btn-outline-primary btn-icon" title="Edit"
                                            data-bs-toggle="modal" data-bs-target="#pmEditModal"
                                            data-id="<?= (int) $m['id'] ?>"
                                            data-name="<?= $this->e($m['name']) ?>"
                                            data-code="<?= $this->e($m['code']) ?>"
                                            data-account="<?= (int) ($m['account_id'] ?? 0) ?>"
                                            data-cash="<?= (int) $m['is_cash'] ?>"
                                            data-bank="<?= (int) $m['is_bank'] ?>"
                                            data-active="<?= (int) $m['is_active'] ?>"
                                            data-system="<?= (int) $m['is_system'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ((int) $m['is_system'] === 0): ?>
                                        <form method="post" action="<?= $this->url('/settings/payment-modes/' . (int) $m['id'] . '/delete') ?>" class="d-inline"
                                              data-confirm="Delete payment mode '<?= $this->e($m['name']) ?>'?">
                                            <?= $this->csrfField() ?>
                                            <button class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$modes): ?>
                            <tr><td colspan="6"><div class="empty-state"><i class="bi bi-wallet2"></i>No payment modes yet.</div></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit modal -->
<div class="modal fade" id="pmEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="" id="pmEditForm">
                <?= $this->csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Edit payment mode</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="pe-name">Name</label>
                        <input type="text" class="form-control" id="pe-name" name="name" required maxlength="80">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pe-code">Code</label>
                        <input type="text" class="form-control" id="pe-code" name="code" required maxlength="30">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="pe-account">Default account</label>
                        <select class="form-select" id="pe-account" name="account_id">
                            <option value="">— None —</option>
                            <?php foreach ($accounts as $acc): ?>
                                <option value="<?= (int) $acc['id'] ?>"><?= $this->e($acc['code'] . ' — ' . $acc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-flex gap-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="pe-cash" name="is_cash" value="1">
                            <label class="form-check-label small" for="pe-cash">Cash mode</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="pe-bank" name="is_bank" value="1">
                            <label class="form-check-label small" for="pe-bank">Bank mode</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="pe-active" name="is_active" value="1">
                            <label class="form-check-label small" for="pe-active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('pmEditModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', (e) => {
        const b = e.relatedTarget;
        document.getElementById('pe-name').value = b.dataset.name;
        document.getElementById('pe-code').value = b.dataset.code;
        document.getElementById('pe-account').value = b.dataset.account;
        document.getElementById('pe-cash').checked = b.dataset.cash === '1';
        document.getElementById('pe-bank').checked = b.dataset.bank === '1';
        document.getElementById('pe-active').checked = b.dataset.active === '1';
        document.getElementById('pmEditForm').action = '/settings/payment-modes/' + b.dataset.id;
    });
});
</script>
