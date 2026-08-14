<?php

namespace Database\Factories;

use App\Enums\AdPlacementType;
use App\Enums\AdSlotStatus;
use App\Models\AdPlacement;
use App\Models\AdSlot;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdPlacement>
 */
class AdPlacementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'ad_slot_id' => AdSlot::factory(),
            'placement_type' => AdPlacementType::Top,
            'position_order' => 0,
            'status' => AdSlotStatus::Active,
        ];
    }

    public function afterParagraph(int $n): static
    {
        return $this->state(fn () => ['placement_type' => AdPlacementType::AfterParagraph, 'paragraph_index' => $n]);
    }
}
