<?php

namespace App\Modules\Products\Contracts;

interface StockProvider
{

    public function getStock(array $productIds): array;

    public function setStock(int $productId, int $quantity): bool;

    public function reserve(string $requestId, array $items): array;

    public function release(string $reservationId): bool;
}
