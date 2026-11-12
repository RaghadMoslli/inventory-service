<?php

namespace App\Modules\Products\Services;

use App\Support\PerfMode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductCacheService
{
    private const VERSION_KEY = 'products:version';
    private const RECENT_WRITE_KEY = 'products:recent_write';
    private const HITS = 'perf:cache:hits';
    private const MISSES = 'perf:cache:misses';

    public function enabled(): bool
    {
        return PerfMode::on('cache');
    }

    public function listKey(?string $category, int $page, int $perPage): string
    {
        return sprintf('products:v%d:list:%s:p%d:n%d', $this->version(), $category ?? 'all', $page, $perPage);
    }

    public function itemKey(int $id): string
    {
        return sprintf('products:v%d:item:%d', $this->version(), $id);
    }

    public function remember(string $key, callable $loader)
    {
        if (! $this->enabled()) {
            return $loader();
        }

        $ran = false;
        $load = function () use ($loader, &$ran) {
            $ran = true;

            return $loader();
        };

        try {
            $cached = Cache::get($key);
            if ($cached !== null) {
                $this->count(self::HITS);

                return $cached;
            }

            $lock = Cache::lock($key.':lock', 10);
            try {
                $lock->block(3);
            } catch (\Throwable) {

            }

            try {
                $cached = Cache::get($key);
                if ($cached !== null) {
                    $this->count(self::HITS);

                    return $cached;
                }

                $this->count(self::MISSES);
                $value = $load();
                if ($value !== null) {
                    Cache::put($key, $value, config('perf.cache_ttl', 600));
                }

                return $value;
            } finally {
                optional($lock)->release();
            }
        } catch (\Throwable $e) {
            if ($ran) {
                throw $e;
            }
            Log::warning('Product cache unavailable, falling back to database: '.$e->getMessage());

            return $loader();
        }
    }

    public function invalidate(): void
    {
        try {
            if (! Cache::has(self::VERSION_KEY)) {
                Cache::forever(self::VERSION_KEY, 1);
            }
            Cache::increment(self::VERSION_KEY);

            Cache::put(self::RECENT_WRITE_KEY, 1, config('perf.ryw_ttl', 5));
        } catch (\Throwable $e) {
            Log::warning('Product cache invalidation failed: '.$e->getMessage());
        }
    }

    public function mustReadFromPrimary(): bool
    {
        try {
            return (bool) Cache::has(self::RECENT_WRITE_KEY);
        } catch (\Throwable) {
            return false;
        }
    }

    public function stats(): array
    {
        $hits = (int) Cache::get(self::HITS, 0);
        $misses = (int) Cache::get(self::MISSES, 0);

        return [
            'hits' => $hits,
            'misses' => $misses,
            'version' => $this->version(),
            'hit_ratio' => ($hits + $misses) > 0 ? round($hits / ($hits + $misses), 3) : 0.0,
        ];
    }

    public function resetStats(): void
    {
        Cache::forget(self::HITS);
        Cache::forget(self::MISSES);
    }

    private function version(): int
    {
        try {
            return (int) Cache::get(self::VERSION_KEY, 1);
        } catch (\Throwable) {
            return 1;
        }
    }

    private function count(string $key): void
    {
        try {
            if (! Cache::has($key)) {
                Cache::forever($key, 0);
            }
            Cache::increment($key);
        } catch (\Throwable) {
        }
    }
}
