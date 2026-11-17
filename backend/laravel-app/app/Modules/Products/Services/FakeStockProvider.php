<?php

namespace App\Modules\Products\Services;

use App\Modules\Products\Contracts\StockProvider;
use Illuminate\Support\Str;

class FakeStockProvider implements StockProvider
{

    private static array $stock = [];

    private static array $reservations = [];

    public function getStock(array $productIds): array
    {
        $result = [];
        foreach ($productIds as $id) {
            $result[(int) $id] = self::$stock[(int) $id] ?? 10;
        }

        return $result;
    }

    public function setStock(int $productId, int $quantity): bool
    {
        self::$stock[$productId] = $quantity;

        return true;
    }

    public function reserve(string $requestId, array $items): array
    {
        $shortages = [];
        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $qty = (int) $item['quantity'];
            $available = self::$stock[$productId] ?? 0;
            if ($available < $qty) {
                $shortages[] = [
                    'product_id' => $productId,
                    'requested' => $qty,
                    'available' => $available,
                ];
            }
        }

        if ($shortages !== []) {
            return [
                'success' => false,
                'reservation_id' => null,
                'shortages' => $shortages,
            ];
        }

        $reservationId = (string) Str::uuid();
        $reserved = [];
        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $qty = (int) $item['quantity'];
            self::$stock[$productId] = (self::$stock[$productId] ?? 0) - $qty;
            $reserved[$productId] = ($reserved[$productId] ?? 0) + $qty;
        }
        self::$reservations[$reservationId] = $reserved;

        return [
            'success' => true,
            'reservation_id' => $reservationId,
            'shortages' => [],
        ];
    }

    public function release(string $reservationId): bool
    {
        if (! isset(self::$reservations[$reservationId])) {
            return false;
        }

        foreach (self::$reservations[$reservationId] as $productId => $qty) {
            self::$stock[$productId] = (self::$stock[$productId] ?? 0) + $qty;
        }
        unset(self::$reservations[$reservationId]);

        return true;
    }
}
