<?php

namespace App\Models;

use App\Enums\AdPlacementType;
use App\Enums\AdSlotStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdPlacement extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'post_id',
        'ad_slot_id',
        'placement_type',
        'paragraph_index',
        'video_position',
        'position_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'placement_type' => AdPlacementType::class,
            'paragraph_index' => 'integer',
            'video_position' => 'integer',
            'position_order' => 'integer',
            'status' => AdSlotStatus::class,
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function adSlot(): BelongsTo
    {
        return $this->belongsTo(AdSlot::class);
    }

    public function isActive(): bool
    {
        return $this->status === AdSlotStatus::Active;
    }
}
