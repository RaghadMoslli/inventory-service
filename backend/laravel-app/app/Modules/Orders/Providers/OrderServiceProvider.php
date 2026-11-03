<?php

namespace App\Modules\Orders\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class OrderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // تسجيل أي خدمات أو ارتباطات خاصة بموديول الطلبات
    }

    public function boot(): void
    {
        // تسجيل مسارات الـ API الخاصة بالطلبات
        Route::prefix('api')
            ->middleware('api')
            ->group(__DIR__ . '/../Routes/api.php');
    }
}