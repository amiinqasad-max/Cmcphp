<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers (§34) applied to every response, admin and
 * public alike. Deliberately conservative — no Content-Security-Policy
 * here, since a strict CSP has to be tuned against the actual third-party
 * script surface (AdSense, YouTube/Vimeo embeds) or it breaks them; that's
 * a per-deployment tuning task documented in docs/DEPLOYMENT.md rather
 * than a one-size-fits-all default that would silently break ads/embeds.
 */
class SetSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
