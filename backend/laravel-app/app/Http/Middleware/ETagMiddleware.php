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

        $response->setEtag(md5($response->getContent()), true);   // weak ETag: W/"..."
        $response->headers->set('Cache-Control', 'no-cache, private');
        $response->isNotModified($request);                       // يحوّله إلى 304 إذا طابقت البصمة

        return $response;
    }
}
