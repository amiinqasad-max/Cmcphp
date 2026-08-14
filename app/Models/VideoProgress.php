<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoProgress extends Model
{
    use HasUuids;

    protected $fillable = [
        'post_id',
        'post_video_id',
        'session_id',
        'user_id',
        'watched_seconds',
        'max_percent_reached',
        'play_count',
        'pause_count',
        'is_completed',
        'started_at',
        'last_watched_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'watched_seconds' => 'decimal:2',
            'max_percent_reached' => 'integer',
            'play_count' => 'integer',
            'pause_count' => 'integer',
            'is_completed' => 'boolean',
            'started_at' => 'datetime',
            'last_watched_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function postVideo(): BelongsTo
    {
        return $this->belongsTo(PostVideo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
