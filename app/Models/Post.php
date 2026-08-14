<?php

namespace App\Models;

use App\Enums\NextArticleMode;
use App\Enums\PostStatus;
use App\Models\Concerns\HasSeoMetadata;
use App\Models\Concerns\SanitizesContentHtml;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory, HasSeoMetadata, HasUuids, SanitizesContentHtml, SoftDeletes;

    /** Average adult silent reading speed, used for the reading-time estimate. */
    private const WORDS_PER_MINUTE = 200;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'category_id',
        'author_id',
        'featured_image_media_id',
        'status',
        'published_at',
        'next_article_id',
        'next_article_mode',
        'reading_time_minutes',
        'completion_reading_threshold',
        'completion_required_videos',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'next_article_mode' => NextArticleMode::class,
            'published_at' => 'datetime',
            'completion_reading_threshold' => 'integer',
            'completion_required_videos' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            if (blank($post->slug)) {
                $post->slug = static::uniqueSlugFor(Str::slug($post->title), $post->id);
            }

            if (filled($post->content)) {
                $post->reading_time_minutes = static::estimateReadingTimeMinutes($post->content);
            }
        });
    }

    public static function uniqueSlugFor(string $base, ?string $ignoreId = null): string
    {
        $slug = $base;
        $suffix = 1;

        while (
            static::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public static function estimateReadingTimeMinutes(string $html): int
    {
        $text = trim(strip_tags($html));
        $wordCount = $text === '' ? 0 : str_word_count($text);

        return max(1, (int) ceil($wordCount / self::WORDS_PER_MINUTE));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_image_media_id');
    }

    public function nextArticle(): BelongsTo
    {
        return $this->belongsTo(self::class, 'next_article_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tags');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(PostVideo::class)->orderBy('position');
    }

    public function requiredVideos(): HasMany
    {
        return $this->videos()->where('is_required', true);
    }

    public function adPlacements(): HasMany
    {
        return $this->hasMany(AdPlacement::class)->orderBy('position_order');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function approvedTopLevelComments(): HasMany
    {
        return $this->comments()->approved()->whereNull('parent_id')->with('replies.user');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Published)
            ->where('published_at', '<=', now());
    }

    public function scopeInCategory(Builder $query, Category $category): Builder
    {
        return $query->where('category_id', $category->id);
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
