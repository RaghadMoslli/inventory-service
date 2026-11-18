<?php

use App\Modules\Orders\Controllers\EmailLogController;
use App\Modules\Orders\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {

    Route::get('/orders', [OrderController::class, 'index']);

    Route::post('/orders', [OrderController::class, 'store']);

    Route::get('/orders/{order}', [OrderController::class, 'show']);

    Route::get('/orders/{id}/email-status', [EmailLogController::class, 'orderStatus']);

    // العميل: إلغاء من PENDING أو CONFIRMED فقط
    Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    // الأدمن: تغيير الحالة للأمام (State Machine). endpoint واحد فقط
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])
        ->middleware('role:admin');

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/email-logs', [EmailLogController::class, 'index']);
        Route::post('/admin/email-logs/{id}/retry', [EmailLogController::class, 'retry']);
    });

});