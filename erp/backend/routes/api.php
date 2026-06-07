<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\AccountCategoryController;
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
    Route::get('accounts/tree', [AccountController::class, 'tree'])->middleware('permission:accounts.view');
    Route::post('accounts/import', [AccountController::class, 'import'])->middleware('permission:accounts.create');
    Route::get('accounts/{id}/balance', [AccountController::class, 'balance'])->middleware('permission:accounts.view');
    Route::get('accounts', [AccountController::class, 'index'])->middleware('permission:accounts.view');
    Route::get('accounts/{id}', [AccountController::class, 'show'])->middleware('permission:accounts.view');
    Route::post('accounts', [AccountController::class, 'store'])->middleware('permission:accounts.create');
    Route::put('accounts/{id}', [AccountController::class, 'update'])->middleware('permission:accounts.edit');
    Route::delete('accounts/{id}', [AccountController::class, 'destroy'])->middleware('permission:accounts.delete');
});
