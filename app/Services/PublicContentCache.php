<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Thin wrapper around Redis-tagged caching for the public site's read-heavy
 * listing queries (home, article index, category/tag pages). Kept as its
 * own service rather than scattering `Cache::tags(...)` calls so the tag
 * name and TTL live in exactly one place — and so PostObserver/PageObserver
 * have one thing to call on write instead of needing to know cache internals.
 */
class PublicContentCache
{
    public const POSTS_TAG = 'posts';

    public const PAGES_TAG = 'pages';

    private const TTL_MINUTES = 5;

    public function rememberPosts(string $key, \Closure $callback): mixed
    {
        return Cache::tags([self::POSTS_TAG])->remember($key, now()->addMinutes(self::TTL_MINUTES), $callback);
    }

    public function rememberPages(string $key, \Closure $callback): mixed
    {
        return Cache::tags([self::PAGES_TAG])->remember($key, now()->addMinutes(self::TTL_MINUTES), $callback);
    }

    public function flushPosts(): void
    {
        Cache::tags([self::POSTS_TAG])->flush();
    }

    public function flushPages(): void
    {
        Cache::tags([self::PAGES_TAG])->flush();
    }
}
