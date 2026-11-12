<?php

namespace App\Console\Commands;

use App\Support\PerfMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class PerfModeCommand extends Command
{
    protected $signature = 'perf:mode
        {mode=status : baseline أو optimized أو status}
        {--enable=* : أعلام تُفعَّل فوق baseline، مثل indexes أو etag}';

    protected $description = 'تبديل النظام بين baseline (بدون تحسينات) و optimized (مع كل التحسينات)';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->components->error('هذا الأمر غير مسموح في بيئة production.');

            return self::FAILURE;
        }

        $mode = strtolower((string) $this->argument('mode'));
        $names = config('perf.flags', []);

        if (! in_array($mode, ['baseline', 'optimized', 'status'], true)) {
            $this->components->error("وضع غير معروف: {$mode}. المسموح: baseline | optimized | status");

            return self::FAILURE;
        }

        if ($mode !== 'status') {
            $enable = collect($this->option('enable'))
                ->flatMap(fn ($v) => explode(',', (string) $v))
                ->map(fn ($v) => trim($v))
                ->filter()
                ->values()
                ->all();

            $unknown = array_diff($enable, $names);
            if ($unknown !== []) {
                $this->components->error('أعلام غير معروفة: '.implode(', ', $unknown).' | المتاح: '.implode(', ', $names));

                return self::FAILURE;
            }

            $flags = [];
            foreach ($names as $name) {
                $flags[$name] = $mode === 'optimized' || in_array($name, $enable, true);
            }

            PerfMode::save($flags);
            $this->syncIndexes($flags['indexes'] ?? true);
            $this->components->info("تم التبديل إلى وضع: ".PerfMode::label());
        }

        $this->printStatus();

        return self::SUCCESS;
    }

    private function syncIndexes(bool $enabled): void
    {
        foreach (config('perf.indexes', []) as $name => [$table, $columns]) {
            if ($enabled) {
                DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$table} ({$columns})");
            } else {
                DB::statement("DROP INDEX IF EXISTS {$name}");
            }
        }

        DB::statement('ANALYZE products');
        DB::statement('ANALYZE orders');
        DB::statement('ANALYZE order_items');
    }

    private function printStatus(): void
    {
        $existing = collect(DB::selectFromWriteConnection(
            "SELECT indexname FROM pg_indexes WHERE schemaname = 'public'"
        ))->pluck('indexname')->all();

        $rows = [];
        foreach (PerfMode::flags() as $name => $on) {
            $rows[] = [$name, $on ? 'ON' : 'off'];
        }

        $this->newLine();
        $this->line('الوضع الحالي: <options=bold>'.PerfMode::label().'</>');
        $this->table(['العلم', 'الحالة'], $rows);

        $idx = [];
        foreach (array_keys(config('perf.indexes', [])) as $name) {
            $idx[] = [$name, in_array($name, $existing, true) ? 'موجود' : 'غير موجود'];
        }
        $this->table(['الفهرس', 'في قاعدة البيانات'], $idx);
    }
}
