<?php

namespace App\Http\Middleware;

use App\Support\PerfMode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class QueryCountMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('perf.debug')) {
            return $next($request);
        }

        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $response = $next($request);

        $log = $connection->getQueryLog();
        $connection->disableQueryLog();

        $response->headers->set('X-Query-Count', (string) count($log));
        $response->headers->set('X-Query-Time-Ms', (string) round(array_sum(array_column($log, 'time')), 2));
        $response->headers->set('X-Perf-Mode', PerfMode::label());

        return $response;
    }
}
