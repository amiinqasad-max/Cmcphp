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
        static::saved(fn () => Cache::tags([SitemapGenerator::CACHE_TAG])->flush());
        static::deleted(fn () => Cache::tags([SitemapGenerator::CACHE_TAG])->flush());
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
