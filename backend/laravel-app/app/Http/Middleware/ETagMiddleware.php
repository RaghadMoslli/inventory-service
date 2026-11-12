<?php

namespace App\Http\Middleware;

use App\Support\PerfMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        $etag = '"'.$hash.'"';
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'no-cache, private');

        if ($this->matchesIfNoneMatch($request, $hash)) {
            $response->setNotModified();
        }

        return $response;
    }

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
