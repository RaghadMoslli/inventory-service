<?php

namespace App\Support;

use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * أعلام وضع الأداء (baseline / optimized).
 *
 * قبل (الطلب 5): كانت الحالة تُحفظ بملف storage/app/perf-mode.json على قرص النسخة
 * => لو شغّلنا perf:mode على نسخة واحدة، باقي النسخ ما بتعرف (Stateful).
 *
 * بعد (الطلب 5): الحالة بمكان مشترك واحد = Redis، فكل النسخ تقرأ نفس القيمة (Stateless).
 */
class PerfMode
{
    /** مفتاح Redis المشترك */
    public const REDIS_KEY = 'perf:mode:flags';

    private static ?array $cache = null;

    public static function flags(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $flags = array_fill_keys(config('perf.flags', []), true);

        if (! app()->isProduction()) {
            $data = self::readShared();
            if (is_array($data)) {
                foreach ($flags as $name => $_) {
                    if (array_key_exists($name, $data)) {
                        $flags[$name] = (bool) $data[$name];
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
        Redis::set(self::REDIS_KEY, json_encode($flags));
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

    private static function readShared(): ?array
    {
        try {
            $raw = Redis::get(self::REDIS_KEY);
            if (! is_string($raw) || $raw === '') {
                return null;
            }
            $data = json_decode($raw, true);

            return is_array($data) ? $data : null;
        } catch (Throwable) {
            // لو Redis غير متاح لا نكسر الطلب: نرجع للقيم الافتراضية (كل التحسينات مفعلة)
            return null;
        }
    }
}
