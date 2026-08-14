<?php

namespace App\Services;

/**
 * §5: rules for /robots.txt. The disallow list below was built by reading
 * the actual route structure (routes/web.php, routes/auth.php, and the
 * Filament admin panel's `->path('admin')` in AdminPanelProvider) — not
 * copied from a generic template — so update it here if a route prefix
 * changes rather than assuming the example in the spec still applies.
 */
class RobotsGenerator
{
    /**
     * Every one of these is either the Filament admin panel, a Breeze auth
     * route (routes/auth.php), an authenticated-only utility route, or the
     * tracking ingestion API — none of it is public content a crawler
     * should ever index.
     *
     * @return array<int, string>
     */
    public function disallowedPaths(): array
    {
        return [
            '/admin',
            '/login',
            '/register',
            '/forgot-password',
            '/reset-password',
            '/confirm-password',
            '/verify-email',
            '/email/verification-notification',
            '/dashboard',
            '/profile',
            '/api/',
        ];
    }

    public function sitemapUrl(): string
    {
        return route('sitemap');
    }
}
