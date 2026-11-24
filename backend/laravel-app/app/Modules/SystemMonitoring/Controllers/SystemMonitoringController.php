<?php

namespace App\Modules\SystemMonitoring\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SystemMonitoring\Services\SystemMonitoringService;

class SystemMonitoringController extends Controller
{
    public function __construct(
        private SystemMonitoringService $monitoringService
    ) {}

    public function index()
    {
        $status = $this->monitoringService->getStatus();

        return response()->json([
            'data' => $status,
        ]);
    }
}