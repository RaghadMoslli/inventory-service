<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Users\Controllers\AuthController;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);

    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api');
});

Route::get('me', [AuthController::class, 'me'])->middleware('auth:api');