<?php

use App\Modules\SystemMonitoring\Controllers\SystemMonitoringController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'role:admin'])->group(function () {
    Route::get('/admin/system-status', [SystemMonitoringController::class, 'index']);
});