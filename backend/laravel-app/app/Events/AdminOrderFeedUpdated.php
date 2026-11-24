<?php

namespace App\Events;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Support\OrderFeedPayload;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class AdminOrderFeedUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public array $payload;

    public function __construct(Order $order, public string $kind)
    {
        $this->payload = OrderFeedPayload::from($order);
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.orders')];
    }

    public function broadcastAs(): string
    {
        return $this->kind === self::CREATED ? 'order.created' : 'order.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
