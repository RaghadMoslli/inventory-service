<?php

namespace App\Providers;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Policies\OrderPolicy;
use App\Modules\Products\Contracts\StockProvider;
use App\Modules\Products\Services\FakeStockProvider;
use App\Modules\Products\Services\GrpcStockProvider;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Observers\ProductObserver;
use App\Support\PerfMode;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(Order::class, OrderPolicy::class);

        // إبطال كاش المنتجات تلقائيًا عند أي تعديل
        Product::observe(ProductObserver::class);

        // flag "replica": عند الإطفاء (baseline) كل القراءات تذهب للـ Primary
        if (config('database.connections.pgsql.read') && ! PerfMode::on('replica')) {
            config(['database.connections.pgsql.read' => ['host' => [config('database.connections.pgsql.write.host.0')]]]);
        }
    }
}
