<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api/v1', 'middleware' => ['api', 'auth:api']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust Nginx so rate-limit / auth see the real client IP via X-Forwarded-For.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
        );

        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'etag' => \App\Http\Middleware\ETagMiddleware::class,
        ]);
        $middleware->append(SecurityHeadersMiddleware::class);
        $middleware->append(\App\Http\Middleware\ReadYourWritesMiddleware::class);
        $middleware->append(\App\Http\Middleware\QueryCountMiddleware::class);
        $middleware->append(\App\Http\Middleware\AddInstanceHeader::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {

                if ($e instanceof ValidationException) {
                    return response()->json([
                        'error' => [
                            'code' => 'VALIDATION_ERROR',
                            'message' => 'فشل التحقق من صحة البيانات المدخلة',
                            'details' => $e->errors(),
                        ]
                    ], 422);
                }

                if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                    return response()->json([
                        'error' => [
                            'code' => 'NOT_FOUND',
                            'message' => 'المورد المطلوب غير موجود',
                        ]
                    ], 404);
                }

                if ($e instanceof AuthenticationException) {
                    return response()->json([
                        'error' => [
                            'code' => 'UNAUTHENTICATED',
                            'message' => 'يجب تسجيل الدخول للوصول إلى هذا المصدر',
                        ]
                    ], 401);
                }

                if ($e instanceof AccessDeniedHttpException) {
                    return response()->json([
                        'error' => [
                            'code' => 'FORBIDDEN',
                            'message' => 'ليس لديك الصلاحية للقيام بهذا الإجراء',
                        ]
                    ], 403);
                }

                if ($e instanceof MethodNotAllowedHttpException) {
                    return response()->json([
                        'error' => [
                            'code' => 'METHOD_NOT_ALLOWED',
                            'message' => 'طريقة الطلب (HTTP Method) غير مسجلة لهذا المسار',
                        ]
                    ], 405);
                }

                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

                return response()->json([
                    'error' => [
                        'code' => 'SERVER_ERROR',
                        'message' => $e->getMessage() ?: 'حدث خطأ غير متوقع في الخادم',
                    ]
                ], $statusCode);
            }
        });

    })->create();