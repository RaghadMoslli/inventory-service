<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WorkerFailoverTestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $testId
    ) {}

    public function handle(): void
    {
        sleep(5);

        logger()->info('WORKER_FAILOVER_TEST_2411_A', [
            'test_id' => $this->testId,
            'worker' => gethostname(),
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}
