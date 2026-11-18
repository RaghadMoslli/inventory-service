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
use App\Events\CustomerOrderStatusUpdated;
use App\Modules\Orders\Models\OrderStatusHistory;

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

        // الموافقة = الانتقال PENDING -> CONFIRMED عبر endpoint الحالة الموحّد
        $this->actingAs($admin, 'api')
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'CONFIRMED', 'note' => 'OK'])
            ->assertOk()
            ->assertJsonPath('data.status', 'CONFIRMED');

        $this->assertSame('CONFIRMED', $order->fresh()->status);

        // نفس الانتقال مرة ثانية: 422 (الطلب لم يعد PENDING)
        $this->actingAs($admin, 'api')
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'CONFIRMED'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_TRANSITION');

        // القفز ممنوع: CONFIRMED -> DELIVERED بدون المرور بـ SHIPPED
        $this->actingAs($admin, 'api')
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'DELIVERED'])
            ->assertStatus(422);

        $this->assertSame('CONFIRMED', $order->fresh()->status);

        // قيمة غير معروفة
        $this->actingAs($admin, 'api')
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'maybe'])
            ->assertStatus(422);

        // الحدث أُطلق مرة واحدة فقط (للانتقال الناجح)
        Event::assertDispatchedTimes(OrderStatusChanged::class, 1);
        Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $e) => $e->oldStatus === 'PENDING'
            && $e->newStatus === 'CONFIRMED'
            && $e->changedBy === $admin->id);
    }

    public function test_status_cannot_go_backwards_or_leave_final_states(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);

        $cases = [
            ['CONFIRMED', 'PENDING'],   // رجوع للخلف
            ['SHIPPED', 'CONFIRMED'],   // رجوع للخلف
            ['DELIVERED', 'CANCELLED'], // DELIVERED نهائية
            ['CANCELLED', 'CONFIRMED'], // CANCELLED نهائية (المخزون أُرجع)
        ];

        foreach ($cases as [$from, $to]) {
            $order = $this->makeOrder($customer, $from);

            $this->actingAs($admin, 'api')
                ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => $to])
                ->assertStatus(422);

            $this->assertSame($from, $order->fresh()->status);
        }
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

        // الرفض = الانتقال PENDING -> CANCELLED مع إرجاع المخزون
        $this->actingAs($admin, 'api')
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'CANCELLED'])
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
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'CONFIRMED'])
            ->assertForbidden();

        $this->assertSame('PENDING', $order->fresh()->status);
    }


public function test_customer_can_view_own_order_status_history(): void
{
    $customer = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->create(['role' => 'admin']);
    $order = $this->makeOrder($customer, 'CONFIRMED');

    OrderStatusHistory::create([
        'order_id' => $order->id,
        'old_status' => null,
        'new_status' => 'PENDING',
        'changed_by' => $customer->id,
        'note' => 'تم إنشاء الطلب',
    ]);

    OrderStatusHistory::create([
        'order_id' => $order->id,
        'old_status' => 'PENDING',
        'new_status' => 'CONFIRMED',
        'changed_by' => $admin->id,
        'note' => 'تم تأكيد الطلب',
    ]);

    $this->actingAs($customer, 'api')
        ->getJson("/api/v1/orders/{$order->id}/history")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.old_status', null)
        ->assertJsonPath('data.0.new_status', 'PENDING')
        ->assertJsonPath('data.0.changed_by', $customer->name)
        ->assertJsonPath('data.1.old_status', 'PENDING')
        ->assertJsonPath('data.1.new_status', 'CONFIRMED')
        ->assertJsonPath('data.1.changed_by', $admin->name);
}

public function test_customer_cannot_view_another_customers_order_history(): void
{
    $owner = User::factory()->create(['role' => 'customer']);
    $anotherCustomer = User::factory()->create(['role' => 'customer']);

    $order = $this->makeOrder($owner);

    OrderStatusHistory::create([
        'order_id' => $order->id,
        'old_status' => null,
        'new_status' => 'PENDING',
        'changed_by' => $owner->id,
        'note' => 'تم إنشاء الطلب',
    ]);

    $this->actingAs($anotherCustomer, 'api')
        ->getJson("/api/v1/orders/{$order->id}/history")
        ->assertForbidden();
}

public function test_admin_can_view_customer_order_history(): void
{
    $customer = User::factory()->create(['role' => 'customer']);
    $admin = User::factory()->create(['role' => 'admin']);

    $order = $this->makeOrder($customer);

    OrderStatusHistory::create([
        'order_id' => $order->id,
        'old_status' => null,
        'new_status' => 'PENDING',
        'changed_by' => $customer->id,
        'note' => 'تم إنشاء الطلب',
    ]);

    $this->actingAs($admin, 'api')
        ->getJson("/api/v1/orders/{$order->id}/history")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.new_status', 'PENDING');
}

public function test_order_status_change_broadcasts_to_customers_private_channel(): void
{
    Event::fake([CustomerOrderStatusUpdated::class]);

    $customer = User::factory()->create(['role' => 'customer']);
    $order = $this->makeOrder($customer, 'CONFIRMED');

    event(new OrderStatusChanged(
        $order,
        'PENDING',
        'CONFIRMED',
        1,
        'تم تأكيد الطلب'
    ));

    Event::assertDispatched(
        CustomerOrderStatusUpdated::class,
        function (CustomerOrderStatusUpdated $event) use ($order) {
            return $event->order->id === $order->id
                && $event->oldStatus === 'PENDING'
                && $event->newStatus === 'CONFIRMED'
                && $event->broadcastAs() === 'order.status.changed'
                && $event->broadcastOn()[0]->name === 'private-orders.' . $order->id
                && $event->broadcastWith()['order_id'] === $order->id
                && $event->broadcastWith()['old_status'] === 'PENDING'
                && $event->broadcastWith()['new_status'] === 'CONFIRMED'
                && $event->broadcastQueue() === 'broadcasts';
        }
    );
}

}