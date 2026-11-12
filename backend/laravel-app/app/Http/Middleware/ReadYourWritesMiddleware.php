<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

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
