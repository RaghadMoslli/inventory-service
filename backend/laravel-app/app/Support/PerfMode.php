<?php

namespace App\Support;

/**
 * قراءة وكتابة أعلام قياس الأداء.
 *
 * الاستعمال داخل الكود:
 *     if (PerfMode::on('eager_loading')) { ... الطريقة المحسّنة ... } else { ... الطريقة الأصلية ... }
 *
 * الحالة محفوظة في ملف JSON (config('perf.state_file')).
 * إذا لم يوجد الملف: كل الأعلام مفعّلة (optimized).
 * في بيئة production تُتجاهل الأعلام دائمًا ويُعتبر كل شيء مفعّلًا.
 */
class PerfMode
{
    /** @var array<string,bool>|null */
    private static ?array $cache = null;

    /** @return array<string,bool> */
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

    /** @param array<string,bool> $flags */
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

    /** baseline | optimized | custom */
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
