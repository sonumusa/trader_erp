<?php /** @var app\Core\View $this */ ?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Audit Trail</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Audit Trail</li>
            </ol>
        </nav>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body">
        <form method="get" action="<?= $this->url('/audit') ?>" class="row g-2 align-items-center mb-2">
            <div class="col-auto">
                <input type="search" name="q" class="form-control form-control-sm" placeholder="Search…"
                       value="<?= $this->e($this->data['search']) ?>" style="width:200px">
            </div>
            <div class="col-auto">
                <select name="module" class="form-select form-select-sm">
                    <option value="">All modules</option>
                    <?php foreach ($this->data['modules'] as $m): ?>
                        <option value="<?= $this->e($m['module']) ?>" <?= $this->data['module'] === $m['module'] ? 'selected' : '' ?>>
                            <?= $this->e(ucfirst($m['module'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="doc_type" class="form-select form-select-sm">
                    <option value="">All documents</option>
                    <?php foreach ($this->data['docTypes'] as $d): ?>
                        <option value="<?= $this->e($d['document_type']) ?>" <?= $this->data['docType'] === $d['document_type'] ? 'selected' : '' ?>>
                            <?= $this->e(str_replace('_', ' ', $d['document_type'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-funnel"></i> Filter</button>
            </div>
        </form>

        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Document</th>
                        <th>Description</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->data['rows'] as $a): ?>
                    <tr>
                        <td class="small text-nowrap"><?= $this->e(format_datetime($a['created_at'])) ?></td>
                        <td><?= $this->e($a['user_name'] ?? 'System') ?></td>
                        <td><span class="badge badge-soft-info"><?= $this->e($a['action']) ?></span></td>
                        <td><span class="badge badge-soft-neutral"><?= $this->e($a['module']) ?></span></td>
                        <td class="small">
                            <?= $this->e($a['document_type'] ?? '—') ?>
                            <?php if ($a['document_id']): ?> <span class="text-muted">#<?= (int) $a['document_id'] ?></span><?php endif; ?>
                        </td>
                        <td class="small"><?= $this->e($a['description'] ?? '') ?></td>
                        <td class="small text-muted"><?= $this->e($a['ip_address'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$this->data['rows']): ?>
                    <tr><td colspan="7"><div class="empty-state"><i class="bi bi-clock-history"></i>No audit entries match your filters.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($this->data['pages'] > 1): ?>
            <nav>
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $this->data['pages']; $i++): ?>
                        <li class="page-item <?= $i === $this->data['page'] ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $this->url('/audit?page=' . $i . '&module=' . urlencode($this->data['module']) . '&q=' . urlencode($this->data['search'])) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>
