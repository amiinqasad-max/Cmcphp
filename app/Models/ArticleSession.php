<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'post_id',
        'session_id',
        'user_id',
        'progress_percent',
        'time_spent_seconds',
        'bottom_reached',
        'is_completed',
        'started_at',
        'last_activity_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_percent' => 'integer',
            'time_spent_seconds' => 'integer',
            'bottom_reached' => 'boolean',
            'is_completed' => 'boolean',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
