<?php

namespace App\Jobs;

use App\Mail\OrderConfirmationMail;
use App\Modules\Orders\Models\EmailLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Throwable;

class SendOrderEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $emailLogId)
    {
        $this->onQueue('emails');
        $this->afterCommit = true;
    }

    public function handle(): void
    {
        $log = EmailLog::query()->with('order.user')->find($this->emailLogId);
        if (! $log) {
            return;
        }

        if ($log->isSent()) {
            return;
        }

        $log->increment('attempts');
        $log->refresh();

        if (! $log->order) {
            throw new \RuntimeException('Order missing for email log #'.$log->id);
        }

        Mail::to($log->to)->send(new OrderConfirmationMail($log->order));

        $log->update([
            'status' => EmailLog::STATUS_SENT,
            'error' => null,
            'sent_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception?->getMessage() ?? 'unknown error';

        $log = EmailLog::query()->find($this->emailLogId);
        if ($log && ! $log->isSent()) {
            $log->update([
                'status' => EmailLog::STATUS_DEAD,
                'error' => $message,
            ]);
        }

        try {
            Queue::connection('rabbitmq')->pushRaw(json_encode([
                'type' => 'email.dead',
                'email_log_id' => $this->emailLogId,
                'error' => $message,
                'failed_at' => now()->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE), 'emails.dlq');
        } catch (Throwable) {
        }
    }
}
