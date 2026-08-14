<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);
        $paragraphs = collect(fake()->paragraphs(6))
            ->map(fn (string $p) => "<p>{$p}</p>")
            ->implode('');

        return [
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(20),
            'content' => $paragraphs,
            'author_id' => User::factory(),
            'status' => PostStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Published,
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Scheduled,
            'published_at' => fake()->dateTimeBetween('now', '+1 month'),
        ]);
    }
}
