<?php

namespace Database\Factories;

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'author_name' => fake()->name(),
            'author_email' => fake()->safeEmail(),
            'body' => fake()->paragraph(),
            'status' => CommentStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => CommentStatus::Approved]);
    }
}
