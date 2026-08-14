<?php

namespace App\Models;

use App\Enums\PageStatus;
use App\Models\Concerns\HasSeoMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory, HasSeoMetadata, HasUuids, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'featured_image_media_id',
        'status',
        'published_at',
        'seo_title',
        'seo_description',
        'canonical_url',
    ];

    protected function casts(): array
    {
        return [
            'status' => PageStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Page $page) {
            if (blank($page->slug)) {
                $base = Str::slug($page->title);
                $slug = $base;
                $suffix = 1;

                while (
                    static::withTrashed()
                        ->where('slug', $slug)
                        ->when($page->id, fn (Builder $query) => $query->whereKeyNot($page->id))
                        ->exists()
                ) {
                    $slug = "{$base}-{$suffix}";
                    $suffix++;
                }

                $page->slug = $slug;
            }
        });
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_media_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PageStatus::Published)
            ->where('published_at', '<=', now());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
