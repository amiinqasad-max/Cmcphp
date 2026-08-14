<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Issues a long-lived, signed, httponly anonymous session cookie on first
 * visit (§9). The value is never generated or read by client JS — it rides
 * along automatically as a same-origin cookie on every request, including
 * the tracking-ingestion `fetch`/`sendBeacon` calls, so the client never
 * needs to know or transmit it explicitly.
 *
 * If the visitor is authenticated, `user_id` is additionally recorded on
 * tracking rows (see EngagementTrackingService) — since the anonymous
 * cookie persists across login, that naturally associates the pre-login
 * session with the account once they sign in, without any extra merge step.
 */
class EnsureAnonymousSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $cookieName = config('cms.anonymous_session_cookie');
        $sessionId = $request->cookie($cookieName);

        if (! $sessionId || ! Str::isUuid($sessionId)) {
            $sessionId = (string) Str::uuid();
        }

        $request->attributes->set('anonymous_session_id', $sessionId);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->setCookie(cookie()->make(
            name: $cookieName,
            value: $sessionId,
            minutes: config('cms.anonymous_session_lifetime_days') * 24 * 60,
            httpOnly: true,
            sameSite: 'lax',
        ));

        return $response;
    }
}
