<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-Your-Writes عبر الطلبات المتتالية.
 *
 * المشكلة: الـ Replica قد تتأخر ثوانيَ عن الـ Primary. إن عدّل المستخدم منتجًا ثم أعاد تحميل القائمة
 * فقد تأتيه البيانات القديمة من الـ Replica.
 * الحل: بعد أي كتابة ناجحة نضع علامة في Redis (مفتاحها من بصمة التوكن، بدون استعلام DB) لمدة ryw_ttl ثانية،
 * وكل طلب من نفس المستخدم خلال هذه المدة يقرأ من الـ Primary. (داخل الطلب الواحد يتكفل بذلك sticky=true).
 */
class ReadYourWritesMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->key($request);

        try {
            if (Cache::has($key)) {
                DB::connection()->useWriteConnectionWhenReading();
            }
        } catch (\Throwable) {
            // Redis غير متاح: نكمل بدون الميزة
        }

        $response = $next($request);

        if (! $request->isMethodSafe() && $response->getStatusCode() < 400) {
            try {
                Cache::put($key, 1, config('perf.ryw_ttl', 5));
            } catch (\Throwable) {
            }
        }

        return $response;
    }

    private function key(Request $request): string
    {
        $who = $request->bearerToken() ?: $request->ip();

        return 'ryw:'.sha1((string) $who);
    }
}
