<?php

namespace App\Modules\Notifications\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // تسجيل أي خدمات خاصة بالموديول إذا لزم الأمر
    }

    public function boot(): void
    {
        // تسجيل مسارات الـ API الخاصة بموديول الإشعارات مع تحديد البادئة والوسيط
        Route::prefix('api/v1')
            ->middleware('api')
            ->group(__DIR__ . '/../Routes/api.php');
    }
}