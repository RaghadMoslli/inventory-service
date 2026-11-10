<?php

/*
|--------------------------------------------------------------------------
| إعدادات قياس الأداء (المتطلب الثالث)
|--------------------------------------------------------------------------
| هذا الملف مصدر الحقيقة الوحيد لـ:
|   1) أسماء الأعلام (flags) التي نشغّل/نطفئ بها كل تحسين.
|   2) الفهارس التي تُنشأ بالـ migration ويتحكم بها الأمر perf:mode.
|
| الوضع الافتراضي (إذا لم يوجد ملف الحالة) = optimized، أي كل التحسينات مفعّلة.
| وضع baseline (كل شيء مطفأ) نطلبه صراحةً بالأمر:  php artisan perf:mode baseline
*/

return [

    // عند true يضيف الـ middleware ترويسات X-Query-Count و X-Query-Time-Ms و X-Perf-Mode للردود
    'debug' => env('PERF_DEBUG', false),

    // ملف صغير يحفظ حالة الأعلام (يُقرأ بكل طلب، فالتبديل فوري بلا إعادة تشغيل)
    'state_file' => storage_path('app/perf-mode.json'),

    // أسماء الأعلام المتاحة
    'flags' => [
        'indexes',        // فهارس قاعدة البيانات
        'eager_loading',  // حل N+1 بـ with()
        'cache',          // Redis (مرحلة لاحقة)
        'etag',           // ETag و 304 (مرحلة لاحقة)
        'replica',        // القراءة من Read Replica (مرحلة لاحقة)
    ],

    // الفهارس: اسم الفهرس => [الجدول, الأعمدة]
    'indexes' => [
        'products_category_id_idx'        => ['products', 'category, id'],
        'orders_user_id_created_at_idx'   => ['orders', 'user_id, created_at'],
        'orders_status_created_at_idx'    => ['orders', 'status, created_at'],
        'orders_created_at_idx'           => ['orders', 'created_at'],
        'order_items_order_id_idx'        => ['order_items', 'order_id'],
        'order_items_product_id_idx'      => ['order_items', 'product_id'],
    ],

    // مدة صلاحية الكاش بالثواني (الإبطال الفوري يتم عند أي تعديل، والـ TTL شبكة أمان فقط)
    'cache_ttl' => (int) env('PERF_CACHE_TTL', 600),

    // بعد أي كتابة يُجبَر نفس المستخدم على القراءة من الـ Primary لهذه المدة (ثوانٍ) = Read-Your-Writes
    'ryw_ttl' => (int) env('PERF_RYW_TTL', 5),

    // تصنيفات المنتجات المولّدة (الأولى أكثر شيوعًا لتوزيع واقعي)
    'categories' => [
        'Electronics', 'Clothing', 'Books', 'Home', 'Toys',
        'Sports', 'Beauty', 'Grocery', 'Garden', 'Automotive',
    ],
];
