<?php

use App\Http\Controllers\AuthController;
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
});
