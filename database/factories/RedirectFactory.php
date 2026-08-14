<?php

namespace Database\Factories;

use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source_path' => '/'.fake()->unique()->slug(),
            'destination' => '/'.fake()->slug(),
            'status_code' => 301,
            'is_active' => true,
            'hit_count' => 0,
        ];
    }
}
