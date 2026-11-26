<?php

namespace Tests\Feature;

use App\Events\OrderCreated;
use App\Jobs\SendOrderEmailJob;
use App\Listeners\QueueOrderEmail;
use App\Mail\OrderConfirmationMail;
use App\Models\User;
use App\Modules\Orders\Models\EmailLog;
use App\Modules\Orders\Models\Order;
use App\Modules\Products\Contracts\StockProvider;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\FakeStockProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderEmailQueueTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        \Illuminate\Foundation\Support\Providers\EventServiceProvider::disableEventDiscovery();

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(StockProvider::class, fn () => new FakeStockProvider());
    }

    public function test_creating_order_dispatches_order_created_event(): void
    {
        Event::fake([OrderCreated::class]);

        $fake = new FakeStockProvider();
        $this->app->instance(StockProvider::class, $fake);

        $user = User::factory()->create(['role' => 'customer']);
        $product = Product::create([
            'name' => 'P1',
            'price' => 10,
            'category' => 'Books',
        ]);
        $fake->setStock($product->id, 50);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ])
            ->assertCreated();

        Event::assertDispatched(OrderCreated::class);
    }

    public function test_listener_queues_email_job_and_creates_log(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email' => 'buyer@test.com', 'role' => 'customer']);
        $order = Order::create([
            'user_id' => $user->id,
            'status' => 'PENDING',
            'reservation_id' => 'r1',
            'total_amount' => 20,
        ]);
        $order->setRelation('user', $user);

        (new QueueOrderEmail())->handle(new OrderCreated($order));

        $this->assertDatabaseHas('email_logs', [
            'order_id' => $order->id,
            'to' => 'buyer@test.com',
            'status' => EmailLog::STATUS_QUEUED,
        ]);

        Queue::assertPushedOn('emails', SendOrderEmailJob::class);
    }

    public function test_listener_marks_email_dead_when_queue_publish_fails(): void
    {
        $this->mock(\Illuminate\Contracts\Bus\Dispatcher::class, function ($mock) {
            $mock->shouldReceive('dispatch')
                ->andThrow(new \RuntimeException('RabbitMQ unavailable'));
        });

        $user = User::factory()->create(['email' => 'buyer@test.com', 'role' => 'customer']);
        $order = Order::create([
            'user_id' => $user->id,
            'status' => 'PENDING',
            'reservation_id' => 'r1',
            'total_amount' => 20,
        ]);
        $order->setRelation('user', $user);

        (new QueueOrderEmail())->handle(new OrderCreated($order));

        $this->assertDatabaseHas('email_logs', [
            'order_id' => $order->id,
            'to' => 'buyer@test.com',
            'status' => EmailLog::STATUS_DEAD,
            'error' => 'RabbitMQ unavailable',
        ]);
    }

    public function test_email_job_marks_sent_and_is_not_duplicated(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'buyer@test.com', 'role' => 'customer']);
        $order = Order::create([
            'user_id' => $user->id,
            'status' => 'PENDING',
            'reservation_id' => 'r1',
            'total_amount' => 20,
        ]);
        $log = EmailLog::create([
            'order_id' => $order->id,
            'to' => $user->email,
            'status' => EmailLog::STATUS_QUEUED,
            'attempts' => 0,
        ]);

        $job = new SendOrderEmailJob($log->id);
        $job->handle();
        $job->handle();

        Mail::assertSent(OrderConfirmationMail::class, 1);
        $this->assertSame(EmailLog::STATUS_SENT, $log->fresh()->status);
    }

    public function test_admin_can_list_email_logs_customer_forbidden(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = Order::create([
            'user_id' => $customer->id,
            'status' => 'PENDING',
            'reservation_id' => 'r1',
            'total_amount' => 20,
        ]);
        EmailLog::create([
            'order_id' => $order->id,
            'to' => $customer->email,
            'status' => EmailLog::STATUS_QUEUED,
        ]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/v1/admin/email-logs')
            ->assertOk()
            ->assertJsonPath('stats.queued', 1);

        $this->actingAs($customer, 'api')
            ->getJson('/api/v1/admin/email-logs')
            ->assertForbidden();
    }
}
