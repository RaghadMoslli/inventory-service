<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Jobs\SendOrderEmailJob;
use App\Modules\Orders\Models\EmailLog;

class QueueOrderEmail
{
    public function handle(OrderCreated $event): void
    {
        $order = $event->order->loadMissing('user');
        $to = $order->user?->email;

        if (! $to) {
            return;
        }

        $log = EmailLog::create([
            'order_id' => $order->id,
            'to' => $to,
            'status' => EmailLog::STATUS_QUEUED,
            'attempts' => 0,
        ]);

        SendOrderEmailJob::dispatch($log->id)->onQueue('emails');
    }
}
