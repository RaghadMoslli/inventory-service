<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ModularRoutingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // تسجيل مسارات موديول Users
        Route::prefix('api')
            ->middleware('api')
            ->group(app_path('Modules/Users/Routes/api.php'));

       
        Route::prefix('api')
            ->middleware('api')
            ->group(app_path('Modules/Products/Routes/api.php'));

        Route::prefix('api')
            ->middleware('api')
            ->group(app_path('Modules/Notifications/Routes/api.php'));

        Route::prefix('api')
            ->middleware('api')
            ->group(app_path('Modules/Orders/Routes/api.php'));
    }
}