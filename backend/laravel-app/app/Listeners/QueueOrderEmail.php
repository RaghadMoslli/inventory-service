<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use App\Jobs\SendOrderEmailJob;
use App\Modules\Orders\Models\EmailLog;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        try {
            // Dispatch immediately (not via PendingDispatch destructor) so failures are catchable.
            app(Dispatcher::class)->dispatch(
                (new SendOrderEmailJob($log->id))->onQueue('emails')
            );
        } catch (Throwable $e) {
            // Order is already committed — never fail the HTTP request because RabbitMQ is down.
            $log->update([
                'status' => EmailLog::STATUS_DEAD,
                'error' => $e->getMessage(),
            ]);

            Log::warning('Failed to publish order email job; marked email_log dead for manual retry', [
                'email_log_id' => $log->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
