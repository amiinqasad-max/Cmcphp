<?php

namespace App\Models;

use App\Enums\EventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngagementEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'post_id',
        'post_video_id',
        'session_id',
        'user_id',
        'event_uuid',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => EventType::class,
            'payload' => 'array',
            'created_at' => 'datetime',
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
}
