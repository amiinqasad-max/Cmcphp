<?php

namespace Database\Factories;

use App\Enums\AdSlotFormat;
use App\Enums\AdSlotStatus;
use App\Models\AdSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdSlot>
 */
class AdSlotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'ad_client' => 'ca-pub-'.fake()->numerify('################'),
            'ad_slot_code' => fake()->numerify('##########'),
            'format' => AdSlotFormat::Auto,
            'is_responsive' => true,
            'is_default' => false,
            'status' => AdSlotStatus::Active,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => AdSlotStatus::Inactive]);
    }
}
