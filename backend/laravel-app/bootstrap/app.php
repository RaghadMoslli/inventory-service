<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\SecurityHeadersMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // تسجيل الـ Middleware الخاص بالصلاحيات (Admin)
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'etag' => \App\Http\Middleware\ETagMiddleware::class,
        ]);
        $middleware->append(SecurityHeadersMiddleware::class);
        $middleware->append(\App\Http\Middleware\ReadYourWritesMiddleware::class); // قراءة من الـ Primary بعد الكتابة (Replica)
        $middleware->append(\App\Http\Middleware\QueryCountMiddleware::class); // ترويسات X-Query-Count (عند PERF_DEBUG=true)
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 1. معالجة خطأ عدم وجود المورد (404 Not Found) للـ API
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'المورد المطلوب غير موجود',
                    ]
                ], 404);
            }
        });

        // 2. معالجة أخطاء المصادقة (401 Unauthenticated) للـ API
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'UNAUTHENTICATED',
                        'message' => 'يجب تسجيل الدخول للوصول إلى هذا المصدر',
                    ]
                ], 401);
            }
        });
    })->create();
