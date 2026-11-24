<?php

namespace App\Providers;

use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Listeners\BroadcastNewOrderToAdmins;
use App\Listeners\BroadcastOrderUpdateToAdmins;
use App\Listeners\QueueOrderEmail;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Policies\OrderPolicy;
use App\Modules\Products\Contracts\StockProvider;
use App\Modules\Products\Services\FakeStockProvider;
use App\Modules\Products\Services\GrpcStockProvider;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Observers\ProductObserver;
use App\Support\PerfMode;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Listeners\BroadcastOrderStatusToCustomer;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StockProvider::class, function () {
            $driver = config('services.inventory.driver', env('INVENTORY_DRIVER', 'grpc'));

            if ($driver === 'fake') {
                return new FakeStockProvider();
            }

            return new GrpcStockProvider();
        });
    }

    public function boot(): void
    {
        \Illuminate\Foundation\Support\Providers\EventServiceProvider::disableEventDiscovery();

        Gate::policy(Order::class, OrderPolicy::class);

        Product::observe(ProductObserver::class);

        Event::listen(OrderCreated::class, QueueOrderEmail::class);
        Event::listen(OrderStatusChanged::class, BroadcastOrderStatusToCustomer::class);

        //  بث الطلب الجديد وتحديثاته للمسؤولين
        Event::listen(OrderCreated::class, BroadcastNewOrderToAdmins::class);
        Event::listen(OrderStatusChanged::class, BroadcastOrderUpdateToAdmins::class);

        if (config('database.connections.pgsql.read') && ! PerfMode::on('replica')) {
            config(['database.connections.pgsql.read' => ['host' => [config('database.connections.pgsql.write.host.0')]]]);
        }
    }
}
