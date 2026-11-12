<?php

namespace App\Http\Middleware;

use App\Support\PerfMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP caching بـ ETag.
 *  - يحسب بصمة (ETag) لمحتوى رد GET ناجح ويضعها في الترويسة ETag.
 *  - إذا أرسل العميل If-None-Match بنفس البصمة نرد 304 Not Modified بلا جسم (توفير عرض نطاق).
 *  - Cache-Control: no-cache تعني "خزّنه لكن اسألني قبل استعماله"، فلا يرى المستخدم بيانات قديمة أبدًا.
 *
 * ملاحظة: الرد يُبنى على السيرفر قبل حساب البصمة، فالتوفير هنا في حجم الرد المنقول،
 * أما توفير عمل السيرفر فيأتي من Redis.
 * يعمل فقط عند تفعيل flag "etag" (في baseline لا ترويسة ETag ولا 304).
 */
class ETagMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! PerfMode::on('etag')
            || ! $request->isMethodSafe()
            || $response->getStatusCode() !== 200
            || $response->getContent() === false
            || $response->getContent() === '') {
            return $response;
        }

        $hash = md5($response->getContent());
        // strong ETag: "hash" — أسهل للمطابقة من Postman/Swagger
        $etag = '"'.$hash.'"';
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'no-cache, private');

        if ($this->matchesIfNoneMatch($request, $hash)) {
            $response->setNotModified();
        }

        return $response;
    }

    /**
     * يقبل If-None-Match بعدة أشكال شائعة من العملاء:
     * "hash" | W/"hash" | hash | قائمة مفصولة بفواصل
     */
    private function matchesIfNoneMatch(Request $request, string $hash): bool
    {
        $header = $request->headers->get('If-None-Match');
        if ($header === null || trim($header) === '') {
            return false;
        }

        if (trim($header) === '*') {
            return true;
        }

        foreach (preg_split('/\s*,\s*/', $header) ?: [] as $candidate) {
            $normalized = strtolower(trim($candidate));
            $normalized = preg_replace('/^w\//', '', $normalized) ?? $normalized;
            $normalized = trim($normalized, "\"'");

            if ($normalized === strtolower($hash)) {
                return true;
            }
        }

        return false;
    }
}
