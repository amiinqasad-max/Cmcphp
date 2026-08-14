<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets browsers/CDNs cache public GET pages for a short window (§35) —
 * applied only to the public content routes, never the admin panel or the
 * tracking API. Skipped for authenticated requests so a logged-in admin
 * previewing the site (or a reader with a session) never gets a stale,
 * shared cache entry.
 */
class SetPublicCacheHeaders
{
    private const MAX_AGE_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful() && ! $request->user()) {
            $response->headers->set('Cache-Control', 'public, max-age='.self::MAX_AGE_SECONDS);
        }

        return $response;
    }
}
