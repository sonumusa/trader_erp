<?php /** @var app\Core\View $this */ ?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Companies</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Companies</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <a href="<?= $this->url('/companies/new') ?>" class="btn btn-primary btn-icon">
            <i class="bi bi-plus-lg"></i> New Company
        </a>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Code</th>
                        <th>Branches</th>
                        <th>Financial years</th>
                        <th>Currency</th>
                        <th>Status</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->data['companies'] as $c): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold">
                                <?= $this->e($c['name']) ?>
                                <?php if ((int) $c['id'] === (int) $this->data['currentId']): ?>
                                    <span class="badge badge-soft-info ms-1">Active</span>
                                <?php endif; ?>
                            </div>
                            <div class="small text-muted"><?= $this->e($c['email'] ?: '—') ?></div>
                        </td>
                        <td><span class="badge badge-soft-neutral"><?= $this->e($c['code'] ?: '—') ?></span></td>
                        <td><span class="badge badge-soft-neutral"><?= (int) $c['branch_count'] ?> branch(es)</span></td>
                        <td><span class="badge badge-soft-neutral"><?= (int) $c['fy_count'] ?> year(s)</span></td>
                        <td><?= $this->e($c['currency']) ?></td>
                        <td>
                            <?php if ((int) $c['id'] === (int) $this->data['currentId']): ?>
                                <span class="badge badge-soft-success">Active context</span>
                            <?php else: ?>
                                <form method="post" action="<?= $this->url('/settings/company/switch') ?>" class="d-inline">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="company_id" value="<?= (int) $c['id'] ?>">
                                    <button class="btn btn-sm btn-outline-primary btn-icon"><i class="bi bi-arrow-right-circle"></i> Switch</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <a href="<?= $this->url('/companies/' . (int) $c['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if ((int) $c['id'] !== (int) $this->data['currentId']): ?>
                                <form method="post" action="<?= $this->url('/companies/' . (int) $c['id'] . '/delete') ?>" class="d-inline" data-confirm="Archive company '<?= $this->e($c['name']) ?>'? Its records will be retained and it will disappear from active company selectors.">
                                    <?= $this->csrfField() ?>
                                    <button class="btn btn-sm btn-outline-danger btn-icon" title="Archive company"><i class="bi bi-archive"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
