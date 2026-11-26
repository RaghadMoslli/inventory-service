<?php

namespace App\Listeners;

use App\Events\AdminOrderFeedUpdated;
use App\Events\OrderCreated;
use Illuminate\Support\Facades\Log;
use Throwable;

class BroadcastNewOrderToAdmins
{
    public function handle(OrderCreated $event): void
    {
        try {
            event(new AdminOrderFeedUpdated($event->order, AdminOrderFeedUpdated::CREATED));
        } catch (Throwable $e) {
            // Order already saved — realtime feed must not fail the create response.
            Log::warning('Failed to broadcast new order to admins', [
                'order_id' => $event->order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
