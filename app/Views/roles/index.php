<?php /** @var app\Core\View $this */ ?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Roles &amp; Permissions</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Roles &amp; Permissions</li>
            </ol>
        </nav>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Description</th>
                        <th>Users</th>
                        <th>System role</th>
                        <th class="actions-cell">Permissions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->data['roles'] as $r): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= $this->e($r['name']) ?></div>
                            <div class="small text-muted"><?= $this->e($r['slug']) ?></div>
                        </td>
                        <td class="small"><?= $this->e($r['description'] ?? '—') ?></td>
                        <td><span class="badge badge-soft-neutral"><?= (int) $r['user_count'] ?> user(s)</span></td>
                        <td>
                            <?php if ((int) $r['is_system'] === 1): ?>
                                <span class="badge badge-soft-info">System</span>
                            <?php else: ?>
                                <span class="badge badge-soft-neutral">Custom</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <a href="<?= $this->url('/roles/' . (int) $r['id'] . '/permissions') ?>" class="btn btn-sm btn-outline-primary btn-icon">
                                <i class="bi bi-shield-check"></i> Edit permissions
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
