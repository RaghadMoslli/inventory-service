<?php

namespace App\Modules\Products\Services;

use App\Modules\Products\Contracts\StockProvider;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Real gRPC client for inventory.v1.InventoryService via grpcurl.
 * Speaks the same proto contract as the Spring inventory service.
 */
class GrpcStockProvider implements StockProvider
{
    private string $target;

    private int $timeoutMs;

    public function __construct(?string $host = null, ?int $port = null, ?int $timeoutMs = null)
    {
        $host = $host ?? config('services.inventory.host', 'inventory');
        $port = $port ?? (int) config('services.inventory.port', 9090);
        $this->timeoutMs = $timeoutMs ?? (int) config('services.inventory.timeout_ms', 3000);
        $this->target = sprintf('%s:%d', $host, $port);
    }

   public function getStock(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $payload = [
            'product_ids' => array_map('intval', array_values($productIds)),
        ];

        $response = $this->call('GetStock', $payload);
        $result = [];
        
        foreach ($response['items'] ?? [] as $item) {
            // دعم كلا الاحتمالات لتسمية المفاتيح منعاً لأي خطأ
            $id = $item['product_id'] ?? $item['productId'] ?? 0;
            $available = $item['available'] ?? $item['availableQuantity'] ?? 0;
            
            if ($id > 0) {
                $result[(int) $id] = (int) $available;
            }
        }

        foreach ($productIds as $id) {
            $result[(int) $id] = $result[(int) $id] ?? 0;
        }

        return $result;
    }

    public function setStock(int $productId, int $quantity): bool
    {
        $response = $this->call('SetStock', [
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);

        return (bool) ($response['ok'] ?? false);
    }

    public function reserve(string $requestId, array $items): array
    {
        $payloadItems = [];
        foreach ($items as $item) {
            $payloadItems[] = [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
            ];
        }

        $response = $this->call('Reserve', [
            'request_id' => $requestId,
            'items' => $payloadItems,
        ]);

        $shortages = [];
        foreach ($response['shortages'] ?? [] as $shortage) {
            $shortages[] = [
                'product_id' => (int) ($shortage['product_id'] ?? $shortage['productId'] ?? 0),
                'requested' => (int) ($shortage['requested'] ?? 0),
                'available' => (int) ($shortage['available'] ?? 0),
            ];
        }

        return [
            'success' => (bool) ($response['success'] ?? false),
            // grpcurl JSON often returns camelCase (reservationId)
            'reservation_id' => $response['reservation_id'] ?? $response['reservationId'] ?? null,
            'shortages' => $shortages,
        ];
    }

    public function release(string $reservationId): bool
    {
        $response = $this->call('Release', [
            'reservation_id' => $reservationId,
        ]);

        return (bool) ($response['released'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $method, array $payload): array
    {
        $candidates = [
            '/proto/inventory.proto',
            base_path('proto/inventory.proto'),
            base_path('../../inventory-service/proto/inventory.proto'),
        ];

        $protoPath = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $protoPath = $candidate;
                break;
            }
        }

        if ($protoPath === null) {
            throw new RuntimeException('inventory.proto not found for gRPC client');
        }

        $process = new Process([
            'grpcurl',
            '-plaintext',
            '-max-time',
            (string) max(1, (int) ceil($this->timeoutMs / 1000)),
            '-import-path',
            dirname($protoPath),
            '-proto',
            'inventory.proto',
            '-d',
            json_encode($payload, JSON_THROW_ON_ERROR),
            $this->target,
            'inventory.v1.InventoryService/'.$method,
        ]);

        $process->setTimeout(max(2, $this->timeoutMs / 1000 + 1));
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new RuntimeException(
                'Inventory gRPC unavailable or timed out: '.($error !== '' ? $error : 'unknown error')
            );
        }

        $output = trim($process->getOutput());
        if ($output === '') {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
