<?php /** @var app\Core\View $this */ ?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Page not found</h1>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body">
        <div class="empty-state" style="padding:3.5rem 1rem">
            <i class="bi bi-search"></i>
            <h5 class="mt-2">404 — The page you requested could not be found</h5>
            <p class="text-muted mx-auto" style="max-width:440px">
                It may have been moved, renamed, or removed. Use the navigation on the left to continue.
            </p>
            <a href="<?= $this->url('/') ?>" class="btn btn-primary btn-icon mt-2">
                <i class="bi bi-house"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>
