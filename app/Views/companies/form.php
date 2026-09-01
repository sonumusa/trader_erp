<?php /** @var app\Core\View $this */
$company = $this->data['company'];
$old = \app\Core\Session::get('_old_input', []);
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1><?= $company ? 'Edit Company' : 'New Company' ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/companies') ?>">Companies</a></li>
                <li class="breadcrumb-item active"><?= $company ? 'Edit' : 'New' ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="erp-card">
            <div class="erp-card-body">
                <form method="post" action="<?= $company ? $this->url('/companies/' . (int) $company['id']) : $this->url('/companies') ?>">
                    <?= $this->csrfField() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Company name <span class="required-star">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required
                                   value="<?= $this->e($old['name'] ?? ($company['name'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="code">Company code</label>
                            <input type="text" class="form-control" id="code" name="code" maxlength="20"
                                   value="<?= $this->e($old['code'] ?? ($company['code'] ?? '')) ?>">
                            <div class="form-hint">Used in document numbers, e.g. LHR-SI-26-000001.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address">Address</label>
                            <input type="text" class="form-control" id="address" name="address"
                                   value="<?= $this->e($old['address'] ?? ($company['address'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone"
                                   value="<?= $this->e($old['phone'] ?? ($company['phone'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= $this->e($old['email'] ?? ($company['email'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="currency">Currency</label>
                            <select class="form-select" id="currency" name="currency">
                                <?php foreach (['PKR' => 'PKR — Pakistani Rupee', 'USD' => 'USD — US Dollar', 'SAR' => 'SAR — Saudi Riyal', 'AED' => 'AED — UAE Dirham', 'GBP' => 'GBP — British Pound', 'EUR' => 'EUR — Euro'] as $code => $label): ?>
                                    <option value="<?= $code ?>" <?= ($company['currency'] ?? 'PKR') === $code ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if (!$company): ?>
                        <div class="col-12 mt-1"><hr class="my-2"></div>
                        <div class="col-12">
                            <h6 class="fw-bold">Financial year</h6>
                            <p class="form-hint">A default financial year is created with the company. You can add more later under Settings → Financial Years.</p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="fy_start">Start date</label>
                            <input type="date" class="form-control" id="fy_start" name="fy_start"
                                   value="<?= $this->e($old['fy_start'] ?? (date('Y') . '-07-01')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="fy_end">End date</label>
                            <input type="date" class="form-control" id="fy_end" name="fy_end"
                                   value="<?= $this->e($old['fy_end'] ?? ((date('Y') + 1) . '-06-30')) ?>">
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-icon">
                            <i class="bi bi-check-lg"></i> <?= $company ? 'Save Changes' : 'Create Company' ?>
                        </button>
                        <a href="<?= $this->url('/companies') ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($company): ?>
    <div class="col-lg-4">
        <div class="erp-card">
            <div class="erp-card-header"><h6><i class="bi bi-info-circle me-1 text-primary"></i> Company setup</h6></div>
            <div class="erp-card-body small">
                <div class="d-flex justify-content-between"><span class="text-muted">Head Office</span><b>Created</b></div>
                <div class="d-flex justify-content-between mt-1"><span class="text-muted">Default warehouse</span><b>Created</b></div>
                <div class="d-flex justify-content-between mt-1"><span class="text-muted">Document series</span><b><?= (int) \app\Services\DocumentNumberService::countFor((int) $company['id']) ?> types</b></div>
                <div class="d-flex justify-content-between mt-1"><span class="text-muted">Financial year</span><b><?= $this->e(format_date($company['fiscal_year_start'], 'Y-m-d')) ?> → <?= $this->e(format_date($company['fiscal_year_end'], 'Y-m-d')) ?></b></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
