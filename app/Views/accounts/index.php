<?php /** @var app\Core\View $this */
$typeBadge = [
    'asset'     => ['badge-soft-info', 'bi-coin'],
    'liability' => ['badge-soft-warning', 'bi-bank'],
    'income'    => ['badge-soft-success', 'bi-graph-up-arrow'],
    'expense'   => ['badge-soft-danger', 'bi-graph-down-arrow'],
    'equity'    => ['badge-soft-violet', 'bi-pie-chart'],
];

/** Flatten the tree into [node, depth, hasChildren] rows in display order. */
$flat = [];
$walk = function (array $nodes, int $depth) use (&$walk, &$flat): void {
    foreach ($nodes as $node) {
        $children = (int) $node['is_group'] === 1 ? ($node['children'] ?? []) : [];
        $flat[] = ['node' => $node, 'depth' => $depth, 'children' => count($children) > 0];
        if ($children !== []) {
            $walk($children, $depth + 1);
        }
    }
};
$walk($this->data['tree'], 0);
?>
<?php $this->layout('app'); ?>

<div class="erp-page-head">
    <div>
        <h1>Chart of Accounts</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= $this->url('/') ?>">Home</a></li>
                <li class="breadcrumb-item active">Chart of Accounts</li>
            </ol>
        </nav>
    </div>
    <div class="actions">
        <a href="<?= $this->url('/accounts/defaults') ?>" class="btn btn-outline-primary btn-icon">
            <i class="bi bi-sliders"></i> Accounting Defaults
        </a>
        <?php if ($this->data['canCreate']): ?>
            <a href="<?= $this->url('/accounts/new') ?>" class="btn btn-primary btn-icon">
                <i class="bi bi-plus-lg"></i> New Account
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="erp-card">
    <div class="erp-card-body p-0">
        <div class="table-responsive erp-table-wrap">
            <table class="table erp-table mb-0">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th class="actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody id="coaBody">
                <?php foreach ($flat as $i => $item): ?>
                    <?php
                    $node = $item['node'];
                    [$badge, $icon] = $typeBadge[$node['account_type']] ?? ['badge-soft-neutral', 'bi-question'];
                    $pad = 16 + $item['depth'] * 24;
                    ?>
                    <tr class="coa-row" data-depth="<?= $this->e($item['depth']) ?>" data-has-children="<?= $item['children'] ? '1' : '0' ?>">
                        <td>
                            <div style="padding-left:<?= $pad ?>px" class="d-flex align-items-center gap-1">
                                <?php if ($item['children']): ?>
                                    <button type="button" class="btn btn-sm btn-link p-0 text-muted coa-toggle" title="Collapse" aria-expanded="true">
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="d-inline-block" style="width:18px"></span>
                                <?php endif; ?>
                                <i class="bi <?= $icon ?> me-1 text-muted small"></i>
                                <span class="<?= (int) $node['is_group'] === 1 ? 'fw-bold' : '' ?>"><?= $this->e($node['name']) ?></span>
                            </div>
                        </td>
                        <td><code><?= $this->e($node['code']) ?></code></td>
                        <td><span class="badge <?= $badge ?>"><?= $this->e(ucfirst($node['account_type'])) ?></span></td>
                        <td>
                            <?php if ((int) $node['is_group'] === 1): ?>
                                <span class="badge badge-soft-neutral">Group</span>
                            <?php else: ?>
                                <span class="badge badge-soft-neutral">Detail</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $node['is_active'] === 1): ?>
                                <span class="badge badge-soft-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-soft-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <a href="<?= $this->url('/accounts/' . (int) $node['id'] . '/ledger') ?>" class="btn btn-sm btn-outline-primary btn-icon" title="View ledger">
                                <i class="bi bi-journal-text"></i>
                            </a>
                            <?php if ($this->data['canEdit']): ?>
                                <a href="<?= $this->url('/accounts/' . (int) $node['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary btn-icon" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const body = document.getElementById('coaBody');
    if (!body) return;
    const rows = Array.from(body.querySelectorAll('tr.coa-row'));

    // Collapse top-level groups by default for a clean view
    rows.forEach((row) => {
        if (row.dataset.hasChildren === '1') {
            toggleRow(row, false);
        }
    });

    function toggleRow(row, expand) {
        const depth = parseInt(row.dataset.depth, 10);
        let idx = rows.indexOf(row) + 1;
        while (idx < rows.length && parseInt(rows[idx].dataset.depth, 10) > depth) {
            rows[idx].style.display = expand ? '' : 'none';
            idx++;
        }
        const btn = row.querySelector('.coa-toggle');
        if (btn) {
            btn.setAttribute('aria-expanded', expand ? 'true' : 'false');
            btn.title = expand ? 'Collapse' : 'Expand';
            btn.querySelector('i').classList.toggle('bi-chevron-down', expand);
            btn.querySelector('i').classList.toggle('bi-chevron-right', !expand);
        }
    }

    body.addEventListener('click', (e) => {
        const btn = e.target.closest('.coa-toggle');
        if (!btn) return;
        const row = btn.closest('tr');
        const expanded = btn.getAttribute('aria-expanded') === 'true';
        toggleRow(row, !expanded);
    });
})();
</script>
