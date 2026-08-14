<?php

namespace Database\Factories;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'status' => PageStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PageStatus::Published,
            'published_at' => now(),
        ]);
    }
}
