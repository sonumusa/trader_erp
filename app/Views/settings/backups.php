<?php /** @var app\Core\View $this */
$backups = $this->data['backups'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Backups</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= $this->url('/settings') ?>">Settings</a></li>
                <li class="breadcrumb-item active">Backups</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <?php if ($this->data['canCreate']): ?>
            <form method="post" action="<?= $this->url('/settings/backups') ?>" class="d-inline">
                <?= $this->csrfField() ?>
                <button class="btn btn-primary btn-icon"><i class="bi bi-cloud-arrow-down"></i> Create Backup</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="erp-alert erp-alert-info">
    <i class="bi bi-shield-lock"></i>
    <span>Backups are stored <b>outside the web root</b> (<code>storage/backups</code>) and are never publicly
        accessible. Downloads require the backup permission. The export uses <code>mysqldump</code> when available
        and falls back to a pure-PHP SQL export — so it works on Hostinger shared hosting without shell access.</span>
</div>

<div class="erp-card">
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <th>Backup file</th>
                        <th class="num">Size</th>
                        <th>Created</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($backups as $b): ?>
                    <tr>
                        <td class="fw-semibold"><i class="bi bi-file-earmark-sql me-1 text-muted"></i><?= $this->e($b['name']) ?></td>
                        <td class="num"><?= $this->e(number_format($b['size'])) ?> bytes</td>
                        <td><?= $this->e(format_datetime($b['created_at'])) ?></td>
                        <td class="actions-cell">
                            <?php if ($this->data['canDownload']): ?>
                                <a href="<?= $this->url('/settings/backups/' . urlencode($b['name']) . '/download') ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Download">
                                    <i class="bi bi-download"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($this->data['canDelete']): ?>
                                <form method="post" action="<?= $this->url('/settings/backups/' . urlencode($b['name']) . '/delete') ?>" class="d-inline"
                                      data-confirm="Delete this backup? This cannot be undone.">
                                    <?= $this->csrfField() ?>
                                    <button class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$backups): ?>
                    <tr><td colspan="4"><div class="empty-state"><i class="bi bi-cloud-arrow-down"></i>No backups yet. Create your first backup.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
