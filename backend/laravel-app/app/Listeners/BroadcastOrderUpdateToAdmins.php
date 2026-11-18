<?php

namespace App\Listeners;

use App\Events\AdminOrderFeedUpdated;
use App\Events\OrderStatusChanged;


class BroadcastOrderUpdateToAdmins
{
    public function handle(OrderStatusChanged $event): void
    {
        event(new AdminOrderFeedUpdated($event->order, AdminOrderFeedUpdated::UPDATED));
    }
}
