<?php

namespace App\Modules\SystemMonitoring\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Throwable;

class SystemMonitoringService
{
    public function getStatus(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'rabbitmq' => $this->checkRabbitMQ(),
            'checked_at' => now()->toISOString(),
        ];
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');

            return ['status' => 'connected'];
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'unavailable'];
        }
    }

    private function checkRedis(): array
    {
        try {
            Redis::connection()->ping();

            return ['status' => 'connected'];
        } catch (Throwable $e) {
            report($e);

            return ['status' => 'unavailable'];
        }
    }

    private function checkRabbitMQ(): array
    {
        try {
            $response = Http::timeout(5)
                ->withBasicAuth(
                    config('services.rabbitmq.management_user', env('RABBITMQ_USER')),
                    config('services.rabbitmq.management_password', env('RABBITMQ_PASSWORD'))
                )
                ->get('http://rabbitmq:15672/api/queues/%2F');

            $response->throw();

            $queues = collect($response->json())->map(function (array $queue) {
                return [
                    'name' => $queue['name'],
                    'messages' => $queue['messages'],
                    'messages_ready' => $queue['messages_ready'],
                    'messages_unacknowledged' => $queue['messages_unacknowledged'],
                    'consumers' => $queue['consumers'],
                    'state' => $queue['state'],
                ];
            })->values();

            return [
                'status' => 'connected',
                'queues' => $queues,
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'status' => 'unavailable',
                'queues' => [],
            ];
        }
    }
}
