<?php

namespace App\Jobs;

use App\Mail\OrderConfirmationMail;
use App\Modules\Orders\Models\EmailLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
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
    logger()->info('EMAIL_JOB_WORKER', [
        'email_log_id' => $this->emailLogId,
        'worker' => gethostname(),
        'timestamp' => now()->toDateTimeString(),
    ]);

    DB::transaction(function (): void {

        // Lock the email log row until the transaction finishes.
        $log = EmailLog::query()
            ->whereKey($this->emailLogId)
            ->lockForUpdate()
            ->first();

        if (! $log) {
            return;
        }

        // Recheck the status AFTER acquiring the lock.
        if ($log->isSent() || $log->isDead()) {
            logger()->info('EMAIL_JOB_SKIPPED', [
                'email_log_id' => $log->id,
                'status' => $log->status,
                'worker' => gethostname(),
            ]);

            return;
        }

        logger()->info('EMAIL_JOB_LOCK_ACQUIRED', [
            'email_log_id' => $log->id,
            'worker' => gethostname(),
        ]);

        $log->increment('attempts');

        $log->load('order.user');

        if (! $log->order) {
            throw new \RuntimeException(
                'Order missing for email log #'.$log->id
            );
        }

        logger()->info('EMAIL_JOB_SEND_ATTEMPT', [
            'email_log_id' => $log->id,
            'worker' => gethostname(),
        ]);

        Mail::to($log->to)->send(
            new OrderConfirmationMail($log->order)
        );

        $log->update([
            'status' => EmailLog::STATUS_SENT,
            'error' => null,
            'sent_at' => now(),
        ]);

        logger()->info('EMAIL_JOB_SENT', [
            'email_log_id' => $log->id,
            'worker' => gethostname(),
        ]);
    });
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
