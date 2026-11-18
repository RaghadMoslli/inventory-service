<?php

use App\Broadcasting\AdminOrdersChannel;
use App\Modules\Orders\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('admin.orders', AdminOrdersChannel::class, ['guards' => ['api']]);

Broadcast::channel('orders.{orderId}', function (User $user, string $orderId): bool {
    $order = Order::find($orderId);

    return $order !== null
        && ($user->role === 'admin' || $user->id === $order->user_id);
}, ['guards' => ['api']]);
