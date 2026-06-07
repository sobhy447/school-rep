<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\AccountCategoryController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\VoucherController;
use App\Http\Controllers\Settings\BranchController;
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
});
