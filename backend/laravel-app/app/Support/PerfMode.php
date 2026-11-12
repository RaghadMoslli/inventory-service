<?php

namespace App\Support;

class PerfMode
{

    private static ?array $cache = null;

    public static function flags(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $flags = array_fill_keys(config('perf.flags', []), true);

        if (! app()->isProduction()) {
            $file = config('perf.state_file');
            if (is_string($file) && is_file($file)) {
                $data = json_decode((string) @file_get_contents($file), true);
                if (is_array($data)) {
                    foreach ($flags as $name => $_) {
                        if (array_key_exists($name, $data)) {
                            $flags[$name] = (bool) $data[$name];
                        }
                    }
                }
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
        $file = config('perf.state_file');
        $dir = dirname($file);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($file, json_encode($flags, JSON_PRETTY_PRINT), LOCK_EX);
        self::forget();
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
