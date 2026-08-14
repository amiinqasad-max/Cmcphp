<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleCompletion extends Model
{
    use HasUuids;

    protected $fillable = [
        'post_id',
        'session_id',
        'user_id',
        'reading_progress_percent',
        'videos_completed_count',
        'videos_required_count',
        'completed_at',
        'next_post_id',
    ];

    protected function casts(): array
    {
        return [
            'reading_progress_percent' => 'integer',
            'videos_completed_count' => 'integer',
            'videos_required_count' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function nextPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'next_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
