<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\AccountCategoryController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\VoucherController;
use App\Http\Controllers\Accounting\PettyCashItemController;
use App\Http\Controllers\Accounting\ExpenseClaimController;
use App\Http\Controllers\Accounting\SettlementController;
use App\Http\Controllers\Accounting\ClosingController;
use App\Http\Controllers\Accounting\ReportController;
use App\Http\Controllers\Accounting\FixedAssetController;
use App\Http\Controllers\Accounting\BankReconciliationController;
use App\Http\Controllers\Accounting\WarehouseController;
use App\Http\Controllers\Accounting\InventoryItemController;
use App\Http\Controllers\Accounting\StockController;
use App\Http\Controllers\Accounting\PurchaseInvoiceController;
use App\Http\Controllers\Accounting\SalesInvoiceController;
use App\Http\Controllers\Accounting\EmployeeController;
use App\Http\Controllers\Accounting\SalaryComponentController;
use App\Http\Controllers\Accounting\PayrollController;
use App\Http\Controllers\Accounting\PosController;
use App\Http\Controllers\Accounting\AttachmentController;
use App\Http\Controllers\Accounting\ReminderController;
use App\Http\Controllers\Accounting\DocumentPdfController;
use App\Http\Controllers\Accounting\AuditLogController;
use App\Http\Controllers\Accounting\ChequeController;
use App\Http\Controllers\Accounting\ReturnController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\CompanyController;
use App\Http\Controllers\Settings\BranchController;
use App\Http\Controllers\Settings\CompanySettingController;
use App\Http\Controllers\Settings\CostCenterController;
use App\Http\Controllers\Settings\CurrencyController;
use App\Http\Controllers\Settings\FiscalYearController;
use App\Http\Controllers\Settings\TaxRateController;
use App\Http\Controllers\Settings\VoucherTypeController;
use Illuminate\Support\Facades\Route;

/*
| نقاط نهاية الـ API
| كل المسارات المحمية تمرّ بـ auth:sanctum ثم company (عزل المستأجر).
*/

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'company'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ===== المرحلة 1: الإعدادات الأساسية =====
    Route::prefix('settings')->group(function () {
        // كيانات CRUD قياسية محميّة بصلاحيات settings.*
        $entities = [
            'branches' => BranchController::class,
            'fiscal-years' => FiscalYearController::class,
            'currencies' => CurrencyController::class,
            'cost-centers' => CostCenterController::class,
            'voucher-types' => VoucherTypeController::class,
            'tax-rates' => TaxRateController::class,
        ];

        foreach ($entities as $uri => $controller) {
            Route::get($uri, [$controller, 'index'])->middleware('permission:settings.view');
            Route::get("$uri/{id}", [$controller, 'show'])->middleware('permission:settings.view');
            Route::post($uri, [$controller, 'store'])->middleware('permission:settings.create');
            Route::put("$uri/{id}", [$controller, 'update'])->middleware('permission:settings.edit');
            Route::delete("$uri/{id}", [$controller, 'destroy'])->middleware('permission:settings.delete');
        }

        // إجراءات خاصة بالسنة المالية (إقفال/فتح)
        Route::post('fiscal-years/{id}/lock', [FiscalYearController::class, 'lock'])
            ->middleware('permission:settings.lock');
        Route::post('fiscal-years/{id}/unlock', [FiscalYearController::class, 'unlock'])
            ->middleware('permission:settings.lock');
    });

    // ===== المرحلة 2: دليل الحسابات =====
    // فئات الحسابات (تعدد الفئات)
    Route::get('account-categories', [AccountCategoryController::class, 'index'])->middleware('permission:accounts.view');
    Route::get('account-categories/grouped', [AccountCategoryController::class, 'grouped'])->middleware('permission:accounts.view');
    Route::get('account-categories/{id}', [AccountCategoryController::class, 'show'])->middleware('permission:accounts.view');
    Route::post('account-categories', [AccountCategoryController::class, 'store'])->middleware('permission:accounts.create');
    Route::put('account-categories/{id}', [AccountCategoryController::class, 'update'])->middleware('permission:accounts.edit');
    Route::delete('account-categories/{id}', [AccountCategoryController::class, 'destroy'])->middleware('permission:accounts.delete');

    // دليل الحسابات
    Route::get('accounts/parties', [AccountController::class, 'parties'])->middleware('permission:accounts.view');
    Route::get('accounts/{id}/statement', [AccountController::class, 'statement'])->middleware('permission:accounts.view');
    Route::get('accounts/search', [AccountController::class, 'search'])->middleware('permission:accounts.view');
    Route::get('accounts/tree', [AccountController::class, 'tree'])->middleware('permission:accounts.view');
    Route::post('accounts/import', [AccountController::class, 'import'])->middleware('permission:accounts.create');
    Route::get('accounts/{id}/balance', [AccountController::class, 'balance'])->middleware('permission:accounts.view');
    Route::get('accounts', [AccountController::class, 'index'])->middleware('permission:accounts.view');
    Route::get('accounts/{id}', [AccountController::class, 'show'])->middleware('permission:accounts.view');
    Route::post('accounts', [AccountController::class, 'store'])->middleware('permission:accounts.create');
    Route::put('accounts/{id}', [AccountController::class, 'update'])->middleware('permission:accounts.edit');
    Route::delete('accounts/{id}', [AccountController::class, 'destroy'])->middleware('permission:accounts.delete');

    // ===== المرحلة 3: القيود اليومية =====
    Route::get('journal-entries', [JournalEntryController::class, 'index'])->middleware('permission:journals.view');
    Route::get('journal-entries/{id}', [JournalEntryController::class, 'show'])->middleware('permission:journals.view');
    Route::post('journal-entries', [JournalEntryController::class, 'store'])->middleware('permission:journals.create');
    Route::put('journal-entries/{id}', [JournalEntryController::class, 'update'])->middleware('permission:journals.edit');
    Route::delete('journal-entries/{id}', [JournalEntryController::class, 'destroy'])->middleware('permission:journals.delete');
    Route::post('journal-entries/{id}/approve', [JournalEntryController::class, 'approve'])->middleware('permission:journals.approve');
    Route::post('journal-entries/{id}/post', [JournalEntryController::class, 'post'])->middleware('permission:journals.post');
    Route::post('journal-entries/{id}/reverse', [JournalEntryController::class, 'reverse'])->middleware('permission:journals.unpost');
    Route::post('journal-entries/{id}/duplicate', [JournalEntryController::class, 'duplicate'])->middleware('permission:journals.create');

    // ===== المرحلة 3: السندات (قبض/صرف/تحويل) =====
    Route::get('vouchers', [VoucherController::class, 'index'])->middleware('permission:vouchers.view');
    Route::get('vouchers/{id}', [VoucherController::class, 'show'])->middleware('permission:vouchers.view');
    Route::post('vouchers', [VoucherController::class, 'store'])->middleware('permission:vouchers.create');
    Route::post('vouchers/{id}/reverse', [VoucherController::class, 'reverse'])->middleware('permission:vouchers.unpost');

    // ===== المرحلة 4: العهد =====
    Route::get('petty-cash-items', [PettyCashItemController::class, 'index'])->middleware('permission:petty_cash.view');
    Route::get('petty-cash-items/{id}', [PettyCashItemController::class, 'show'])->middleware('permission:petty_cash.view');
    Route::post('petty-cash-items', [PettyCashItemController::class, 'store'])->middleware('permission:petty_cash.create');
    Route::put('petty-cash-items/{id}', [PettyCashItemController::class, 'update'])->middleware('permission:petty_cash.edit');
    Route::delete('petty-cash-items/{id}', [PettyCashItemController::class, 'destroy'])->middleware('permission:petty_cash.delete');

    Route::get('expense-claims', [ExpenseClaimController::class, 'index'])->middleware('permission:petty_cash.view');
    Route::get('expense-claims/{id}', [ExpenseClaimController::class, 'show'])->middleware('permission:petty_cash.view');
    Route::post('expense-claims', [ExpenseClaimController::class, 'store'])->middleware('permission:petty_cash.create');
    Route::post('expense-claims/{id}/approve', [ExpenseClaimController::class, 'approve'])->middleware('permission:petty_cash.approve');
    Route::post('expense-claims/{id}/convert', [ExpenseClaimController::class, 'convert'])->middleware('permission:petty_cash.post');

    // ===== المرحلة 4: الأمانات/السداد =====
    Route::get('settlements/summary', [SettlementController::class, 'summary'])->middleware('permission:settlements.view');
    Route::get('settlements/customer/{accountId}', [SettlementController::class, 'customer'])->middleware('permission:settlements.view');
    Route::post('settlements/customer/{accountId}/allocate', [SettlementController::class, 'allocate'])->middleware('permission:settlements.create');

    // ===== المرحلة 4: الإقفال السنوي =====
    Route::post('closing/close-year', [ClosingController::class, 'close'])->middleware('permission:journals.lock');

    // إعدادات الشركة (خرائط الحسابات)
    Route::get('company-settings', [CompanySettingController::class, 'index'])->middleware('permission:settings.view');
    Route::put('company-settings', [CompanySettingController::class, 'update'])->middleware('permission:settings.edit');

    // ===== المرحلة 5: التقارير المالية و Dashboard =====
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('reports/trial-balance', [ReportController::class, 'trialBalance']);
        Route::get('reports/income-statement', [ReportController::class, 'incomeStatement']);
        Route::get('reports/balance-sheet', [ReportController::class, 'balanceSheet']);
        Route::get('reports/general-ledger/{accountId}', [ReportController::class, 'generalLedger']);
        Route::get('reports/cash-flow', [ReportController::class, 'cashFlow']);
        Route::get('reports/claims', [ReportController::class, 'claims']);
        Route::get('reports/aging', [ReportController::class, 'aging']);
        Route::get('reports/vat', [ReportController::class, 'vat']);
        Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
    });

    // ===== المرحلة 6: الأصول الثابتة والإهلاك =====
    Route::get('fixed-assets', [FixedAssetController::class, 'index'])->middleware('permission:fixed_assets.view');
    Route::get('fixed-assets/{id}', [FixedAssetController::class, 'show'])->middleware('permission:fixed_assets.view');
    Route::get('fixed-assets/{id}/schedule', [FixedAssetController::class, 'schedule'])->middleware('permission:fixed_assets.view');
    Route::post('fixed-assets', [FixedAssetController::class, 'store'])->middleware('permission:fixed_assets.create');
    Route::put('fixed-assets/{id}', [FixedAssetController::class, 'update'])->middleware('permission:fixed_assets.edit');
    Route::delete('fixed-assets/{id}', [FixedAssetController::class, 'destroy'])->middleware('permission:fixed_assets.delete');
    Route::post('fixed-assets/{id}/depreciate', [FixedAssetController::class, 'depreciate'])->middleware('permission:fixed_assets.post');
    Route::post('fixed-assets/depreciate-all', [FixedAssetController::class, 'depreciateAll'])->middleware('permission:fixed_assets.post');
    Route::post('fixed-assets/{id}/dispose', [FixedAssetController::class, 'dispose'])->middleware('permission:fixed_assets.post');

    // ===== المرحلة 7: التسويات البنكية =====
    Route::get('bank-reconciliations', [BankReconciliationController::class, 'index'])->middleware('permission:banks.view');
    Route::get('bank-reconciliations/{id}', [BankReconciliationController::class, 'show'])->middleware('permission:banks.view');
    Route::post('bank-reconciliations', [BankReconciliationController::class, 'store'])->middleware('permission:banks.create');
    Route::post('bank-reconciliations/{id}/toggle', [BankReconciliationController::class, 'toggle'])->middleware('permission:banks.edit');
    Route::post('bank-reconciliations/{id}/import-statement', [BankReconciliationController::class, 'importStatement'])->middleware('permission:banks.edit');
    Route::post('bank-reconciliations/{id}/complete', [BankReconciliationController::class, 'complete'])->middleware('permission:banks.edit');

    // ===== المرحلة 8: المخزون =====
    Route::get('warehouses', [WarehouseController::class, 'index'])->middleware('permission:inventory.view');
    Route::post('warehouses', [WarehouseController::class, 'store'])->middleware('permission:inventory.create');
    Route::put('warehouses/{id}', [WarehouseController::class, 'update'])->middleware('permission:inventory.edit');
    Route::delete('warehouses/{id}', [WarehouseController::class, 'destroy'])->middleware('permission:inventory.delete');

    Route::get('items', [InventoryItemController::class, 'index'])->middleware('permission:inventory.view');
    Route::get('items/{id}', [InventoryItemController::class, 'show'])->middleware('permission:inventory.view');
    Route::post('items', [InventoryItemController::class, 'store'])->middleware('permission:inventory.create');
    Route::put('items/{id}', [InventoryItemController::class, 'update'])->middleware('permission:inventory.edit');
    Route::delete('items/{id}', [InventoryItemController::class, 'destroy'])->middleware('permission:inventory.delete');

    Route::get('stock/valuation', [StockController::class, 'valuation'])->middleware('permission:inventory.view');
    Route::get('stock/item/{id}', [StockController::class, 'itemStock'])->middleware('permission:inventory.view');
    Route::post('stock/receive', [StockController::class, 'receive'])->middleware('permission:inventory.create');
    Route::post('stock/issue', [StockController::class, 'issue'])->middleware('permission:inventory.create');
    Route::post('stock/transfer', [StockController::class, 'transfer'])->middleware('permission:inventory.create');
    Route::post('stock/adjust', [StockController::class, 'adjust'])->middleware('permission:inventory.edit');

    // ===== المرحلة 9: المشتريات =====
    Route::get('purchase-invoices', [PurchaseInvoiceController::class, 'index'])->middleware('permission:purchases.view');
    Route::get('purchase-invoices/{id}', [PurchaseInvoiceController::class, 'show'])->middleware('permission:purchases.view');
    Route::post('purchase-invoices', [PurchaseInvoiceController::class, 'store'])->middleware('permission:purchases.create');
    Route::post('purchase-invoices/{id}/post', [PurchaseInvoiceController::class, 'post'])->middleware('permission:purchases.post');

    // ===== المرحلة 9: المبيعات =====
    Route::get('sales-invoices', [SalesInvoiceController::class, 'index'])->middleware('permission:sales.view');
    Route::get('sales-invoices/{id}', [SalesInvoiceController::class, 'show'])->middleware('permission:sales.view');
    Route::post('sales-invoices', [SalesInvoiceController::class, 'store'])->middleware('permission:sales.create');
    Route::post('sales-invoices/{id}/post', [SalesInvoiceController::class, 'post'])->middleware('permission:sales.post');

    // ===== المرحلة 10: الموارد البشرية والرواتب =====
    Route::get('salary-components', [SalaryComponentController::class, 'index'])->middleware('permission:hr.view');
    Route::post('salary-components', [SalaryComponentController::class, 'store'])->middleware('permission:hr.create');
    Route::put('salary-components/{id}', [SalaryComponentController::class, 'update'])->middleware('permission:hr.edit');
    Route::delete('salary-components/{id}', [SalaryComponentController::class, 'destroy'])->middleware('permission:hr.delete');

    Route::get('employees', [EmployeeController::class, 'index'])->middleware('permission:hr.view');
    Route::get('employees/{id}', [EmployeeController::class, 'show'])->middleware('permission:hr.view');
    Route::post('employees', [EmployeeController::class, 'store'])->middleware('permission:hr.create');
    Route::put('employees/{id}', [EmployeeController::class, 'update'])->middleware('permission:hr.edit');
    Route::delete('employees/{id}', [EmployeeController::class, 'destroy'])->middleware('permission:hr.delete');
    Route::post('employees/{id}/components', [EmployeeController::class, 'setComponents'])->middleware('permission:hr.edit');

    Route::get('payroll', [PayrollController::class, 'index'])->middleware('permission:hr.view');
    Route::get('payroll/{id}', [PayrollController::class, 'show'])->middleware('permission:hr.view');
    Route::post('payroll/generate', [PayrollController::class, 'generate'])->middleware('permission:hr.create');
    Route::post('payroll/{id}/post', [PayrollController::class, 'post'])->middleware('permission:hr.post');

    // ===== المرحلة 11: نقاط البيع (POS) =====
    Route::get('pos', [PosController::class, 'index'])->middleware('permission:pos.view');
    Route::get('pos/{id}', [PosController::class, 'show'])->middleware('permission:pos.view');
    Route::post('pos/checkout', [PosController::class, 'checkout'])->middleware('permission:pos.create');

    // ===== المرحلة 12: المرفقات (PDF) وربط صفحات السطور =====
    Route::get('journal-entries/{id}/attachments', [AttachmentController::class, 'index'])->middleware('permission:journals.view');
    Route::post('journal-entries/{id}/attachments', [AttachmentController::class, 'upload'])->middleware('permission:journals.edit');
    Route::post('journal-entries/{id}/line-pages', [AttachmentController::class, 'setLinePages'])->middleware('permission:journals.edit');
    Route::get('attachments/{id}/download', [AttachmentController::class, 'download'])->middleware('permission:journals.view');
    Route::delete('attachments/{id}', [AttachmentController::class, 'destroy'])->middleware('permission:journals.edit');

    // ===== المرحلة 13: التذكيرات/التنبيهات =====
    Route::get('reminders', [ReminderController::class, 'index'])->middleware('permission:reminders.view');
    Route::get('reminders/due', [ReminderController::class, 'due'])->middleware('permission:reminders.view');
    Route::post('reminders', [ReminderController::class, 'store'])->middleware('permission:reminders.create');
    Route::post('reminders/generate', [ReminderController::class, 'generate'])->middleware('permission:reminders.create');
    Route::put('reminders/{id}', [ReminderController::class, 'update'])->middleware('permission:reminders.edit');
    Route::post('reminders/{id}/done', [ReminderController::class, 'markDone'])->middleware('permission:reminders.edit');
    Route::post('reminders/{id}/dismiss', [ReminderController::class, 'dismiss'])->middleware('permission:reminders.edit');
    Route::delete('reminders/{id}', [ReminderController::class, 'destroy'])->middleware('permission:reminders.delete');

    // ===== الطباعة / PDF =====
    Route::get('sales-invoices/{id}/pdf', [DocumentPdfController::class, 'salesInvoice'])->middleware('permission:sales.view');
    Route::get('purchase-invoices/{id}/pdf', [DocumentPdfController::class, 'purchaseInvoice'])->middleware('permission:purchases.view');
    Route::get('journal-entries/{id}/pdf', [DocumentPdfController::class, 'journalEntry'])->middleware('permission:journals.view');
    Route::get('accounts/{id}/statement/pdf', [DocumentPdfController::class, 'statement'])->middleware('permission:accounts.view');

    // ===== المرحلة 14: سجل التدقيق =====
    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');

    // ===== المرحلة 15: الشيكات =====
    Route::get('cheques', [ChequeController::class, 'index'])->middleware('permission:cheques.view');
    Route::post('cheques', [ChequeController::class, 'store'])->middleware('permission:cheques.create');
    Route::post('cheques/{id}/clear', [ChequeController::class, 'clear'])->middleware('permission:cheques.edit');
    Route::post('cheques/{id}/bounce', [ChequeController::class, 'bounce'])->middleware('permission:cheques.edit');

    // ===== إدارة المستخدمين والأدوار =====
    Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create');
    Route::put('users/{id}', [UserController::class, 'update'])->middleware('permission:users.edit');
    Route::delete('users/{id}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:users.view');
    Route::get('roles/permissions', [RoleController::class, 'permissions'])->middleware('permission:users.view');
    Route::get('roles/{id}', [RoleController::class, 'show'])->middleware('permission:users.view');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:users.create');
    Route::put('roles/{id}', [RoleController::class, 'update'])->middleware('permission:users.edit');
    Route::delete('roles/{id}', [RoleController::class, 'destroy'])->middleware('permission:users.delete');

    // ===== الشركات والتبديل =====
    Route::get('company', [CompanyController::class, 'current'])->middleware('permission:settings.view');
    Route::put('company', [CompanyController::class, 'update'])->middleware('permission:settings.edit');
    Route::get('companies', [CompanyController::class, 'index']);
    Route::post('companies', [CompanyController::class, 'store']);
    Route::post('companies/{id}/switch', [CompanyController::class, 'switch']);

    // ===== المرحلة 16: المرتجعات =====
    Route::get('returns', [ReturnController::class, 'index'])->middleware('permission:returns.view');
    Route::post('returns', [ReturnController::class, 'store'])->middleware('permission:returns.create');
});
