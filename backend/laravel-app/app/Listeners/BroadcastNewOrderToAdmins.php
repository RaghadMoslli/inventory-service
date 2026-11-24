<?php

namespace App\Listeners;

use App\Events\AdminOrderFeedUpdated;
use App\Events\OrderCreated;


class BroadcastNewOrderToAdmins
{
    public function handle(OrderCreated $event): void
    {
        event(new AdminOrderFeedUpdated($event->order, AdminOrderFeedUpdated::CREATED));
    }
}
