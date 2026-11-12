<?php

use App\Modules\Orders\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {

    Route::get('/orders', [OrderController::class, 'index']);

    Route::post('/orders', [OrderController::class, 'store']);

    Route::get('/orders/{order}', [OrderController::class, 'show']);

    Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus'])
        ->middleware('role:admin');

});