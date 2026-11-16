<?php

namespace App\Modules\Orders\Support;

use App\Modules\Orders\Models\Order;

class OrderFeedPayload
{
    public static function from(Order $order): array
    {
        $order->loadMissing('user:id,name', 'items.product:id,name');

        return [
            'order_id' => $order->id,
            'status' => $order->status,
            'customer_name' => $order->user?->name,
            'total' => (float) $order->total_amount,
            'updated_at' => $order->updated_at?->copy()->utc()->toIso8601String(),
            'created_at' => $order->created_at?->copy()->utc()->toIso8601String(),
            'items_count' => $order->items->count(),
            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'quantity' => $item->quantity,
            ])->values()->all(),
        ];
    }
}
