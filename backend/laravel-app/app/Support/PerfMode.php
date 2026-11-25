<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

class PerfMode
{
    private static ?array $cache = null;

    private const REDIS_KEY = 'perf:mode:flags';

    public static function flags(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $flags = array_fill_keys(config('perf.flags', []), true);

        if (! app()->isProduction()) {
            try {
                $data = Cache::store('redis')->get(self::REDIS_KEY);
                if (is_array($data)) {
                    foreach ($flags as $name => $_) {
                        if (array_key_exists($name, $data)) {
                            $flags[$name] = (bool) $data[$name];
                        }
                    }
                }
            } catch (Throwable) {
                // Redis down: keep defaults so requests still succeed.
            }
        }

        return self::$cache = $flags;
    }

    public static function on(string $flag): bool
    {
        return self::flags()[$flag] ?? true;
    }

    public static function save(array $flags): void
    {
        try {
            Cache::store('redis')->forever(self::REDIS_KEY, $flags);
        } catch (Throwable) {
            // Ignore persistence failure; in-memory cache still updates for this process.
        }

        self::forget();
        self::$cache = array_fill_keys(config('perf.flags', []), true);
        foreach (self::$cache as $name => $_) {
            if (array_key_exists($name, $flags)) {
                self::$cache[$name] = (bool) $flags[$name];
            }
        }
    }

    public static function label(): string
    {
        $flags = self::flags();
        if (! in_array(true, $flags, true)) {
            return 'baseline';
        }
        if (! in_array(false, $flags, true)) {
            return 'optimized';
        }

        return 'custom';
    }

    public static function forget(): void
    {
        self::$cache = null;
    }
}
