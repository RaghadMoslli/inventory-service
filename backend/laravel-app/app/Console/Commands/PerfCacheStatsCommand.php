<?php

namespace App\Console\Commands;

use App\Modules\Products\Services\ProductCacheService;
use Illuminate\Console\Command;

class PerfCacheStatsCommand extends Command
{
    protected $signature = 'perf:cache-stats {--reset : تصفير العدّادات}';

    protected $description = 'إحصائيات كاش المنتجات (hits / misses / version)';

    public function handle(ProductCacheService $cache): int
    {
        if ($this->option('reset')) {
            $cache->resetStats();
            $this->components->info('تم تصفير العدّادات.');
        }

        $s = $cache->stats();
        $this->table(['hits', 'misses', 'hit ratio', 'version'], [[$s['hits'], $s['misses'], $s['hit_ratio'], $s['version']]]);
        $this->line('version يزيد بمقدار 1 عند كل إضافة/تعديل/حذف منتج (هذا هو إبطال الكاش).');

        return self::SUCCESS;
    }
}
