<?php

namespace App\Modules\Products\Contracts;

interface StockProvider
{
    /**
     * جلب الكمية المتوفرة لمنتج محدد أو مجموعة منتجات
     */
    public function getStock(array $productIds): array;

    /**
     * تعيين الكمية الأولية عند إنشاء منتج جديد
     */
    public function setStock(int $productId, int $quantity): bool;
}