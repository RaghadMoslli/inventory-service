<?php

return [

    'debug' => env('PERF_DEBUG', false),

    'state_file' => storage_path('app/perf-mode.json'),

    'flags' => [
        'indexes',
        'eager_loading',
        'cache',
        'etag',
        'replica',
    ],

    'indexes' => [
        'products_category_id_idx'        => ['products', 'category, id'],
        'orders_user_id_created_at_idx'   => ['orders', 'user_id, created_at'],
        'orders_status_created_at_idx'    => ['orders', 'status, created_at'],
        'orders_created_at_idx'           => ['orders', 'created_at'],
        'order_items_order_id_idx'        => ['order_items', 'order_id'],
        'order_items_product_id_idx'      => ['order_items', 'product_id'],
        'notifications_user_id_idx'       => ['notifications', 'user_id'],
    ],

    'cache_ttl' => (int) env('PERF_CACHE_TTL', 600),

    'ryw_ttl' => (int) env('PERF_RYW_TTL', 5),

    'categories' => [
        'Electronics', 'Clothing', 'Books', 'Home', 'Toys',
        'Sports', 'Beauty', 'Grocery', 'Garden', 'Automotive',
    ],
];
