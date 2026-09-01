<?php /** @var app\Core\View $this */
$data = $this->data['data'];
$search = $this->data['search'];
$sort = $this->data['sort'];
$dir = $this->data['dir'];
$canCreate = $this->data['canCreate'];
$canEdit = $this->data['canEdit'];
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Users</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Users</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <?php if ($canCreate): ?>
            <a href="<?= $this->url('/users/new') ?>" class="btn btn-primary btn-icon">
                <i class="bi bi-plus-lg"></i> New User
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body">
        <form method="get" action="<?= $this->url('/users') ?>" class="row g-2 align-items-center mb-2">
            <div class="col-auto flex-grow-1" style="max-width:340px">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="search" name="q" class="form-control" placeholder="Search by name or email…"
                           value="<?= $this->e($search) ?>">
                </div>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-primary" type="submit">Search</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= $this->url('/users') ?>" class="btn btn-sm btn-link">Clear</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table">
                <thead>
                    <tr>
                        <th class="sortable" data-sort="name">User <?= $sort === 'name' ? '<i class="bi bi-arrow-' . ($dir === 'ASC' ? 'up' : 'down') . '"></i>' : '' ?></th>
                        <th class="sortable" data-sort="email">Email</th>
                        <th>Role</th>
                        <th class="sortable" data-sort="status">Status</th>
                        <th class="sortable" data-sort="last_login_at">Last login</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['rows'] as $u): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="user-avatar" style="width:30px;height:30px;font-size:.72rem"><?= $this->e(strtoupper(mb_substr($u['name'], 0, 1))) ?></span>
                                <div>
                                    <div class="fw-semibold"><?= $this->e($u['name']) ?></div>
                                    <div class="small text-muted">ID #<?= (int) $u['id'] ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?= $this->e($u['email']) ?></td>
                        <td><span class="badge badge-soft-neutral"><?= $this->e($u['role_name']) ?></span></td>
                        <td>
                            <?php if ($u['status'] === 'active'): ?>
                                <span class="badge badge-soft-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= $this->e($u['last_login_at'] ? format_datetime($u['last_login_at']) : 'Never') ?></td>
                        <td class="actions-cell">
                            <?php if ($canEdit): ?>
                                <a href="<?= $this->url('/users/' . (int) $u['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (($this->data['canDelete'] ?? false) && (int) $u['id'] !== (int) ($this->data['currentUser']['id'] ?? 0)): ?>
                                <form method="post" action="<?= $this->url('/users/' . (int) $u['id'] . '/delete') ?>" class="d-inline" data-confirm="Delete this user? Their account will be deactivated.">
                                    <?= $this->csrfField() ?>
                                    <button class="btn btn-sm btn-outline-danger btn-icon" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$data['rows']): ?>
                    <tr><td colspan="6"><div class="empty-state"><i class="bi bi-person-x"></i>No users found.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($data['pages'] > 1): ?>
            <nav>
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $data['pages']; $i++): ?>
                        <li class="page-item <?= $i === $data['page'] ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $this->url('/users?page=' . $i . '&q=' . urlencode($search)) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.sortable').forEach((th) => {
    th.addEventListener('click', () => {
        const sort = th.dataset.sort;
        const dir = '<?= $this->e($dir) ?>' === 'ASC' ? 'DESC' : 'ASC';
        const q = encodeURIComponent('<?= $this->e($search) ?>');
        window.location = '<?= $this->url('/users') ?>?sort=' + sort + '&dir=' + dir + '&q=' + q;
    });
});
</script>
