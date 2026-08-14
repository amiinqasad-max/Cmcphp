<?php

namespace App\Models;

use App\Services\SitemapGenerator;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image_media_id',
        'seo_title',
        'seo_description',
        'position',
    ];

    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            if (blank($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        // Categories don't have a dedicated Observer class (no activity log
        // entry is warranted for them); the sitemap listing is the only
        // thing that needs invalidating on a category write.
        //
        // Statement-bodied closures, not `fn () => Cache::...->flush()`:
        // TaggedCache::flush()/Cache::forget() return a *boolean*, and a
        // closure registered via static::saved()/deleted() has its return
        // value treated as that listener's response — Illuminate's event
        // dispatcher halts *all further listeners for the same event* the
        // moment one returns exactly `false` (e.g. flush()/forget() on an
        // already-empty/never-cached key). An arrow function leaks that
        // boolean straight through; a statement body always discards it.
        static::saved(function () {
            Cache::tags([SitemapGenerator::CACHE_TAG])->flush();
        });
        static::deleted(function () {
            Cache::tags([SitemapGenerator::CACHE_TAG])->flush();
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
