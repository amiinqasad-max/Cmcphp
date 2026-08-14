<?php

namespace Database\Factories;

use App\Enums\MediaType;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(MediaType::cases());
        $extension = match ($type) {
            MediaType::Image => 'jpg',
            MediaType::Video => 'mp4',
            MediaType::Document => 'pdf',
        };

        $path = 'media/'.fake()->uuid().'.'.$extension;

        return [
            'disk' => 'public',
            'path' => $path,
            'type' => $type,
            'mime_type' => match ($type) {
                MediaType::Image => 'image/jpeg',
                MediaType::Video => 'video/mp4',
                MediaType::Document => 'application/pdf',
            },
            'size_bytes' => fake()->numberBetween(10_000, 20_000_000),
            'title' => fake()->sentence(3),
            'alt_text' => $type === MediaType::Image ? fake()->sentence(4) : null,
            'caption' => fake()->optional()->sentence(),
            'description' => fake()->optional()->paragraph(),
            'duration_seconds' => $type === MediaType::Video ? fake()->numberBetween(5, 300) : null,
            'width' => $type === MediaType::Image ? fake()->numberBetween(600, 2400) : null,
            'height' => $type === MediaType::Image ? fake()->numberBetween(400, 1600) : null,
        ];
    }

    public function image(): static
    {
        return $this->state(fn () => [
            'type' => MediaType::Image,
            'mime_type' => 'image/jpeg',
            'path' => 'media/'.fake()->uuid().'.jpg',
            'width' => fake()->numberBetween(600, 2400),
            'height' => fake()->numberBetween(400, 1600),
            'duration_seconds' => null,
        ]);
    }

    public function video(?int $durationSeconds = null): static
    {
        return $this->state(fn () => [
            'type' => MediaType::Video,
            'mime_type' => 'video/mp4',
            'path' => 'media/'.fake()->uuid().'.mp4',
            'duration_seconds' => $durationSeconds ?? fake()->numberBetween(5, 300),
            'width' => null,
            'height' => null,
        ]);
    }
}
