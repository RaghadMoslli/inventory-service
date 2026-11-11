<?php

namespace App\Console\Commands;

use App\Support\PerfMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * يشغّل EXPLAIN (ANALYZE) على أبطأ الاستعلامات ويطبع جدولًا مختصرًا:
 * نوع المسح (Seq Scan أو Index Scan) وزمن التنفيذ الفعلي.
 *
 *   php artisan perf:explain
 *   php artisan perf:explain --save      (يحفظ الخطط الكاملة في storage/app/perf/)
 *
 * شغّليه مرة بوضع baseline ومرة بوضع optimized، وقارني.
 */
class PerfExplainCommand extends Command
{
    protected $signature = 'perf:explain
        {--runs=3 : عدد مرات تنفيذ كل استعلام (نأخذ الوسيط)}
        {--save : حفظ الخطط الكاملة في ملف}';

    protected $description = 'EXPLAIN ANALYZE لأبطأ الاستعلامات وعرض نوع المسح والزمن';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->components->error('هذا الأمر غير مسموح في بيئة production.');

            return self::FAILURE;
        }

        $runs = max(1, (int) $this->option('runs'));
        $queries = $this->queries();

        if ($queries === null) {
            $this->components->error('لا توجد بيانات كافية. شغّلي أولًا: php artisan perf:seed --fresh --force');

            return self::FAILURE;
        }

        $rows = [];
        $saved = [];
        foreach ($queries as $name => $sql) {
            $times = [];
            $scans = [];
            for ($i = 0; $i < $runs; $i++) {
                $plan = $this->explainJson($sql);
                $times[] = (float) $plan['Execution Time'];
                $scans = $this->scanNodes($plan['Plan']);
            }
            sort($times);
            $median = $times[intdiv(count($times), 2)];

            $rows[] = [$name, implode(', ', array_unique($scans)), number_format($median, 2).' ms'];
            $saved[$name] = ['sql' => $sql, 'text' => $this->explainText($sql)];
        }

        $label = PerfMode::label();
        $this->newLine();
        $this->line("الوضع الحالي: <options=bold>{$label}</>   (الوسيط لـ {$runs} تشغيلات)");
        $this->table(['الاستعلام', 'نوع المسح', 'الزمن الفعلي'], $rows);

        if ($this->option('save')) {
            $dir = storage_path('app/perf');
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $file = $dir.'/explain-'.$label.'-'.date('Ymd-His').'.txt';
            $out = "Mode: {$label}\n";
            foreach ($saved as $name => $item) {
                $out .= "\n==== {$name} ====\n{$item['sql']}\n\n{$item['text']}\n";
            }
            file_put_contents($file, $out);
            $this->components->info("حُفظت الخطط الكاملة في: {$file}");
        }

        return self::SUCCESS;
    }

    /** @return array<string,string>|null */
    private function queries(): ?array
    {
        $topUser = DB::selectFromWriteConnection(
            'SELECT user_id FROM orders GROUP BY user_id ORDER BY COUNT(*) DESC LIMIT 1'
        );
        $ids = DB::selectFromWriteConnection('SELECT id FROM orders ORDER BY id DESC LIMIT 10');

        if ($topUser === [] || $ids === []) {
            return null;
        }

        $userId = (int) $topUser[0]->user_id;
        $idList = implode(',', array_map(fn ($r) => (int) $r->id, $ids));

        return [
            '1) منتجات تصنيف (صفحة 11)' => "SELECT * FROM products WHERE category = 'Books' ORDER BY id LIMIT 10 OFFSET 100",
            '2) عدّ منتجات التصنيف (pagination)' => "SELECT COUNT(*) FROM products WHERE category = 'Books'",
            '3) طلبات الـ admin (صفحة 1)' => 'SELECT * FROM orders ORDER BY created_at DESC LIMIT 10',
            '4) طلبات الـ admin (صفحة عميقة)' => 'SELECT * FROM orders ORDER BY created_at DESC LIMIT 10 OFFSET 20000',
            '5) طلبات الـ admin حسب الحالة' => "SELECT * FROM orders WHERE status = 'PENDING' ORDER BY created_at DESC LIMIT 10",
            '6) طلبات مستخدم' => "SELECT * FROM orders WHERE user_id = {$userId} ORDER BY created_at DESC LIMIT 10",
            '7) عناصر 10 طلبات (with items)' => "SELECT * FROM order_items WHERE order_id IN ({$idList})",
        ];
    }

    /** @return array<string,mixed> */
    private function explainJson(string $sql): array
    {
        $row = DB::selectFromWriteConnection("EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) {$sql}")[0];
        $decoded = json_decode(((array) $row)['QUERY PLAN'], true);

        return $decoded[0];
    }

    private function explainText(string $sql): string
    {
        $rows = DB::selectFromWriteConnection("EXPLAIN (ANALYZE, BUFFERS) {$sql}");

        return implode("\n", array_map(fn ($r) => ((array) $r)['QUERY PLAN'], $rows));
    }

    /** @return list<string> */
    private function scanNodes(array $node): array
    {
        $found = [];
        if (isset($node['Node Type']) && str_contains($node['Node Type'], 'Scan')) {
            $found[] = $node['Node Type'].(isset($node['Relation Name']) ? ' on '.$node['Relation Name'] : '');
        }
        foreach ($node['Plans'] ?? [] as $child) {
            $found = array_merge($found, $this->scanNodes($child));
        }

        return $found;
    }
}
