<?php

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true).' menu';

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'location' => null,
            'is_active' => true,
        ];
    }
}
