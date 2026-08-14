<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\AdEvent;
use App\Models\AdSlot;
use App\Models\EngagementEvent;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchedulerCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_scheduled_posts_publishes_due_posts_only(): void
    {
        $author = User::factory()->create();
        $due = Post::factory()->create([
            'author_id' => $author->id,
            'status' => PostStatus::Scheduled,
            'published_at' => now()->subMinute(),
        ]);
        $notYetDue = Post::factory()->create([
            'author_id' => $author->id,
            'status' => PostStatus::Scheduled,
            'published_at' => now()->addHour(),
        ]);

        $this->artisan('posts:publish-scheduled')->assertSuccessful();

        $this->assertSame(PostStatus::Published, $due->fresh()->status);
        $this->assertSame(PostStatus::Scheduled, $notYetDue->fresh()->status);
    }

    public function test_publishing_a_scheduled_post_logs_activity(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create([
            'author_id' => $author->id,
            'status' => PostStatus::Scheduled,
            'published_at' => now()->subMinute(),
        ]);

        $this->artisan('posts:publish-scheduled');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'post.published',
            'subject_id' => $post->id,
        ]);
    }

    public function test_tracking_prune_deletes_only_events_older_than_the_retention_window(): void
    {
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);
        $slot = AdSlot::factory()->create();

        $old = EngagementEvent::create([
            'event_type' => 'article_open', 'post_id' => $post->id, 'session_id' => (string) Str::uuid(),
            'event_uuid' => (string) Str::uuid(), 'payload' => [], 'created_at' => now()->subDays(200),
        ]);
        $recent = EngagementEvent::create([
            'event_type' => 'article_open', 'post_id' => $post->id, 'session_id' => (string) Str::uuid(),
            'event_uuid' => (string) Str::uuid(), 'payload' => [], 'created_at' => now()->subDays(5),
        ]);
        $oldAd = AdEvent::create([
            'ad_slot_id' => $slot->id, 'post_id' => $post->id, 'session_id' => (string) Str::uuid(),
            'event_type' => 'ad_requested', 'event_uuid' => (string) Str::uuid(), 'created_at' => now()->subDays(200),
        ]);

        $this->artisan('tracking:prune', ['--days' => 90])->assertSuccessful();

        $this->assertDatabaseMissing('engagement_events', ['id' => $old->id]);
        $this->assertDatabaseHas('engagement_events', ['id' => $recent->id]);
        $this->assertDatabaseMissing('ad_events', ['id' => $oldAd->id]);
    }
}
