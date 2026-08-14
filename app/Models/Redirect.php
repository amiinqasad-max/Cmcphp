<?php

namespace App\Models;

use App\Enums\RedirectStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Redirect extends Model
{
    use HasFactory, HasUuids;

    public const CACHE_KEY = 'redirects.active_map';

    protected $fillable = [
        'source_path',
        'destination',
        'status_code',
        'is_active',
        'hit_count',
        'last_hit_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => RedirectStatus::class,
            'is_active' => 'boolean',
            'hit_count' => 'integer',
            'last_hit_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $redirect) {
            $redirect->source_path = self::normalizePath($redirect->source_path);

            if (self::isInternalPath($redirect->destination)) {
                $redirect->destination = self::normalizePath($redirect->destination);
            }
        });

        // Every write invalidates the single cached lookup map (§6 —
        // "avoid querying the database unnecessarily on every request").
        // hit-count bumps go through incrementHit() below, which
        // deliberately does NOT invalidate the cache (see its docblock).
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** "/foo/bar", no trailing slash (except root), no query string, always leading-slash. */
    public static function normalizePath(string $path): string
    {
        $path = trim((string) parse_url($path, PHP_URL_PATH), '/');

        return $path === '' ? '/' : '/'.$path;
    }

    public static function isInternalPath(string $destination): bool
    {
        return str_starts_with($destination, '/');
    }

    /**
     * Would redirecting $sourcePath -> $destination ever lead back to
     * $sourcePath, directly or by chaining through other active redirects?
     * External destinations can't loop within our own table, so they're
     * always safe here (§6 "prevent redirect loops").
     */
    public static function wouldCreateLoop(string $sourcePath, string $destination, ?string $ignoreId = null): bool
    {
        if (! self::isInternalPath($destination)) {
            return false;
        }

        $source = self::normalizePath($sourcePath);
        $current = self::normalizePath($destination);

        if ($current === $source) {
            return true;
        }

        $map = self::query()
            ->where('is_active', true)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->pluck('destination', 'source_path');

        $visited = [$source => true];
        $guard = 0;

        while ($guard < 50) {
            if (isset($visited[$current])) {
                return true;
            }

            $visited[$current] = true;
            $next = $map->get($current);

            if ($next === null || ! self::isInternalPath($next)) {
                return false;
            }

            $current = self::normalizePath($next);
            $guard++;
        }

        return true;
    }

    /**
     * Bump hit tracking without touching the cached lookup map — a redirect
     * being *used* doesn't change where it points, so there's nothing to
     * invalidate, and every hit doesn't need to pay for a fresh cache build.
     */
    public function incrementHit(): void
    {
        self::withoutEvents(function () {
            $this->increment('hit_count', 1, ['last_hit_at' => now()]);
        });
    }
}
