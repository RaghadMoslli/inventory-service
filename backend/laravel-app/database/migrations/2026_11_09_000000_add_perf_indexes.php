<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * فهارس الأداء (المتطلب الثالث).
 *
 * قائمة الفهارس معرّفة في config/perf.php، وهي نفسها التي يستعملها الأمر
 * php artisan perf:mode لحذفها (baseline) أو إعادة إنشائها (optimized).
 *
 * ملاحظة: Postgres لا ينشئ فهرسًا تلقائيًا على المفاتيح الأجنبية
 * (orders.user_id و order_items.order_id و order_items.product_id)،
 * لذلك استعلامات with('items') و where user_id كانت تمسح الجدول كاملًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('perf.indexes') as $name => [$table, $columns]) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$table} ({$columns})");
        }
    }

    public function down(): void
    {
        foreach (array_keys(config('perf.indexes')) as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }
};
