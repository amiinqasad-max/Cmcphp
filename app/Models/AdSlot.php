<?php

namespace App\Models;

use App\Enums\AdSlotFormat;
use App\Enums\AdSlotStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdSlot extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'ad_client',
        'ad_slot_code',
        'format',
        'is_responsive',
        'is_default',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'format' => AdSlotFormat::class,
            'is_responsive' => 'boolean',
            'is_default' => 'boolean',
            'status' => AdSlotStatus::class,
        ];
    }

    public function placements(): HasMany
    {
        return $this->hasMany(AdPlacement::class);
    }

    public function isActive(): bool
    {
        return $this->status === AdSlotStatus::Active;
    }
}
