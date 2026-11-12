<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ModularRoutingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {

        $apiV1 = function (string $routesFile): void {
            Route::prefix('api/v1')
                ->middleware('api')
                ->group($routesFile);
        };

        $apiV1(app_path('Modules/Users/Routes/api.php'));
        $apiV1(app_path('Modules/Products/Routes/api.php'));
        $apiV1(app_path('Modules/Notifications/Routes/api.php'));
        $apiV1(app_path('Modules/Orders/Routes/api.php'));
    }
}