<?php

namespace Database\Factories;

use App\Enums\MenuItemType;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'parent_id' => null,
            'type' => MenuItemType::Custom,
            'label' => fake()->words(2, true),
            'url' => '/'.fake()->slug(),
            'target' => '_self',
            'is_visible' => true,
            'position' => 0,
        ];
    }
}
