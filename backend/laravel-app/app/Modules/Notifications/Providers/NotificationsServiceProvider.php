<?php

namespace App\Modules\Notifications\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    public function boot(): void
    {

        Route::prefix('api/v1')
            ->middleware('api')
            ->group(__DIR__ . '/../Routes/api.php');
    }
}