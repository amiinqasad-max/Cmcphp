<?php

namespace Database\Factories;

use App\Enums\VideoStatus;
use App\Models\Media;
use App\Models\Post;
use App\Models\PostVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostVideo>
 */
class PostVideoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'media_id' => Media::factory()->video(),
            'position' => 1,
            'duration_seconds' => fake()->numberBetween(10, 300),
            'is_required' => true,
            'completion_threshold' => 90,
            'status' => VideoStatus::Active,
        ];
    }
}
