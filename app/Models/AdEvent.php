<?php

namespace App\Models;

use App\Enums\AdEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ad_slot_id',
        'ad_placement_id',
        'post_id',
        'session_id',
        'event_type',
        'event_uuid',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => AdEventType::class,
            'created_at' => 'datetime',
        ];
    }

    public function adSlot(): BelongsTo
    {
        return $this->belongsTo(AdSlot::class);
    }

    public function adPlacement(): BelongsTo
    {
        return $this->belongsTo(AdPlacement::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
