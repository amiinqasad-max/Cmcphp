<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Menu extends Model
{
    use HasFactory, HasUuids;

    public const CACHE_TAG = 'menus';

    protected $fillable = [
        'name',
        'slug',
        'location',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Menu $menu) {
            if (blank($menu->slug)) {
                $menu->slug = static::uniqueSlugFor(Str::slug($menu->name), $menu->id);
            }
        });

        // Statement-bodied, not `fn () => ...->flush()`: TaggedCache::flush()
        // returns a boolean, and Illuminate's event dispatcher halts *every
        // other listener registered for the same event* the instant one
        // returns exactly `false` — an arrow function leaks that return
        // value straight through and would silently stop MenuObserver's
        // saved/deleted handling (activity logging) from ever running
        // whenever flush() happened to return false (e.g. an already-empty
        // cache tag). A statement body always discards it.
        static::saved(function () {
            Cache::tags([self::CACHE_TAG])->flush();
        });
        static::deleted(function () {
            Cache::tags([self::CACHE_TAG])->flush();
        });
    }

    public static function uniqueSlugFor(string $base, ?string $ignoreId = null): string
    {
        $slug = $base ?: 'menu';
        $suffix = 1;

        while (
            static::query()->where('slug', $slug)
                ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('position');
    }

    public function topLevelItems(): HasMany
    {
        return $this->items()->whereNull('parent_id');
    }

    /**
     * Nested tree of *renderable* items only (visible, and — for internal
     * references — pointing at content that still exists and is published;
     * §1 "prevent broken internal references"). Built in PHP from one flat,
     * eager-loaded query rather than N recursive queries.
     */
    public function renderableTree(): Collection
    {
        $flat = $this->items()
            ->with('linkable')
            ->get()
            ->filter(fn (MenuItem $item) => $item->is_visible && ! $item->isBroken());

        return MenuItem::buildTree($flat, null);
    }

    /** Every item regardless of visibility/broken state — what the admin editor works with. */
    public function fullTree(): Collection
    {
        return MenuItem::buildTree($this->items()->with('linkable')->get(), null);
    }
}
