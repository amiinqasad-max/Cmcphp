<?php

namespace Tests\Feature;

use App\Models\AdEvent;
use App\Models\AdSlot;
use App\Models\ArticleCompletion;
use App\Models\ArticleSession;
use App\Models\DailyRollup;
use App\Models\EngagementEvent;
use App\Models\Post;
use App\Models\PostVideo;
use App\Models\User;
use App\Models\VideoProgress;
use App\Services\AnalyticsAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsAggregationTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AnalyticsAggregationService
    {
        return app(AnalyticsAggregationService::class);
    }

    public function test_aggregates_pageviews_sessions_and_completions_for_a_post(): void
    {
        $date = Carbon::parse('2026-01-15');
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);

        // Two reading sessions that day.
        ArticleSession::create([
            'post_id' => $post->id, 'session_id' => (string) Str::uuid(),
            'progress_percent' => 80, 'time_spent_seconds' => 100,
            'started_at' => $date->copy()->addHours(2), 'last_activity_at' => $date->copy()->addHours(2),
        ]);
        ArticleSession::create([
            'post_id' => $post->id, 'session_id' => (string) Str::uuid(),
            'progress_percent' => 100, 'time_spent_seconds' => 200, 'is_completed' => true,
            'started_at' => $date->copy()->addHours(3), 'last_activity_at' => $date->copy()->addHours(3),
        ]);

        // One article_open raw event (pageview) that day.
        EngagementEvent::create([
            'event_type' => 'article_open', 'post_id' => $post->id, 'session_id' => (string) Str::uuid(),
            'event_uuid' => (string) Str::uuid(), 'payload' => [], 'created_at' => $date->copy()->addHour(),
        ]);

        // One completion that day.
        ArticleCompletion::create([
            'post_id' => $post->id, 'session_id' => (string) Str::uuid(),
            'reading_progress_percent' => 95, 'videos_completed_count' => 0, 'videos_required_count' => 0,
            'completed_at' => $date->copy()->addHours(4),
        ]);

        // Activity on a different day should NOT be counted.
        ArticleSession::create([
            'post_id' => $post->id, 'session_id' => (string) Str::uuid(),
            'progress_percent' => 10, 'time_spent_seconds' => 5,
            'started_at' => $date->copy()->addDay(), 'last_activity_at' => $date->copy()->addDay(),
        ]);

        $this->service()->aggregateForDate($date);

        $rollup = DailyRollup::where('date', $date->toDateString())->where('post_id', $post->id)->first();

        $this->assertNotNull($rollup);
        $this->assertSame(1, $rollup->pageviews);
        $this->assertSame(2, $rollup->sessions_count);
        $this->assertSame(90, $rollup->avg_progress_percent); // (80+100)/2
        $this->assertSame(1, $rollup->completions_count);
    }

    public function test_aggregates_video_and_ad_metrics(): void
    {
        $date = Carbon::parse('2026-01-15');
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);
        $video = PostVideo::factory()->create(['post_id' => $post->id, 'position' => 1]);
        $slot = AdSlot::factory()->create();

        VideoProgress::create([
            'post_id' => $post->id, 'post_video_id' => $video->id, 'session_id' => (string) Str::uuid(),
            'is_completed' => true, 'max_percent_reached' => 95, 'watched_seconds' => 9,
            'started_at' => $date->copy()->addHour(), 'last_watched_at' => $date->copy()->addHour(),
            'completed_at' => $date->copy()->addHour(),
        ]);

        EngagementEvent::create([
            'event_type' => 'video_play', 'post_id' => $post->id, 'post_video_id' => $video->id,
            'session_id' => (string) Str::uuid(), 'event_uuid' => (string) Str::uuid(),
            'payload' => [], 'created_at' => $date->copy()->addHour(),
        ]);

        AdEvent::create([
            'ad_slot_id' => $slot->id, 'post_id' => $post->id, 'session_id' => (string) Str::uuid(),
            'event_type' => 'ad_requested', 'event_uuid' => (string) Str::uuid(), 'created_at' => $date->copy()->addHour(),
        ]);
        AdEvent::create([
            'ad_slot_id' => $slot->id, 'post_id' => $post->id, 'session_id' => (string) Str::uuid(),
            'event_type' => 'ad_rendered', 'event_uuid' => (string) Str::uuid(), 'created_at' => $date->copy()->addHour(),
        ]);

        // Need at least one article_session so the post is counted as "affected" that day.
        ArticleSession::create([
            'post_id' => $post->id, 'session_id' => (string) Str::uuid(),
            'progress_percent' => 50, 'time_spent_seconds' => 30,
            'started_at' => $date->copy()->addHour(), 'last_activity_at' => $date->copy()->addHour(),
        ]);

        $this->service()->aggregateForDate($date);

        $rollup = DailyRollup::where('date', $date->toDateString())->where('post_id', $post->id)->first();

        $this->assertSame(1, $rollup->video_plays);
        $this->assertSame(1, $rollup->video_completions);
        $this->assertSame(1, $rollup->ad_requests);
        $this->assertSame(1, $rollup->ad_renders);
    }

    public function test_creates_a_site_wide_rollup_row_summing_all_posts(): void
    {
        $date = Carbon::parse('2026-01-15');
        $author = User::factory()->create();
        $postA = Post::factory()->published()->create(['author_id' => $author->id]);
        $postB = Post::factory()->published()->create(['author_id' => $author->id]);

        foreach ([$postA, $postB] as $post) {
            ArticleSession::create([
                'post_id' => $post->id, 'session_id' => (string) Str::uuid(),
                'progress_percent' => 70, 'time_spent_seconds' => 60,
                'started_at' => $date->copy()->addHour(), 'last_activity_at' => $date->copy()->addHour(),
            ]);
        }

        $this->service()->aggregateForDate($date);

        $siteWide = DailyRollup::where('date', $date->toDateString())->whereNull('post_id')->first();

        $this->assertNotNull($siteWide);
        $this->assertSame(2, $siteWide->sessions_count);
        $this->assertSame(2, $siteWide->visitors_count);
    }

    public function test_rerunning_for_the_same_date_replaces_rather_than_duplicates(): void
    {
        $date = Carbon::parse('2026-01-15');
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);
        ArticleSession::create([
            'post_id' => $post->id, 'session_id' => (string) Str::uuid(),
            'progress_percent' => 50, 'time_spent_seconds' => 30,
            'started_at' => $date->copy()->addHour(), 'last_activity_at' => $date->copy()->addHour(),
        ]);

        $this->service()->aggregateForDate($date);
        $this->service()->aggregateForDate($date);

        $this->assertSame(2, DailyRollup::where('date', $date->toDateString())->count()); // 1 post + 1 site-wide
    }
}
