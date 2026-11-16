<?php

namespace Tests\Feature;

use App\Broadcasting\AdminOrdersChannel;
use App\Events\AdminOrderFeedUpdated;
use App\Events\OrderCreated;
use App\Events\OrderStatusChanged;
use App\Models\User;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Contracts\StockProvider;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\FakeStockProvider;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AsyncRealtimeFlowTest extends TestCase
{
    use RefreshDatabase;

    private FakeStockProvider $stock;

    public function createApplication()
    {
        \Illuminate\Foundation\Support\Providers\EventServiceProvider::disableEventDiscovery();

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->stock = new FakeStockProvider();
        $this->app->instance(StockProvider::class, $this->stock);
    }

    private function makeOrder(User $customer, string $status = 'PENDING', ?string $reservationId = null): Order
    {
        return Order::create([
            'user_id' => $customer->id,
            'status' => $status,
            'reservation_id' => $reservationId,
            'total_amount' => 150,
        ]);
    }

    public function test_admin_channel_authorizes_only_admins(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        $channel = new AdminOrdersChannel();
        $this->assertTrue($channel->join($admin));
        $this->assertFalse($channel->join($customer));

        $this->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-admin.orders',
        ])->assertUnauthorized();
    }

    public function test_new_order_is_broadcast_to_admin_channel(): void
    {
        Queue::fake();

        $customer = User::factory()->create(['name' => 'Ahmad', 'role' => 'customer']);
        $order = $this->makeOrder($customer);

        event(new OrderCreated($order));

        Queue::assertPushedOn('broadcasts', BroadcastEvent::class, function (BroadcastEvent $job) use ($order) {
            $event = $job->event;

            return $event instanceof AdminOrderFeedUpdated
                && $event->broadcastAs() === 'order.created'
                && $event->broadcastOn()[0]->name === 'private-admin.orders'
                && $event->broadcastWith()['order_id'] === $order->id
                && $event->broadcastWith()['status'] === 'PENDING'
                && $event->broadcastWith()['customer_name'] === 'Ahmad'
                && $event->broadcastWith()['total'] === 150.0;
        });
    }

    public function test_admin_can_approve_pending_order_only_once(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrder($customer);

        $this->actingAs($admin, 'api')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'approved', 'note' => 'OK'])
            ->assertOk()
            ->assertJsonPath('data.status', 'CONFIRMED');

        $this->assertSame('CONFIRMED', $order->fresh()->status);

        $this->actingAs($admin, 'api')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'approved'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_TRANSITION');

        $this->actingAs($admin, 'api')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'rejected'])
            ->assertStatus(422);

        $this->assertSame('CONFIRMED', $order->fresh()->status);

        $this->actingAs($admin, 'api')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'maybe'])
            ->assertStatus(422);

        Event::assertDispatchedTimes(OrderStatusChanged::class, 1);
        Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $e) => $e->oldStatus === 'PENDING'
            && $e->newStatus === 'CONFIRMED'
            && $e->changedBy === $admin->id);
    }

    public function test_rejecting_order_cancels_it_and_releases_reserved_stock(): void
    {
        Event::fake([OrderStatusChanged::class]);

        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $product = Product::create(['name' => 'P1', 'price' => 10, 'category' => 'Books']);

        $this->stock->setStock($product->id, 5);
        $reservation = $this->stock->reserve('req-1', [['product_id' => $product->id, 'quantity' => 2]]);
        $this->assertSame(3, $this->stock->getStock([$product->id])[$product->id]);

        $order = $this->makeOrder($customer, 'PENDING', $reservation['reservation_id']);

        $this->actingAs($admin, 'api')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'rejected'])
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED');

        $this->assertSame('CANCELLED', $order->fresh()->status);
        $this->assertSame(5, $this->stock->getStock([$product->id])[$product->id]); // رجعت الكمية
    }

    // ---------------------------------------------------------------- 08
    public function test_customer_cannot_change_order_status(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->makeOrder($customer);

        $this->actingAs($customer, 'api')
            ->patchJson("/api/v1/admin/orders/{$order->id}/status", ['status' => 'approved'])
            ->assertForbidden();

        $this->assertSame('PENDING', $order->fresh()->status);
    }
}
