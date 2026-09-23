<?php
/** @var app\Core\View $this */
use app\Services\CompanyContextService;
use app\Services\FeatureService;
use app\Services\PermissionService;

$user = $this->data['currentUser'] ?? $auth ?? null;
$flash = $this->data['flash'] ?? [];
$title = $this->data['title'] ?? 'TradeERP';
$company = $this->data['company'] ?? CompanyContextService::currentCompany();
$branch = CompanyContextService::currentBranch();

$can = fn(string $m, string $d, string $a) => PermissionService::can($m, $d, $a);
$feat = fn(string $k) => FeatureService::isEnabled($k);
$branches = CompanyContextService::branches();
$companies = CompanyContextService::allCompanies();

$navItems = [
    'MAIN' => [
        ['url' => '/', 'icon' => 'speedometer2', 'svg' => 'speed', 'label' => 'Dashboard', 'active' => true],
    ],
    'SALES' => [
        ['url' => '/sales/invoices', 'icon' => 'receipt', 'svg' => 'receipt', 'label' => 'Sales Invoices', 'show' => $feat('sales') && $can('sales', 'sales_invoice', 'view')],
        ['url' => '/sales/orders', 'icon' => 'clipboard-check', 'svg' => 'clipboard', 'label' => 'Sales Orders', 'show' => $feat('sales') && $can('sales', 'sales_order', 'view')],
        ['url' => '/sales/quotations', 'icon' => 'file-earmark-text', 'svg' => 'document', 'label' => 'Sales Quotations', 'show' => $feat('sales') && $can('sales', 'sales_quotation', 'view')],
        ['url' => '/sales/returns', 'icon' => 'arrow-counterclockwise', 'svg' => 'undo', 'label' => 'Sales Returns', 'show' => $feat('sales') && $can('sales', 'sales_return', 'view')],
    ],
    'PURCHASE' => [
        ['url' => '/purchase/invoices', 'icon' => 'receipt', 'svg' => 'receipt', 'label' => 'Purchase Invoices', 'show' => $feat('purchase') && $can('purchase', 'purchase_invoice', 'view')],
        ['url' => '/purchase/orders', 'icon' => 'clipboard-check', 'svg' => 'clipboard', 'label' => 'Purchase Orders', 'show' => $feat('purchase') && $can('purchase', 'purchase_order', 'view')],
        ['url' => '/purchase/quotations', 'icon' => 'file-earmark-text', 'svg' => 'document', 'label' => 'Purchase Quotations', 'show' => $feat('purchase') && $can('purchase', 'purchase_quotation', 'view')],
        ['url' => '/purchase/returns', 'icon' => 'arrow-counterclockwise', 'svg' => 'undo', 'label' => 'Purchase Returns', 'show' => $feat('purchase') && $can('purchase', 'purchase_return', 'view')],
    ],
    'INVENTORY' => [
        ['url' => '/inventory/stock-balance', 'icon' => 'box-seam', 'svg' => 'boxes', 'label' => 'Stock Balance', 'show' => $feat('inventory') && $can('inventory', 'stock', 'view')],
        ['url' => '/inventory/item-cost', 'icon' => 'calculator', 'svg' => 'calculator', 'label' => 'Item-wise Cost', 'show' => $feat('inventory') && $can('inventory', 'stock', 'view')],
        ['url' => '/inventory/low-stock', 'icon' => 'exclamation-triangle', 'svg' => 'alert', 'label' => 'Low Stock', 'show' => $feat('inventory') && $can('inventory', 'stock', 'view')],
        ['url' => '/inventory/transfers', 'icon' => 'arrow-left-right', 'svg' => 'transfer', 'label' => 'Stock Transfer', 'show' => $feat('inventory') && $can('inventory', 'transfer', 'view')],
        ['url' => '/inventory/adjustments', 'icon' => 'sliders', 'svg' => 'sliders', 'label' => 'Stock Adjustment', 'show' => $feat('inventory') && $can('inventory', 'adjustment', 'view')],
        ['url' => '/inventory/opening-stock', 'icon' => 'plus-square', 'svg' => 'plus', 'label' => 'Opening Stock', 'show' => $feat('inventory') && $can('inventory', 'opening_stock', 'create')],
    ],
    'MASTERS' => [
        ['url' => '/customers', 'icon' => 'people', 'svg' => 'people', 'label' => 'Customers', 'show' => $feat('sales') && $can('sales', 'customer', 'view')],
        ['url' => '/suppliers', 'icon' => 'truck', 'svg' => 'truck', 'label' => 'Suppliers', 'show' => $feat('purchase') && $can('purchase', 'supplier', 'view')],
        ['url' => '/items', 'icon' => 'box', 'svg' => 'box', 'label' => 'Items', 'show' => $feat('inventory') && $can('inventory', 'item', 'view')],
        ['url' => '/item-groups', 'icon' => 'folder2', 'svg' => 'folder', 'label' => 'Item Groups', 'show' => $feat('inventory') && $can('inventory', 'item_group', 'view')],
        ['url' => '/uoms', 'icon' => 'rulers', 'svg' => 'rulers', 'label' => 'Units of Measure', 'show' => $feat('inventory') && $can('inventory', 'uom', 'view')],
        ['url' => '/warehouses', 'icon' => 'building', 'svg' => 'building', 'label' => 'Warehouses', 'show' => $feat('inventory') && $feat('warehouses') && $can('inventory', 'warehouse', 'view')],
    ],
    'ACCOUNTING' => [
        ['url' => '/vouchers', 'icon' => 'wallet2', 'svg' => 'wallet', 'label' => 'Vouchers', 'show' => $feat('accounting') && $can('accounting', 'voucher', 'view')],
        ['url' => '/accounts', 'icon' => 'diagram-3', 'svg' => 'chart', 'label' => 'Chart of Accounts', 'show' => $feat('accounting') && $can('accounting', 'account', 'view')],
        ['url' => '/accounts/defaults', 'icon' => 'sliders', 'svg' => 'sliders', 'label' => 'Accounting Defaults', 'show' => $feat('accounting') && $can('accounting', 'defaults', 'view')],
    ],
    'REPORTS' => [
        ['url' => '/reports', 'icon' => 'file-earmark-bar-graph', 'svg' => 'chart', 'label' => 'All Reports', 'show' => $can('reports', 'report', 'view')],
    ],
    'SETTINGS' => [
        ['url' => '/users', 'icon' => 'person-gear', 'svg' => 'user', 'label' => 'Users', 'show' => $can('users', 'user', 'view')],
        ['url' => '/roles', 'icon' => 'shield-lock', 'svg' => 'shield', 'label' => 'Roles & Permissions', 'show' => $can('users', 'role', 'view')],
        ['url' => '/companies', 'icon' => 'building', 'svg' => 'building', 'label' => 'Companies', 'show' => $can('settings', 'company', 'view')],
        ['url' => '/branches', 'icon' => 'diagram-2', 'svg' => 'branches', 'label' => 'Branches', 'show' => $feat('branches') && $can('settings', 'branch', 'view')],
        ['url' => '/settings/financial-years', 'icon' => 'calendar-range', 'svg' => 'calendar', 'label' => 'Financial Years', 'show' => $can('settings', 'financial_year', 'view')],
        ['url' => '/settings/numbering', 'icon' => 'hash', 'svg' => 'hash', 'label' => 'Document Numbering', 'show' => $can('settings', 'numbering', 'view')],
        ['url' => '/settings/payment-modes', 'icon' => 'wallet2', 'svg' => 'wallet', 'label' => 'Payment Modes', 'show' => $feat('accounting') && $can('settings', 'payment_mode', 'view')],
        ['url' => '/settings/taxes', 'icon' => 'percent', 'svg' => 'percent', 'label' => 'Tax Rates', 'show' => $can('settings', 'tax', 'view')],
        ['url' => '/settings/closing', 'icon' => 'calendar-lock', 'svg' => 'lock', 'label' => 'Closing Period', 'show' => $can('settings', 'closing', 'view')],
        ['url' => '/settings/backups', 'icon' => 'cloud-arrow-down', 'svg' => 'backup', 'label' => 'Backups', 'show' => $can('settings', 'backup', 'view')],
        ['url' => '/settings', 'icon' => 'gear', 'svg' => 'gear', 'label' => 'Settings', 'show' => $can('settings', 'setting', 'view')],
        ['url' => '/settings/features', 'icon' => 'toggle-on', 'svg' => 'toggle', 'label' => 'Features', 'show' => $can('settings', 'feature', 'view')],
        ['url' => '/setup', 'icon' => 'magic', 'svg' => 'magic', 'label' => 'Setup Wizard', 'show' => $can('settings', 'setup', 'view')],
        ['url' => '/audit', 'icon' => 'clock-history', 'svg' => 'clock', 'label' => 'Audit Trail', 'show' => $can('audit', 'audit_log', 'view')],
    ],
];

$currentPath = '/' . ltrim((string) ($this->data['request']?->path() ?? '/'), '/');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= $this->e(\app\Core\Csrf::token()) ?>">
<title><?= $this->e($title) ?> — TradeERP</title>
<link rel="stylesheet" href="<?= $this->asset('vendor/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= $this->asset('vendor/bootstrap-icons.min.css') ?>">
<link rel="stylesheet" href="<?= $this->asset('css/app.css') ?>">
</head>
<body>
<div class="erp-shell">

    <aside class="erp-sidebar collapsed" id="erpSidebar" aria-collapsed="true">
        <div class="erp-brand">
            <span class="brand-mark"><?= \app\Core\Icon::svg('brand', 20) ?></span>
            <span>TradeERP<small>Trading &amp; Accounting</small></span>
        </div>

        <nav class="erp-nav">
            <?php foreach ($navItems as $label => $items): ?>
                <?php
                // Desktop rail starts collapsed; keep the core operational groups expanded by default.
                $sectionActive = false;
                foreach ($items as $item) {
                    if (isset($item['show']) && !$item['show']) continue;
                    $a = rtrim($currentPath, '/') === $item['url'] || ($item['url'] === '/' && $currentPath === '/');
                    if ($a) { $sectionActive = true; break; }
                }
                $defaultExpandedGroups = ['MAIN', 'SALES', 'PURCHASE', 'INVENTORY'];
                $groupExpanded = $sectionActive || in_array($label, $defaultExpandedGroups, true);
                $visibleItems = array_values(array_filter($items, fn($it) => !isset($it['show']) || $it['show']));
                if (!$visibleItems) continue;
                ?>
                <div class="erp-nav-group" data-group="<?= $this->e($label) ?>" data-expanded="<?= $groupExpanded ? '1' : '0' ?>">
                    <button type="button" class="erp-nav-group-toggle" aria-expanded="<?= $groupExpanded ? 'true' : 'false' ?>" title="Expand / collapse">
                        <span class="erp-nav-label"><?= $this->e($label) ?></span>
                        <svg class="erp-nav-chevron" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </button>
                    <div class="erp-nav-group-items">
                        <?php foreach ($visibleItems as $item):
                            $active = rtrim($currentPath, '/') === $item['url'] || ($item['url'] === '/' && $currentPath === '/');
                        ?>
                            <a class="erp-nav-item <?= $active ? 'active' : '' ?>" href="<?= $this->url($item['url']) ?>">
                                <?= \app\Core\Icon::svg($item['svg'] ?? $item['icon']) ?>
                                <span><?= $this->e($item['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="erp-sidebar-footer">
            TradeERP <span class="ver">v<?= \app\Core\View::e(APP_VERSION) ?></span>
        </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="erp-main collapsed">
        <header class="erp-topbar">
            <button class="btn btn-outline-secondary btn-sm erp-hamburger" data-sidebar-toggle type="button" aria-label="Toggle sidebar">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
            </button>

            <div class="d-none d-md-flex align-items-center gap-2">
                <!-- Company switcher -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= \app\Core\Icon::svg('company', 14) ?><span class="ms-1"><?= $this->e($company['name'] ?? 'TradeERP') ?></span>
                    </button>
                    <ul class="dropdown-menu shadow-sm">
                        <li><h6 class="dropdown-header">Switch company</h6></li>
                        <?php foreach ($companies as $c): ?>
                            <li>
                                <form method="post" action="<?= $this->url('/settings/company/switch') ?>">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="company_id" value="<?= (int) $c['id'] ?>">
                                    <button class="dropdown-item <?= (int) $c['id'] === (int) ($company['id'] ?? 0) ? 'active' : '' ?>" type="submit">
                                        <?= $this->e($c['name']) ?>
                                    </button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= $this->url('/companies') ?>">Manage companies…</a></li>
                    </ul>
                </div>

                <!-- Branch switcher -->
                <?php if ($feat('branches') && count($branches) > 1): ?>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <?= \app\Core\Icon::svg('branches', 14) ?><span class="ms-1"><?= $this->e($branch['name'] ?? 'Head Office') ?></span>
                        </button>
                        <ul class="dropdown-menu shadow-sm">
                            <li><h6 class="dropdown-header">Branch context</h6></li>
                            <?php foreach ($branches as $b): ?>
                                <li>
                                    <form method="post" action="<?= $this->url('/settings/branch/switch') ?>">
                                        <?= $this->csrfField() ?>
                                        <input type="hidden" name="branch_id" value="<?= (int) $b['id'] ?>">
                                        <button class="dropdown-item <?= (int) $b['id'] === (int) ($branch['id'] ?? 0) ? 'active' : '' ?>" type="submit">
                                            <?= $this->e($b['name']) ?>
                                            <?php if ((int) $b['is_head_office'] === 1): ?><span class="badge badge-soft-info ms-1">HO</span><?php endif; ?>
                                        </button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <div class="erp-topbar-search ms-auto d-none d-md-block">
                <input type="search" id="navFilter" class="form-control" placeholder="Quick jump…  (Ctrl+K)" autocomplete="off">
            </div>

            <div class="erp-topbar-right">
                <div class="dropdown">
                    <a href="#" class="user-chip text-decoration-none text-dark" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="user-avatar"><?= $this->e(strtoupper(mb_substr($user['name'] ?? '?', 0, 1))) ?></span>
                        <span>
                            <span class="u-name d-block"><?= $this->e($user['name'] ?? '') ?></span>
                            <span class="u-role d-block"><?= $this->e($user['role_name'] ?? '') ?></span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><h6 class="dropdown-header"><?= $this->e($user['name'] ?? '') ?></h6></li>
                        <li><span class="dropdown-item-text small text-muted"><?= $this->e($user['email'] ?? '') ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="<?= $this->url('/logout') ?>">
                                <?= $this->csrfField() ?>
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-1"></i> Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="erp-content">
            <?php foreach ($flash as $type => $message): ?>
                <div class="erp-alert erp-alert-<?= $this->e($type) ?>" data-auto-dismiss>
                    <i class="bi bi-<?= $type === 'success' ? 'check-circle' : ($type === 'error' ? 'x-circle' : 'info-circle') ?>"></i>
                    <span><?= $this->e($message) ?></span>
                </div>
            <?php endforeach; ?>

            <?= $this->content() ?>
        </main>
    </div>
</div>

<script src="<?= $this->asset('vendor/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= $this->asset('js/app.js') ?>"></script>
</body>
</html>
