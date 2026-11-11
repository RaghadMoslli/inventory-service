<?php

namespace App\Modules\Products\Contracts;

interface StockProvider
{
    /**
     * @param  array<int>  $productIds
     * @return array<int, int> map productId => available quantity
     */
    public function getStock(array $productIds): array;

    public function setStock(int $productId, int $quantity): bool;

    /**
     * Atomically reserve stock for all order items.
     *
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     * @return array{success:bool, reservation_id:?string, shortages:array}
     */
    public function reserve(string $requestId, array $items): array;

    public function release(string $reservationId): bool;
}
