<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
| نقاط نهاية الـ API — المرحلة 0 (التأسيس)
| كل المسارات المحمية تمرّ بـ auth:sanctum ثم company (عزل المستأجر).
*/

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'company'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
