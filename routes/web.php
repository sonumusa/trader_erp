<?php

declare(strict_types=1);

/**
 * Web routes.
 *
 * Route ordering matters: static routes first, parameterized after.
 * Middleware: csrf validates tokens on all POSTs; auth protects the app.
 */

use app\Controllers\AccountController;
use app\Controllers\AuditController;
use app\Controllers\AuthController;
use app\Controllers\BackupController;
use app\Controllers\BranchController;
use app\Controllers\CompanyController;
use app\Controllers\ClosingController;
use app\Controllers\CustomerController;
use app\Controllers\DashboardController;
use app\Controllers\FeatureController;
use app\Controllers\FinancialYearController;
use app\Controllers\InventoryController;
use app\Controllers\ItemController;
use app\Controllers\ItemGroupController;
use app\Controllers\NumberingController;
use app\Controllers\PaymentModeController;
use app\Controllers\PurchaseController;
use app\Controllers\PurchaseOrderController;
use app\Controllers\PurchaseQuotationController;
use app\Controllers\PurchaseReturnController;
use app\Controllers\ReportController;
use app\Controllers\RoleController;
use app\Controllers\SalesController;
use app\Controllers\SalesOrderController;
use app\Controllers\SalesQuotationController;
use app\Controllers\SalesReturnController;
use app\Controllers\SetupController;
use app\Controllers\SettingsController;
use app\Controllers\SupplierController;
use app\Controllers\TaxController;
use app\Controllers\UomController;
use app\Controllers\UserController;
use app\Controllers\VoucherController;
use app\Controllers\WarehouseController;

$router->get('/', [DashboardController::class, 'index'], ['auth']);

/* ---------------- Authentication ---------------- */
$router->get('/login', [AuthController::class, 'showLogin'], ['guest']);
$router->post('/login', [AuthController::class, 'login'], ['guest', 'csrf']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth', 'csrf']);

/* ---------------- Password reset (guest) ---------------- */
$router->get('/forgot-password', [AuthController::class, 'showForgot'], ['guest']);
$router->post('/forgot-password', [AuthController::class, 'sendReset'], ['guest', 'csrf']);
$router->get('/forgot-password/reset', [AuthController::class, 'showResetForm'], ['guest']);
$router->post('/forgot-password/reset', [AuthController::class, 'doReset'], ['guest', 'csrf']);

/* ---------------- Users & Roles ---------------- */
$router->group('/users', ['auth', 'csrf'], function ($r) {
    $r->get('', [UserController::class, 'index']);
    $r->get('/new', [UserController::class, 'createForm']);
    $r->post('', [UserController::class, 'store']);
    $r->get('/{id}/edit', [UserController::class, 'editForm']);
    $r->post('/{id}', [UserController::class, 'update']);
    $r->post('/{id}/delete', [UserController::class, 'destroy']);
});

$router->group('/roles', ['auth'], function ($r) {
    $r->get('', [RoleController::class, 'index']);
    $r->get('/{id}/permissions', [RoleController::class, 'permissions']);
    $r->post('/{id}/permissions', [RoleController::class, 'savePermissions'], ['csrf']);
});

/* ---------------- Companies ---------------- */
$router->group('/companies', ['auth'], function ($r) {
    $r->get('', [CompanyController::class, 'index']);
    $r->get('/new', [CompanyController::class, 'createForm']);
    $r->post('', [CompanyController::class, 'store'], ['csrf']);
    $r->get('/{id}/edit', [CompanyController::class, 'editForm']);
    $r->post('/{id}', [CompanyController::class, 'update'], ['csrf']);
});

/* ---------------- Branches ---------------- */
$router->group('/branches', ['auth'], function ($r) {
    $r->get('', [BranchController::class, 'index']);
    $r->get('/new', [BranchController::class, 'createForm']);
    $r->post('', [BranchController::class, 'store'], ['csrf']);
    $r->get('/{id}/edit', [BranchController::class, 'editForm']);
    $r->post('/{id}', [BranchController::class, 'update'], ['csrf']);
    $r->post('/{id}/delete', [BranchController::class, 'destroy'], ['csrf']);
});

/* ---------------- Financial years ---------------- */
$router->group('/settings/financial-years', ['auth'], function ($r) {
    $r->get('', [FinancialYearController::class, 'index']);
    $r->get('/new', [FinancialYearController::class, 'createForm']);
    $r->post('', [FinancialYearController::class, 'store'], ['csrf']);
    $r->get('/{id}/edit', [FinancialYearController::class, 'editForm']);
    $r->post('/{id}', [FinancialYearController::class, 'update'], ['csrf']);
    $r->post('/{id}/activate', [FinancialYearController::class, 'activate'], ['csrf']);
    $r->post('/{id}/toggle-close', [FinancialYearController::class, 'toggleClose'], ['csrf']);
});

/* ---------------- Document numbering ---------------- */
$router->group('/settings/numbering', ['auth'], function ($r) {
    $r->get('', [NumberingController::class, 'index']);
    $r->get('/{id}/edit', [NumberingController::class, 'editForm']);
    $r->post('/{id}', [NumberingController::class, 'update'], ['csrf']);
    $r->post('/{id}/reset', [NumberingController::class, 'reset'], ['csrf']);
});

/* ---------------- Chart of Accounts ---------------- */
$router->group('/accounts', ['auth'], function ($r) {
    $r->get('', [AccountController::class, 'index']);
    $r->get('/defaults', [AccountController::class, 'defaults']);
    $r->post('/defaults', [AccountController::class, 'defaultsStore'], ['csrf']);
    $r->get('/new', [AccountController::class, 'createForm']);
    $r->post('', [AccountController::class, 'store'], ['csrf']);
    $r->get('/{id}/ledger', [AccountController::class, 'ledger']);
    $r->get('/{id}/edit', [AccountController::class, 'editForm']);
    $r->post('/{id}', [AccountController::class, 'update'], ['csrf']);
    $r->post('/{id}/delete', [AccountController::class, 'destroy'], ['csrf']);
});

/* ---------------- UOM & Warehouses ---------------- */
$router->group('/uoms', ['auth', 'csrf'], function ($r) {
    $r->get('', [UomController::class, 'index']);
    $r->post('', [UomController::class, 'store']);
    $r->post('/{id}', [UomController::class, 'update']);
    $r->post('/{id}/delete', [UomController::class, 'destroy']);
});

$router->group('/warehouses', ['auth'], function ($r) {
    $r->get('', [WarehouseController::class, 'index']);
    $r->get('/new', [WarehouseController::class, 'createForm']);
    $r->post('', [WarehouseController::class, 'store'], ['csrf']);
    $r->get('/{id}/edit', [WarehouseController::class, 'editForm']);
    $r->post('/{id}', [WarehouseController::class, 'update'], ['csrf']);
    $r->post('/{id}/delete', [WarehouseController::class, 'destroy'], ['csrf']);
});

/* ---------------- Reports ---------------- */
$router->group('/reports', ['auth'], function ($r) {
    $r->get('', [ReportController::class, 'index']);
    $r->get('/{report}', [ReportController::class, 'show']);
    $r->get('/{report}/print', [ReportController::class, 'print']);
    $r->get('/{report}/excel', [ReportController::class, 'excel']);
    $r->get('/{report}/csv', [ReportController::class, 'csv']);
});

/* ---------------- Vouchers ---------------- */
$router->group('/vouchers', ['auth'], function ($r) {
    $r->get('', [VoucherController::class, 'index']);
    $r->get('/new', [VoucherController::class, 'createForm']);
    $r->post('', [VoucherController::class, 'store'], ['csrf']);
    $r->get('/{id}', [VoucherController::class, 'show']);
    $r->get('/{id}/print', [VoucherController::class, 'print']);
    $r->get('/{id}/journal', [VoucherController::class, 'journal']);
    $r->post('/{id}/cancel', [VoucherController::class, 'cancel'], ['csrf']);
});

/* ---------------- Setup wizard ---------------- */
$router->group('/setup', ['auth'], function ($r) {
    $r->get('', [SetupController::class, 'index']);
    $r->post('/step/{n}', [SetupController::class, 'step'], ['csrf']);
});

/* ---------------- Customers & Suppliers ---------------- */
$router->group('/customers', ['auth'], function ($r) {
    $r->get('', [CustomerController::class, 'index']);
    $r->get('/new', [CustomerController::class, 'createForm']);
    $r->post('', [CustomerController::class, 'store'], ['csrf']);
    $r->get('/{id}/ledger', [CustomerController::class, 'ledger']);
    $r->get('/{id}/edit', [CustomerController::class, 'editForm']);
    $r->post('/{id}', [CustomerController::class, 'update'], ['csrf']);
    $r->post('/{id}/delete', [CustomerController::class, 'destroy'], ['csrf']);
});

$router->group('/suppliers', ['auth'], function ($r) {
    $r->get('', [SupplierController::class, 'index']);
    $r->get('/new', [SupplierController::class, 'createForm']);
    $r->post('', [SupplierController::class, 'store'], ['csrf']);
    $r->get('/{id}/ledger', [SupplierController::class, 'ledger']);
    $r->get('/{id}/edit', [SupplierController::class, 'editForm']);
    $r->post('/{id}', [SupplierController::class, 'update'], ['csrf']);
    $r->post('/{id}/delete', [SupplierController::class, 'destroy'], ['csrf']);
});

/* ---------------- Items & item groups ---------------- */
$router->group('/items', ['auth'], function ($r) {
    $r->get('', [ItemController::class, 'index']);
    $r->get('/search', [ItemController::class, 'search']);
    $r->get('/new', [ItemController::class, 'createForm']);
    $r->post('', [ItemController::class, 'store'], ['csrf']);
    $r->get('/{id}/ledger', [ItemController::class, 'ledger']);
    $r->get('/{id}/edit', [ItemController::class, 'editForm']);
    $r->post('/{id}', [ItemController::class, 'update'], ['csrf']);
    $r->post('/{id}/delete', [ItemController::class, 'destroy'], ['csrf']);
});

$router->group('/item-groups', ['auth'], function ($r) {
    $r->get('', [ItemGroupController::class, 'index']);
    $r->post('', [ItemGroupController::class, 'store'], ['csrf']);
    $r->get('/{id}/edit', [ItemGroupController::class, 'editForm']);
    $r->post('/{id}', [ItemGroupController::class, 'update'], ['csrf']);
    $r->post('/{id}/delete', [ItemGroupController::class, 'destroy'], ['csrf']);
});

/* ---------------- Inventory operations ---------------- */
$router->group('/inventory', ['auth'], function ($r) {
    $r->get('/stock-balance', [InventoryController::class, 'stockBalance']);
    $r->get('/item-cost', [InventoryController::class, 'itemCost']);
    $r->post('/costing/update', [InventoryController::class, 'costingUpdate'], ['csrf']);
    $r->get('/low-stock', [InventoryController::class, 'lowStock']);
    $r->get('/transfers', [InventoryController::class, 'transfers']);
    $r->get('/transfers/new', [InventoryController::class, 'transferForm']);
    $r->post('/transfers', [InventoryController::class, 'transferStore'], ['csrf']);
    $r->post('/transfers/{id}/reverse', [InventoryController::class, 'transferReverse'], ['csrf']);
    $r->get('/adjustments', [InventoryController::class, 'adjustments']);
    $r->get('/adjustments/new', [InventoryController::class, 'adjustmentForm']);
    $r->post('/adjustments', [InventoryController::class, 'adjustmentStore'], ['csrf']);
    $r->get('/opening-stock', [InventoryController::class, 'openingStockForm']);
    $r->post('/opening-stock', [InventoryController::class, 'openingStockStore'], ['csrf']);
});

/* ---------------- Purchase ---------------- */
$router->group('/purchase/invoices', ['auth'], function ($r) {
    $r->get('', [PurchaseController::class, 'index']);
    $r->get('/new', [PurchaseController::class, 'createForm']);
    $r->post('', [PurchaseController::class, 'store'], ['csrf']);
    $r->get('/{id}', [PurchaseController::class, 'show']);
    $r->get('/{id}/print', [PurchaseController::class, 'print']);
    $r->get('/{id}/journal', [PurchaseController::class, 'journal']);
    $r->post('/{id}/cancel', [PurchaseController::class, 'cancel'], ['csrf']);
});

$router->group('/purchase/orders', ['auth'], function ($r) {
    $r->get('', [PurchaseOrderController::class, 'index']);
    $r->get('/new', [PurchaseOrderController::class, 'createForm']);
    $r->post('', [PurchaseOrderController::class, 'store'], ['csrf']);
    $r->post('/{id}/cancel', [PurchaseOrderController::class, 'cancel'], ['csrf']);
});

$router->group('/purchase/quotations', ['auth'], function ($r) {
    $r->get('', [PurchaseQuotationController::class, 'index']);
    $r->get('/new', [PurchaseQuotationController::class, 'createForm']);
    $r->post('', [PurchaseQuotationController::class, 'store'], ['csrf']);
    $r->post('/{id}/cancel', [PurchaseQuotationController::class, 'cancel'], ['csrf']);
});

$router->group('/purchase/returns', ['auth'], function ($r) {
    $r->get('', [PurchaseReturnController::class, 'index']);
    $r->get('/new', [PurchaseReturnController::class, 'createForm']);
    $r->post('', [PurchaseReturnController::class, 'store'], ['csrf']);
    $r->get('/{id}', [PurchaseReturnController::class, 'show']);
    $r->get('/{id}/print', [PurchaseReturnController::class, 'print']);
    $r->post('/{id}/cancel', [PurchaseReturnController::class, 'cancel'], ['csrf']);
});

/* ---------------- Sales ---------------- */
$router->group('/sales/invoices', ['auth'], function ($r) {
    $r->get('', [SalesController::class, 'index']);
    $r->get('/new', [SalesController::class, 'createForm']);
    $r->post('', [SalesController::class, 'store'], ['csrf']);
    $r->get('/{id}', [SalesController::class, 'show']);
    $r->get('/{id}/print', [SalesController::class, 'print']);
    $r->get('/{id}/journal', [SalesController::class, 'journal']);
    $r->post('/{id}/cancel', [SalesController::class, 'cancel'], ['csrf']);
    $r->post('/{id}/attachments', [SalesController::class, 'attach'], ['csrf']);
    $r->get('/{id}/attachments/{attachmentId}/download', [SalesController::class, 'download']);
    $r->post('/{id}/attachments/{attachmentId}/delete', [SalesController::class, 'deleteAttachment'], ['csrf']);
});

$router->group('/sales/orders', ['auth'], function ($r) {
    $r->get('', [SalesOrderController::class, 'index']);
    $r->get('/new', [SalesOrderController::class, 'createForm']);
    $r->post('', [SalesOrderController::class, 'store'], ['csrf']);
    $r->post('/{id}/cancel', [SalesOrderController::class, 'cancel'], ['csrf']);
});

$router->group('/sales/quotations', ['auth'], function ($r) {
    $r->get('', [SalesQuotationController::class, 'index']);
    $r->get('/new', [SalesQuotationController::class, 'createForm']);
    $r->post('', [SalesQuotationController::class, 'store'], ['csrf']);
    $r->post('/{id}/cancel', [SalesQuotationController::class, 'cancel'], ['csrf']);
});

$router->group('/sales/returns', ['auth'], function ($r) {
    $r->get('', [SalesReturnController::class, 'index']);
    $r->get('/new', [SalesReturnController::class, 'createForm']);
    $r->post('', [SalesReturnController::class, 'store'], ['csrf']);
    $r->get('/{id}', [SalesReturnController::class, 'show']);
    $r->get('/{id}/print', [SalesReturnController::class, 'print']);
    $r->get('/{id}/journal', [SalesReturnController::class, 'journal']);
    $r->post('/{id}/cancel', [SalesReturnController::class, 'cancel'], ['csrf']);
});

/* ---------------- Settings & Features & switches ---------------- */
$router->group('/settings', ['auth'], function ($r) {
    $r->get('', [SettingsController::class, 'index']);
    $r->post('', [SettingsController::class, 'update'], ['csrf']);
    $r->get('/features', [FeatureController::class, 'index']);
    $r->post('/features', [FeatureController::class, 'update'], ['csrf']);
    $r->get('/payment-modes', [PaymentModeController::class, 'index']);
    $r->post('/payment-modes', [PaymentModeController::class, 'store'], ['csrf']);
    $r->post('/payment-modes/{id}', [PaymentModeController::class, 'update'], ['csrf']);
    $r->post('/payment-modes/{id}/delete', [PaymentModeController::class, 'destroy'], ['csrf']);
    $r->get('/taxes', [TaxController::class, 'index']);
    $r->post('/taxes', [TaxController::class, 'store'], ['csrf']);
    $r->post('/taxes/{id}', [TaxController::class, 'update'], ['csrf']);
    $r->post('/taxes/{id}/delete', [TaxController::class, 'destroy'], ['csrf']);
    $r->get('/closing', [ClosingController::class, 'index']);
    $r->post('/closing', [ClosingController::class, 'update'], ['csrf']);
    $r->get('/backups', [BackupController::class, 'index']);
    $r->post('/backups', [BackupController::class, 'create'], ['csrf']);
    $r->get('/backups/{name}/download', [BackupController::class, 'download']);
    $r->post('/backups/{name}/delete', [BackupController::class, 'delete'], ['csrf']);
    $r->post('/company/switch', [CompanyController::class, 'switch'], ['csrf']);
    $r->post('/branch/switch', [BranchController::class, 'switchBranch'], ['csrf']);
});

/* ---------------- Audit ---------------- */
$router->get('/audit', [AuditController::class, 'index'], ['auth']);

