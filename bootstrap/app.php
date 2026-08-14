<?php

use App\Http\Middleware\EnsureAnonymousSession;
use App\Http\Middleware\SetPublicCacheHeaders;
use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            EnsureAnonymousSession::class,
        ]);

        // Global (not ->web()-scoped): Filament's admin panel runs its own
        // explicit middleware pipeline rather than inheriting the 'web'
        // group, so security headers need the true global stack to reach
        // /admin too.
        $middleware->append(SetSecurityHeaders::class);

        $middleware->alias([
            'cache.public' => SetPublicCacheHeaders::class,
        ]);

        // Tracking-ingestion endpoints are called via `fetch`/`sendBeacon`,
        // which cannot attach a CSRF header. SameSite=Lax on the session
        // cookie already blocks cross-site fetch/XHR from carrying it, and
        // the endpoints are additionally rate-limited and strictly
        // payload-validated (§34/§46) — see routes/web.php's api/track group.
        $middleware->validateCsrfTokens(except: [
            'api/track/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
