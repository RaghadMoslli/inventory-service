<?php

namespace App\Providers;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use App\Modules\Orders\Events\OrderCreated;
use App\Modules\Orders\Events\OrderStatusChanged;
use App\Modules\Notifications\Listeners\SendOrderNotification;
use App\Modules\Notifications\Listeners\SendStatusChangedNotification;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Policies\OrderPolicy;
use App\Modules\Products\Contracts\StockProvider;
use App\Modules\Products\Services\FakeStockProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(StockProvider::class, FakeStockProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
{
    Event::listen(
        OrderCreated::class,
        SendOrderNotification::class,
    );

    Event::listen(
        OrderStatusChanged::class,
        SendStatusChangedNotification::class,
    );

    Gate::policy(Order::class, OrderPolicy::class);
}
}
