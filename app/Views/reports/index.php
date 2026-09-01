<?php /** @var app\Core\View $this */
$catalogue = $this->data['catalogue'];
$groupLabels = ['sales' => 'Sales', 'purchase' => 'Purchase', 'parties' => 'Customers & Suppliers', 'stock' => 'Inventory', 'accounting' => 'Accounting'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Reports</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Reports</li>
            </ol>
        </nav>
    </div>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-info-circle"></i>
    <span>Every report is generated from the accounting ledger, stock ledger or document tables — never from separate totals. All support date filters, sorting and Excel/CSV/print export.</span>
</div>

<div class="row g-3">
    <?php foreach ($catalogue as $group => $reports): ?>
        <div class="col-lg-6 col-xl-4">
            <div class="erp-card h-100">
                <div class="erp-card-header">
                    <h6 class="text-uppercase" style="font-size:.72rem"><?= $this->e($groupLabels[$group] ?? ucfirst($group)) ?></h6>
                </div>
                <div class="erp-card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($reports as $key => [$label]): ?>
                            <a class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2"
                               href="<?= $this->url('/reports/' . $key) ?>">
                                <i class="bi bi-file-earmark-bar-graph text-muted"></i>
                                <span><?= $this->e($label) ?></span>
                                <i class="bi bi-chevron-right ms-auto small text-muted"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
