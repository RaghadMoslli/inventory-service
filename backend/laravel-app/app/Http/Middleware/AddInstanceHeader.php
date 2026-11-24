<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddInstanceHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $instance = config('app.instance_name', 'unknown');

        $response->headers->set('X-Instance', $instance);

        return $response;
    }
}