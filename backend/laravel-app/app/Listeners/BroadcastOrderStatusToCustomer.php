<?php

namespace App\Listeners;

use App\Events\CustomerOrderStatusUpdated;
use App\Events\OrderStatusChanged;

class BroadcastOrderStatusToCustomer
{
    public function handle(OrderStatusChanged $event): void
    {
        event(new CustomerOrderStatusUpdated(
            $event->order,
            $event->oldStatus,
            $event->newStatus,
        ));
    }
}
