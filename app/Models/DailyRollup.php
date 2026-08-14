<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyRollup extends Model
{
    protected $fillable = [
        'date',
        'post_id',
        'pageviews',
        'sessions_count',
        'visitors_count',
        'avg_progress_percent',
        'avg_reading_seconds',
        'completions_count',
        'video_plays',
        'video_completions',
        'ad_requests',
        'ad_renders',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'pageviews' => 'integer',
            'sessions_count' => 'integer',
            'visitors_count' => 'integer',
            'avg_progress_percent' => 'integer',
            'avg_reading_seconds' => 'integer',
            'completions_count' => 'integer',
            'video_plays' => 'integer',
            'video_completions' => 'integer',
            'ad_requests' => 'integer',
            'ad_renders' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function scopeSiteWide(Builder $query): Builder
    {
        return $query->whereNull('post_id');
    }

    public function scopePerPost(Builder $query): Builder
    {
        return $query->whereNotNull('post_id');
    }
}
