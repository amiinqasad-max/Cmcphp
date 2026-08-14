<?php

namespace Tests\Feature\Tracking;

use App\Jobs\ProcessEngagementEventBatch;
use App\Models\Post;
use App\Models\PostVideo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class EngagementEventIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_batch_is_accepted_and_dispatches_processing_job(): void
    {
        Queue::fake();

        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);

        $response = $this->postJson('/api/track/events', [
            'events' => [[
                'event_type' => 'article_open',
                'post_id' => $post->id,
                'event_uuid' => (string) Str::uuid(),
                'payload' => ['percent' => 0],
            ]],
        ]);

        $response->assertStatus(202);
        $response->assertCookie(config('cms.anonymous_session_cookie'));
        Queue::assertPushed(ProcessEngagementEventBatch::class);
    }

    public function test_unknown_event_type_is_rejected(): void
    {
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);

        $this->postJson('/api/track/events', [
            'events' => [[
                'event_type' => 'totally_made_up',
                'post_id' => $post->id,
                'event_uuid' => (string) Str::uuid(),
            ]],
        ])->assertStatus(422);
    }

    public function test_video_event_without_post_video_id_is_rejected(): void
    {
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);

        $this->postJson('/api/track/events', [
            'events' => [[
                'event_type' => 'video_play',
                'post_id' => $post->id,
                'event_uuid' => (string) Str::uuid(),
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['events.0.post_video_id']);
    }

    public function test_video_id_belonging_to_a_different_post_is_rejected(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->published()->create(['author_id' => $author->id]);
        $otherPost = Post::factory()->published()->create(['author_id' => $author->id]);
        $video = PostVideo::factory()->create(['post_id' => $otherPost->id, 'position' => 1]);

        $this->postJson('/api/track/events', [
            'events' => [[
                'event_type' => 'video_play',
                'post_id' => $post->id,
                'post_video_id' => $video->id,
                'event_uuid' => (string) Str::uuid(),
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['events.0.post_video_id']);
    }

    public function test_out_of_range_percent_is_rejected(): void
    {
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);

        $this->postJson('/api/track/events', [
            'events' => [[
                'event_type' => 'article_50',
                'post_id' => $post->id,
                'event_uuid' => (string) Str::uuid(),
                'payload' => ['percent' => 250],
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['events.0.payload.percent']);
    }

    public function test_nonexistent_post_id_is_rejected(): void
    {
        $this->postJson('/api/track/events', [
            'events' => [[
                'event_type' => 'article_open',
                'post_id' => (string) Str::uuid(),
                'event_uuid' => (string) Str::uuid(),
            ]],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['events.0.post_id']);
    }

    public function test_end_to_end_batch_updates_article_session_and_video_progress(): void
    {
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);
        $video = PostVideo::factory()->create(['post_id' => $post->id, 'position' => 1, 'duration_seconds' => 10]);

        $response = $this->postJson('/api/track/events', [
            'events' => [
                [
                    'event_type' => 'article_90',
                    'post_id' => $post->id,
                    'event_uuid' => (string) Str::uuid(),
                    'payload' => ['percent' => 95],
                ],
                [
                    'event_type' => 'video_90',
                    'post_id' => $post->id,
                    'post_video_id' => $video->id,
                    'event_uuid' => (string) Str::uuid(),
                    'payload' => ['percent' => 95, 'watched_seconds' => 9.5],
                ],
            ],
        ]);

        $response->assertStatus(202);

        $this->assertDatabaseHas('article_sessions', [
            'post_id' => $post->id,
            'progress_percent' => 95,
            'is_completed' => true,
        ]);

        $this->assertDatabaseHas('video_progress', [
            'post_video_id' => $video->id,
            'max_percent_reached' => 95,
            'is_completed' => true,
        ]);
    }
}
