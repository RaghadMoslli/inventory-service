<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {

        if (!auth('api')->check() || auth('api')->user()->role !== $role) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'ليس لديك صلاحية للقيام بهذه العملية'
                ]
            ], 403);
        }

        return $next($request);
    }
}