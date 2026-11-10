<?php

namespace App\Modules\Products\Observers;

use App\Modules\Products\Models\Product;
use App\Modules\Products\Services\ProductCacheService;

/**
 * يبطل كاش المنتجات تلقائيًا عند أي إضافة/تعديل/حذف، من أي مكان في الكود
 * (وليس فقط من ProductController)، فلا يمكن نسيان الإبطال.
 */
class ProductObserver
{
    public function __construct(private ProductCacheService $cache)
    {
    }

    public function saved(Product $product): void
    {
        $this->cache->invalidate();
    }

    public function deleted(Product $product): void
    {
        $this->cache->invalidate();
    }
}
