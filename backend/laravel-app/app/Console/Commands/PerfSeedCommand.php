<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PerfSeedCommand extends Command
{
    protected $signature = 'perf:seed
        {--users=1000 : عدد المستخدمين الوهميين (perfuser1@example.test ...)}
        {--products=5000 : عدد المنتجات}
        {--orders=50000 : عدد الطلبات}
        {--max-items=4 : أكبر عدد عناصر في الطلب الواحد}
        {--seed=42 : رقم ثابت لتوليد بيانات متطابقة عند كل تشغيل}
        {--fresh : حذف المنتجات والطلبات الحالية أولًا}
        {--force : بدون سؤال تأكيد}';

    protected $description = 'توليد بيانات ضخمة (مستخدمون، منتجات، طلبات، عناصر طلبات) لقياس الأداء';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->components->error('هذا الأمر غير مسموح في بيئة production.');

            return self::FAILURE;
        }

        $users = max(1, (int) $this->option('users'));
        $products = max(1, (int) $this->option('products'));
        $orders = max(0, (int) $this->option('orders'));
        $maxItems = max(1, (int) $this->option('max-items'));
        $seed = (int) $this->option('seed');

        if ($this->option('fresh')) {
            if (! $this->option('force') && ! $this->confirm('سيتم حذف كل المنتجات والطلبات وعناصرها الحالية. متابعة؟')) {
                return self::FAILURE;
            }
            DB::statement('TRUNCATE order_items, orders, products RESTART IDENTITY');
            $this->components->info('تم تفريغ المنتجات والطلبات.');
        }

        DB::unprepared('SELECT setseed('.number_format(($seed % 1000) / 1000, 3, '.', '').')');

        $this->step('حسابات ثابتة (admin و customer)', function () {
            User::firstOrCreate(['email' => 'admin@test.com'], [
                'name' => 'System Admin', 'password' => 'password123', 'role' => 'admin',
            ]);
            User::firstOrCreate(['email' => 'customer@test.com'], [
                'name' => 'Test Customer', 'password' => 'password123', 'role' => 'customer',
            ]);
        });

        $hash = Hash::make('password123');

        $this->step("المستخدمون ({$users})", function () use ($users, $hash) {
            DB::statement(
                "INSERT INTO users (name, email, password, role, created_at, updated_at)
                 SELECT 'Perf User ' || g, 'perfuser' || g || '@example.test', ?, 'customer', now(), now()
                 FROM generate_series(1, ?) g
                 ON CONFLICT (email) DO NOTHING",
                [$hash, $users]
            );
        });

        $firstProductId = $this->maxId('products') + 1;
        $this->step("المنتجات ({$products})", function () use ($products) {
            $categories = config('perf.categories');
            $arr = "(ARRAY['".implode("','", $categories)."'])";
            $n = count($categories);

            DB::statement(
                "INSERT INTO products (name, description, price, category, image_path, created_at, updated_at)
                 SELECT 'Product ' || g,
                        'Auto generated product ' || g || ' for performance testing',
                        round((5 + random() * 495)::numeric, 2),
                        {$arr}[1 + floor(power(random(), 1.5) * {$n})::int],
                        NULL,
                        now() - (random() * interval '365 days'),
                        now()
                 FROM generate_series(1, ?) g",
                [$products]
            );
        });

        $firstOrderId = $this->maxId('orders') + 1;
        if ($orders > 0) {
            $this->step("الطلبات ({$orders})", function () use ($orders) {

                DB::statement(
                    "WITH u AS (SELECT array_agg(id ORDER BY id) AS a FROM users WHERE email LIKE 'perfuser%@example.test')
                     INSERT INTO orders (user_id, status, reservation_id, total_amount, created_at, updated_at)
                     SELECT u.a[1 + floor(random() * array_length(u.a, 1))::int],
                            CASE WHEN s.r < 0.50 THEN 'DELIVERED'
                                 WHEN s.r < 0.65 THEN 'SHIPPED'
                                 WHEN s.r < 0.80 THEN 'CONFIRMED'
                                 WHEN s.r < 0.90 THEN 'PENDING'
                                 ELSE 'CANCELLED' END,
                            NULL, 0, s.ts, s.ts
                     FROM u,
                          (SELECT random() AS r, now() - (random() * interval '365 days') AS ts
                           FROM generate_series(1, ?) g) s",
                    [$orders]
                );
            });

            $this->step('عناصر الطلبات', function () use ($firstOrderId, $maxItems) {

                DB::statement(
                    "WITH p AS (SELECT array_agg(id ORDER BY id) AS a FROM products)
                     INSERT INTO order_items (order_id, product_id, quantity, unit_price, created_at, updated_at)
                     SELECT s.order_id, s.pid, s.qty, pr.price, s.ts, s.ts
                     FROM (
                         SELECT o.id AS order_id,
                                o.created_at AS ts,
                                p.a[1 + floor(power(random(), 2) * array_length(p.a, 1))::int] AS pid,
                                1 + floor(random() * 3)::int AS qty
                         FROM orders o
                         CROSS JOIN p
                         CROSS JOIN LATERAL generate_series(1, 1 + ((o.id * 2654435761) % ?::int)::int) k
                         WHERE o.id >= ?
                     ) s
                     JOIN products pr ON pr.id = s.pid",
                    [$maxItems, $firstOrderId]
                );
            });

            $this->step('حساب إجمالي كل طلب', function () use ($firstOrderId) {
                DB::statement(
                    'UPDATE orders o SET total_amount = s.t
                     FROM (SELECT order_id, SUM(quantity * unit_price) AS t
                           FROM order_items WHERE order_id >= ? GROUP BY order_id) s
                     WHERE o.id = s.order_id AND o.id >= ?',
                    [$firstOrderId, $firstOrderId]
                );
            });
        }

        $this->step('ANALYZE (تحديث إحصائيات المخطِّط)', function () {
            foreach (['users', 'products', 'orders', 'order_items'] as $t) {
                DB::statement("ANALYZE {$t}");
            }
        });

        app(\App\Modules\Products\Services\ProductCacheService::class)->invalidate();

        $this->summary();

        return self::SUCCESS;
    }

    private function maxId(string $table): int
    {
        return (int) DB::selectFromWriteConnection("SELECT COALESCE(MAX(id), 0) AS m FROM {$table}")[0]->m;
    }

    private function step(string $label, callable $fn): void
    {
        $t = microtime(true);
        $fn();
        $this->line(sprintf('  ✔ %-40s %6.2f s', $label, microtime(true) - $t));
    }

    private function summary(): void
    {
        $count = fn (string $t) => (int) DB::selectFromWriteConnection("SELECT COUNT(*) AS c FROM {$t}")[0]->c;
        $range = DB::selectFromWriteConnection('SELECT MIN(id) AS a, MAX(id) AS b FROM products')[0];

        $this->newLine();
        $this->table(['الجدول', 'عدد الصفوف'], [
            ['users', $count('users')],
            ['products', $count('products')],
            ['orders', $count('orders')],
            ['order_items', $count('order_items')],
        ]);

        $this->components->info('الخطوة التالية: مخزون المنتجات في قاعدة Spring (قاعدة منفصلة):');
        $this->line(
            "docker exec -i inventory_postgres psql -U postgres -d inventory_db -c \"INSERT INTO stock (product_id, available, reserved) "
            ."SELECT g, 100000, 0 FROM generate_series({$range->a}, {$range->b}) g "
            ."ON CONFLICT (product_id) DO UPDATE SET available = 100000;\""
        );
        $this->line('كلمة سر كل المستخدمين الوهميين: password123 (مثل perfuser1@example.test)');
    }
}
