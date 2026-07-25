<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Business data must never be served from a browser or proxy cache — a user
 * pressing Back should not see stale members, leads, or settings.
 *
 * Static assets are untouched (they are content-hashed by Vite and *should*
 * cache aggressively); this only covers application responses.
 */
class NoBrowserCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
