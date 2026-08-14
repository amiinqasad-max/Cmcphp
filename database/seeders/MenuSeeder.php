<?php

namespace Database\Seeders;

use App\Enums\MenuItemType;
use App\Models\Menu;
use Illuminate\Database\Seeder;

/**
 * A sensible default nav so a fresh install isn't blank (§1) — everything
 * here is exactly what the hard-coded nav used to be before Phase 3, now
 * fully admin-editable from Filament instead of requiring a code change.
 * Safe to re-run: firstOrCreate keyed on the location.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $primary = Menu::query()->firstOrCreate(
            ['location' => 'primary_navigation'],
            ['name' => 'Primary Navigation', 'slug' => 'primary-navigation', 'is_active' => true]
        );

        if ($primary->items()->count() === 0) {
            $primary->items()->create(['type' => MenuItemType::Custom->value, 'label' => 'Home', 'url' => '/', 'position' => 0]);
            $primary->items()->create(['type' => MenuItemType::Custom->value, 'label' => 'Articles', 'url' => '/articles', 'position' => 1]);
        }

        $footer = Menu::query()->firstOrCreate(
            ['location' => 'footer'],
            ['name' => 'Footer', 'slug' => 'footer', 'is_active' => true]
        );

        if ($footer->items()->count() === 0) {
            $footer->items()->create(['type' => MenuItemType::Custom->value, 'label' => 'Home', 'url' => '/', 'position' => 0]);
            $footer->items()->create(['type' => MenuItemType::Custom->value, 'label' => 'Articles', 'url' => '/articles', 'position' => 1]);
        }
    }
}
