<?php

namespace App\Modules\Products\Services;

use App\Modules\Products\Contracts\StockProvider;

class FakeStockProvider implements StockProvider
{
    public function getStock(array $productIds): array
    {
        $result = [];
        foreach ($productIds as $id) {
            // قيمة مؤقتة لحين اكتمال ربط gRPC الحقيقي
            $result[$id] = 10; 
        }
        return $result;
    }

    public function setStock(int $productId, int $quantity): bool
    {
        // تنفيذ مؤقت ناجح
        return true;
    }
}