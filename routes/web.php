<?php

use App\Http\Controllers\LegacyController;
use App\Http\Controllers\Accounting\CoaController;
use App\Http\Controllers\Accounting\AssetController;
use App\Http\Controllers\Accounting\JournalController;
use App\Http\Controllers\Accounting\LedgerController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\Ppic\MpsController;
use App\Http\Controllers\Ppic\SpkController;
use App\Http\Controllers\Ppic\PurchaseRequestController;
use App\Http\Controllers\Ppic\InventoryController;
use App\Http\Controllers\Procurement\PurchaseOrderController;
use App\Http\Controllers\Procurement\SupplierController;
use App\Http\Controllers\Qc\IncomingController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\MachineController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\ResetController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SetupWizardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WaLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Engineering\BomController;
use App\Http\Controllers\Engineering\ItemController as EngineeringItemController;
use App\Http\Controllers\Engineering\PartlistController;
use App\Http\Controllers\Executive\AuditLogController;
use App\Http\Controllers\Executive\KpiDashboardController;
use App\Http\Controllers\Finance\TaxController;
use App\Http\Middleware\MmsAuthenticate;
use App\Http\Middleware\RequirePermission;
use App\Http\Controllers\Sales\CustomerController;
use App\Http\Controllers\Sales\QuotationController;
use App\Http\Controllers\Sales\SalesOrderController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\Warehouse\GoodsReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : app(AuthController::class)->showLogin(app(\App\Services\MmsContext::class)));

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::match(['get', 'post'], '/logout', [AuthController::class, 'logout'])->name('logout');
Route::match(['get', 'post'], '/logout.php', [AuthController::class, 'logout']);

Route::middleware(MmsAuthenticate::class)->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/user-settings', [UserSettingsController::class, 'edit'])->name('user_settings.edit');
    Route::post('/user-settings/profile', [UserSettingsController::class, 'updateProfile'])->name('user_settings.profile');
    Route::post('/user-settings/password', [UserSettingsController::class, 'updatePassword'])->name('user_settings.password');

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::middleware(RequirePermission::class . ':user_view')->group(function (): void {
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
        });
        Route::middleware(RequirePermission::class . ':user_edit')->group(function (): void {
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        });
        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->middleware(RequirePermission::class . ':user_delete')
            ->name('users.destroy');

        Route::middleware(RequirePermission::class . ':role_manage')->group(function (): void {
            Route::resource('roles', RoleController::class)->except(['show']);
            Route::get('/roles-permissions/{role?}', [RoleController::class, 'permissions'])->name('roles.permissions');
            Route::put('/roles-permissions/{role}', [RoleController::class, 'updatePermissions'])->name('roles.permissions.update');
        });

        Route::middleware(RequirePermission::class . ':admin_company_manage')->group(function (): void {
            Route::get('/company', [CompanyController::class, 'edit'])->name('company.edit');
            Route::put('/company', [CompanyController::class, 'update'])->name('company.update');
        });

        Route::middleware(RequirePermission::class . ':admin_reset_db')->group(function (): void {
            Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
            Route::post('/backup/download', [BackupController::class, 'download'])->name('backup.download');
            Route::post('/backup/restore', [BackupController::class, 'restore'])->name('backup.restore');
            Route::get('/reset', [ResetController::class, 'index'])->name('reset.index');
            Route::post('/reset', [ResetController::class, 'reset'])->name('reset.store');
            Route::get('/setup-wizard', [SetupWizardController::class, 'index'])->name('setup.index');
            Route::post('/setup-wizard', [SetupWizardController::class, 'save'])->name('setup.save');
            Route::get('/wa-logs', [WaLogController::class, 'index'])->name('wa_logs.index');
        });

        Route::middleware(RequirePermission::class . ':admin_menu_manage')->group(function (): void {
            Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
            Route::post('/menu/mode', [MenuController::class, 'saveMode'])->name('menu.mode');
            Route::post('/menu/users/{user}', [MenuController::class, 'saveUserMenu'])->name('menu.users.save');
        });

        Route::middleware(RequirePermission::class . ':eng_machine_view')->group(function (): void {
            Route::get('/machines', [MachineController::class, 'index'])->name('machines.index');
        });
        Route::middleware(RequirePermission::class . ':eng_machine_manage')->group(function (): void {
            Route::get('/machines/create', [MachineController::class, 'create'])->name('machines.create');
            Route::post('/machines', [MachineController::class, 'store'])->name('machines.store');
            Route::get('/machines/{machine}/edit', [MachineController::class, 'edit'])->name('machines.edit');
            Route::put('/machines/{machine}', [MachineController::class, 'update'])->name('machines.update');
            Route::delete('/machines/{machine}', [MachineController::class, 'destroy'])->name('machines.destroy');
        });
    });

    Route::prefix('sales')->name('sales.')->group(function (): void {
        Route::get('/customers', [CustomerController::class, 'index'])
            ->middleware(RequirePermission::class . ':sales_customer_view')
            ->name('customers.index');
        Route::post('/customers/save-ajax', [CustomerController::class, 'saveAjax'])
            ->middleware(RequirePermission::class . ':sales_customer_manage')
            ->name('customers.save_ajax');
        Route::post('/customers/import-ajax', [CustomerController::class, 'importAjax'])
            ->middleware(RequirePermission::class . ':sales_customer_manage')
            ->name('customers.import_ajax');
        Route::middleware(RequirePermission::class . ':sales_customer_manage')->group(function (): void {
            Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
            Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
            Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
            Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
            Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        });

        Route::get('/quotations', [QuotationController::class, 'index'])
            ->middleware(RequirePermission::class . ':sales_quotation_view')
            ->name('quotations.index');
        Route::get('/quotations/{quotation}/print', [QuotationController::class, 'print'])
            ->middleware(RequirePermission::class . ':sales_quotation_view')
            ->name('quotations.print');
        Route::middleware(RequirePermission::class . ':sales_quotation_manage')->group(function (): void {
            Route::get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
            Route::post('/quotations', [QuotationController::class, 'store'])->name('quotations.store');
            Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
            Route::put('/quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
            Route::delete('/quotations/{quotation}', [QuotationController::class, 'destroy'])->name('quotations.destroy');
        });
        Route::post('/quotations/{quotation}/{action}', [QuotationController::class, 'workflow'])
            ->whereIn('action', ['submit', 'approve', 'reject', 'mark_sent', 'won', 'lost', 'revise'])
            ->name('quotations.workflow');

        Route::get('/orders', [SalesOrderController::class, 'index'])
            ->middleware(RequirePermission::class . ':sales_view')
            ->name('orders.index');
        Route::get('/orders/{order}/print', [SalesOrderController::class, 'print'])
            ->middleware(RequirePermission::class . ':sales_view')
            ->name('orders.print');
        Route::middleware(RequirePermission::class . ':sales_so_manage')->group(function (): void {
            Route::get('/orders/create', [SalesOrderController::class, 'create'])->name('orders.create');
            Route::post('/orders', [SalesOrderController::class, 'store'])->name('orders.store');
            Route::get('/orders/{order}/edit', [SalesOrderController::class, 'edit'])->name('orders.edit');
            Route::put('/orders/{order}', [SalesOrderController::class, 'update'])->name('orders.update');
            Route::delete('/orders/{order}', [SalesOrderController::class, 'destroy'])->name('orders.destroy');
        });
        Route::post('/orders/{order}/{action}', [SalesOrderController::class, 'workflow'])
            ->whereIn('action', ['submit', 'approve', 'reject', 'cancel', 'mark_sent'])
            ->name('orders.workflow');
    });

    Route::prefix('engineering')->name('engineering.')->group(function (): void {
        Route::middleware(RequirePermission::class . ':eng_items')->group(function (): void {
            Route::get('/items', [EngineeringItemController::class, 'index'])->name('items.index');
            Route::get('/items/generate-code', [EngineeringItemController::class, 'generateCode'])->name('items.generate_code');
            Route::post('/items/import-ajax', [EngineeringItemController::class, 'importAjax'])->name('items.import_ajax');
            Route::get('/items/create', [EngineeringItemController::class, 'create'])->name('items.create');
            Route::post('/items', [EngineeringItemController::class, 'store'])->name('items.store');
            Route::get('/items/{item}/edit', [EngineeringItemController::class, 'edit'])->name('items.edit');
            Route::put('/items/{item}', [EngineeringItemController::class, 'update'])->name('items.update');
            Route::delete('/items/{item}', [EngineeringItemController::class, 'destroy'])->name('items.destroy');
        });

        Route::middleware(RequirePermission::class . ':eng_bom')->group(function (): void {
            Route::get('/boms', [BomController::class, 'index'])->name('boms.index');
            Route::get('/boms/create', [BomController::class, 'create'])->name('boms.create');
            Route::post('/boms', [BomController::class, 'store'])->name('boms.store');
            Route::get('/boms/{bom}/edit', [BomController::class, 'edit'])->name('boms.edit');
            Route::put('/boms/{bom}', [BomController::class, 'update'])->name('boms.update');
            Route::delete('/boms/{bom}', [BomController::class, 'destroy'])->name('boms.destroy');
        });

        Route::middleware(RequirePermission::class . ':eng_view')->group(function (): void {
            Route::get('/partlists', [PartlistController::class, 'index'])->name('partlists.index');
            Route::get('/partlists/create', [PartlistController::class, 'create'])->name('partlists.create');
            Route::post('/partlists', [PartlistController::class, 'store'])->name('partlists.store');
            Route::post('/partlists/{spk}/approve', [PartlistController::class, 'approve'])->name('partlists.approve');
            Route::get('/partlists/{spk}/print', [PartlistController::class, 'print'])->name('partlists.print');
        });
    });

    Route::prefix('ppic')->name('ppic.')->group(function (): void {
        Route::get('/spk', [SpkController::class, 'index'])
            ->middleware(RequirePermission::class . ':ppic_spk_view')
            ->name('spk.index');
        Route::get('/spk/{spk}/print', [SpkController::class, 'print'])
            ->middleware(RequirePermission::class . ':ppic_spk_view')
            ->name('spk.print');
        Route::middleware(RequirePermission::class . ':ppic_spk_manage')->group(function (): void {
            Route::get('/spk/create', [SpkController::class, 'create'])->name('spk.create');
            Route::post('/spk', [SpkController::class, 'store'])->name('spk.store');
            Route::get('/spk/{spk}/edit', [SpkController::class, 'edit'])->name('spk.edit');
            Route::put('/spk/{spk}', [SpkController::class, 'update'])->name('spk.update');
            Route::post('/spk/{spk}/{action}', [SpkController::class, 'workflow'])
                ->whereIn('action', ['submit', 'approve_mgr', 'receive_spv'])
                ->name('spk.workflow');
        });
        Route::delete('/spk/{spk}', [SpkController::class, 'destroy'])
            ->middleware(RequirePermission::class . ':ppic_spk_delete')
            ->name('spk.destroy');

        Route::get('/purchase-requests', [PurchaseRequestController::class, 'index'])
            ->middleware(RequirePermission::class . ':ppic_pr_view')
            ->name('purchase_requests.index');
        Route::get('/purchase-requests/{purchaseRequest}/print', [PurchaseRequestController::class, 'print'])
            ->middleware(RequirePermission::class . ':ppic_pr_view')
            ->name('purchase_requests.print');
        Route::middleware(RequirePermission::class . ':ppic_pr_manage')->group(function (): void {
            Route::get('/purchase-requests/create', [PurchaseRequestController::class, 'create'])->name('purchase_requests.create');
            Route::post('/purchase-requests', [PurchaseRequestController::class, 'store'])->name('purchase_requests.store');
            Route::get('/purchase-requests/{purchaseRequest}/edit', [PurchaseRequestController::class, 'edit'])->name('purchase_requests.edit');
            Route::put('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'update'])->name('purchase_requests.update');
            Route::post('/purchase-requests/{purchaseRequest}/{action}', [PurchaseRequestController::class, 'workflow'])
                ->whereIn('action', ['submit', 'approve'])
                ->name('purchase_requests.workflow');
        });
        Route::delete('/purchase-requests/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])
            ->middleware(RequirePermission::class . ':ppic_pr_delete')
            ->name('purchase_requests.destroy');

        Route::middleware(RequirePermission::class . ':ppic_view')->group(function (): void {
            Route::get('/mps', [MpsController::class, 'index'])->name('mps.index');
            Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
            Route::get('/inventory/{item}', [InventoryController::class, 'show'])->name('inventory.show');
        });
    });

    Route::prefix('procurement')->name('procurement.')->group(function (): void {
        Route::get('/orders', [PurchaseOrderController::class, 'index'])
            ->middleware(RequirePermission::class . ':purch_po_view')
            ->name('orders.index');
        Route::get('/orders/{order}/print', [PurchaseOrderController::class, 'print'])
            ->middleware(RequirePermission::class . ':purch_po_view')
            ->name('orders.print');
        Route::middleware(RequirePermission::class . ':purch_po_manage')->group(function (): void {
            Route::get('/orders/create', [PurchaseOrderController::class, 'create'])->name('orders.create');
            Route::post('/orders', [PurchaseOrderController::class, 'store'])->name('orders.store');
            Route::get('/orders/{order}/edit', [PurchaseOrderController::class, 'edit'])->name('orders.edit');
            Route::put('/orders/{order}', [PurchaseOrderController::class, 'update'])->name('orders.update');
        });
        Route::delete('/orders/{order}', [PurchaseOrderController::class, 'destroy'])
            ->middleware(RequirePermission::class . ':purch_po_delete')
            ->name('orders.destroy');
        Route::post('/orders/{order}/{action}', [PurchaseOrderController::class, 'workflow'])
            ->whereIn('action', ['submit', 'approve', 'approve_finance', 'send_vendor', 'cancel'])
            ->name('orders.workflow');

        Route::get('/suppliers', [SupplierController::class, 'index'])
            ->middleware(RequirePermission::class . ':purch_vendor_view')
            ->name('suppliers.index');
        Route::middleware(RequirePermission::class . ':purch_vendor_manage')->group(function (): void {
            Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
            Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
            Route::post('/suppliers/import-ajax', [SupplierController::class, 'importAjax'])->name('suppliers.import_ajax');
            Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
            Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
            Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
        });
    });

    Route::prefix('warehouse')->name('warehouse.')->group(function (): void {
        Route::get('/receipts', [GoodsReceiptController::class, 'index'])
            ->middleware(RequirePermission::class . ':whse_receive_view')
            ->name('receipts.index');
        Route::get('/receipts/po-items/{order}', [GoodsReceiptController::class, 'poItems'])
            ->middleware(RequirePermission::class . ':whse_receive_manage')
            ->name('receipts.po_items');
        Route::middleware(RequirePermission::class . ':whse_receive_manage')->group(function (): void {
            Route::get('/receipts/create', [GoodsReceiptController::class, 'create'])->name('receipts.create');
            Route::post('/receipts', [GoodsReceiptController::class, 'store'])->name('receipts.store');
            Route::get('/receipts/{receipt}/edit', [GoodsReceiptController::class, 'edit'])->name('receipts.edit');
            Route::put('/receipts/{receipt}', [GoodsReceiptController::class, 'update'])->name('receipts.update');
            Route::delete('/receipts/{receipt}', [GoodsReceiptController::class, 'destroy'])->name('receipts.destroy');
        });
    });

    Route::prefix('qc')->name('qc.')->group(function (): void {
        Route::get('/incoming', [IncomingController::class, 'index'])
            ->middleware(RequirePermission::class . ':qc_incoming_view')
            ->name('incoming.index');
        Route::get('/incoming/inspect', [IncomingController::class, 'inspect'])
            ->middleware(RequirePermission::class . ':qc_incoming_manage')
            ->name('incoming.inspect');
        Route::post('/incoming/inspect/{receipt}', [IncomingController::class, 'storeInspection'])
            ->middleware(RequirePermission::class . ':qc_incoming_manage')
            ->name('incoming.store_inspection');
        Route::post('/incoming/{qc}/approve', [IncomingController::class, 'approve'])
            ->middleware(RequirePermission::class . ':qc_incoming_approve')
            ->name('incoming.approve');
        Route::post('/incoming/{qc}/handover', [IncomingController::class, 'handover'])
            ->middleware(RequirePermission::class . ':whse_view')
            ->name('incoming.handover');
        Route::get('/incoming/{qc}/print', [IncomingController::class, 'print'])
            ->middleware(RequirePermission::class . ':qc_incoming_view')
            ->name('incoming.print');
    });

    Route::prefix('executive')->name('executive.')->group(function (): void {
        Route::get('/kpi', [KpiDashboardController::class, 'index'])
            ->middleware(RequirePermission::class . ':owner_kpi')
            ->name('kpi.index');
        Route::get('/logs', [AuditLogController::class, 'index'])
            ->middleware(RequirePermission::class . ':owner_logs')
            ->name('logs.index');
    });

    Route::prefix('finance')->name('finance.')->group(function (): void {
        Route::get('/tax', [TaxController::class, 'index'])
            ->middleware(RequirePermission::class . ':fin_ar_view')
            ->name('tax.index');
        Route::post('/tax/payment', [TaxController::class, 'storePayment'])
            ->middleware(RequirePermission::class . ':fin_ar_manage')
            ->name('tax.payment');
        Route::get('/tax/print', [TaxController::class, 'print'])
            ->middleware(RequirePermission::class . ':fin_ar_view')
            ->name('tax.print');
    });

    Route::prefix('accounting')->name('accounting.')->group(function (): void {
        Route::middleware(RequirePermission::class . ':acc_view')->group(function (): void {
            Route::get('/coa', [CoaController::class, 'index'])->name('coa.index');
            Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');
            Route::get('/journal/print', [JournalController::class, 'print'])->name('journal.print');
            Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger.index');
            Route::get('/ledger/print', [LedgerController::class, 'print'])->name('ledger.print');
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/print', [ReportController::class, 'print'])->name('reports.print');
            Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
            Route::get('/assets/print', [AssetController::class, 'print'])->name('assets.print');
        });
        Route::middleware(RequirePermission::class . ':acc_coa_manage')->group(function (): void {
            Route::get('/coa/create', [CoaController::class, 'create'])->name('coa.create');
            Route::post('/coa', [CoaController::class, 'store'])->name('coa.store');
            Route::get('/coa/{coa}/edit', [CoaController::class, 'edit'])->name('coa.edit');
            Route::put('/coa/{coa}', [CoaController::class, 'update'])->name('coa.update');
            Route::delete('/coa/{coa}', [CoaController::class, 'destroy'])->name('coa.destroy');
            Route::post('/coa/reconcile', [CoaController::class, 'reconcile'])->name('coa.reconcile');
        });
        Route::middleware(RequirePermission::class . ':acc_journal_manage')->group(function (): void {
            Route::get('/journal/create', [JournalController::class, 'create'])->name('journal.create');
            Route::post('/journal', [JournalController::class, 'store'])->name('journal.store');
        });
        Route::middleware(RequirePermission::class . ':acc_asset_manage')->group(function (): void {
            Route::get('/assets/create', [AssetController::class, 'create'])->name('assets.create');
            Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
            Route::get('/assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
            Route::put('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
            Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
            Route::post('/assets/depreciate', [AssetController::class, 'depreciate'])->name('assets.depreciate');
        });
    });
});

Route::any('/{path}', [LegacyController::class, 'handle'])
    ->where('path', '.*');
