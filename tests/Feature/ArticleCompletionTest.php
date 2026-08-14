<?php

namespace Tests\Feature;

use App\Enums\NextArticleMode;
use App\Enums\PostStatus;
use App\Models\ArticleCompletion;
use App\Models\ArticleSession;
use App\Models\Post;
use App\Models\PostVideo;
use App\Models\User;
use App\Models\VideoProgress;
use App\Services\ArticleCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The critical test from docs/ARCHITECTURE.md §51:
 *
 *   IF article_progress >= 90 AND video1..N completed THEN
 *     article_completed = true AND next article opens.
 *   IF any required video is incomplete THEN
 *     article_completed = false AND next article must NOT open.
 */
class ArticleCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ArticleCompletionService
    {
        return app(ArticleCompletionService::class);
    }

    private function articleSession(Post $post, string $sessionId, int $percent, bool $completed): ArticleSession
    {
        return ArticleSession::create([
            'post_id' => $post->id,
            'session_id' => $sessionId,
            'progress_percent' => $percent,
            'is_completed' => $completed,
            'started_at' => now(),
            'last_activity_at' => now(),
            'completed_at' => $completed ? now() : null,
        ]);
    }

    private function videoProgress(PostVideo $video, string $sessionId, bool $completed): VideoProgress
    {
        return VideoProgress::create([
            'post_id' => $video->post_id,
            'post_video_id' => $video->id,
            'session_id' => $sessionId,
            'max_percent_reached' => $completed ? 95 : 40,
            'watched_seconds' => 5,
            'is_completed' => $completed,
            'started_at' => now(),
            'last_watched_at' => now(),
            'completed_at' => $completed ? now() : null,
        ]);
    }

    public function test_article_completes_when_reading_and_all_three_required_videos_are_done(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->published()->create(['author_id' => $author->id]);
        $videos = PostVideo::factory()->count(3)->sequence(
            ['position' => 1], ['position' => 2], ['position' => 3],
        )->create(['post_id' => $post->id, 'is_required' => true]);

        $sessionId = (string) Str::uuid();
        $this->articleSession($post, $sessionId, 95, true);
        $videos->each(fn (PostVideo $v) => $this->videoProgress($v, $sessionId, true));

        $completion = $this->service()->evaluate($post->fresh(), $sessionId, null);

        $this->assertNotNull($completion);
        $this->assertSame(3, $completion->videos_completed_count);
        $this->assertSame(3, $completion->videos_required_count);
        $this->assertDatabaseHas('article_completions', [
            'post_id' => $post->id,
            'session_id' => $sessionId,
        ]);
    }

    public function test_article_does_not_complete_when_any_required_video_is_incomplete(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->published()->create(['author_id' => $author->id]);
        $videos = PostVideo::factory()->count(3)->sequence(
            ['position' => 1], ['position' => 2], ['position' => 3],
        )->create(['post_id' => $post->id, 'is_required' => true]);

        $sessionId = (string) Str::uuid();
        $this->articleSession($post, $sessionId, 95, true);

        // Video 1 and 2 completed, video 3 is not.
        $this->videoProgress($videos[0], $sessionId, true);
        $this->videoProgress($videos[1], $sessionId, true);
        $this->videoProgress($videos[2], $sessionId, false);

        $completion = $this->service()->evaluate($post->fresh(), $sessionId, null);

        $this->assertNull($completion);
        $this->assertDatabaseMissing('article_completions', [
            'post_id' => $post->id,
            'session_id' => $sessionId,
        ]);
    }

    public function test_article_does_not_complete_when_reading_progress_is_below_threshold(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->published()->create(['author_id' => $author->id]);
        $video = PostVideo::factory()->create(['post_id' => $post->id, 'position' => 1, 'is_required' => true]);

        $sessionId = (string) Str::uuid();
        $this->articleSession($post, $sessionId, 60, false); // below default 90% threshold
        $this->videoProgress($video, $sessionId, true);

        $completion = $this->service()->evaluate($post->fresh(), $sessionId, null);

        $this->assertNull($completion);
    }

    public function test_article_with_no_videos_completes_on_reading_progress_alone(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->published()->create(['author_id' => $author->id]);

        $sessionId = (string) Str::uuid();
        $this->articleSession($post, $sessionId, 95, true);

        $completion = $this->service()->evaluate($post->fresh(), $sessionId, null);

        $this->assertNotNull($completion);
        $this->assertSame(0, $completion->videos_required_count);
    }

    public function test_completion_is_idempotent_and_does_not_duplicate(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->published()->create(['author_id' => $author->id]);
        $sessionId = (string) Str::uuid();
        $this->articleSession($post, $sessionId, 95, true);

        $first = $this->service()->evaluate($post->fresh(), $sessionId, null);
        $second = $this->service()->evaluate($post->fresh(), $sessionId, null);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, ArticleCompletion::count());
    }

    public function test_next_article_opens_automatically_only_after_completion(): void
    {
        $author = User::factory()->create();
        $current = Post::factory()->published()->create([
            'author_id' => $author->id,
            'next_article_mode' => NextArticleMode::Auto,
            'published_at' => now()->subDay(),
        ]);
        $nextPost = Post::factory()->published()->create([
            'author_id' => $author->id,
            'published_at' => now()->subDays(2),
        ]);
        $video = PostVideo::factory()->create(['post_id' => $current->id, 'position' => 1, 'is_required' => true]);
        $sessionId = (string) Str::uuid();

        // Incomplete: video not done yet — no next-article resolution should happen.
        $this->articleSession($current, $sessionId, 95, true);
        $this->videoProgress($video, $sessionId, false);
        $this->assertNull($this->service()->evaluate($current->fresh(), $sessionId, null));

        // Now complete the video — completion should fire and resolve next article.
        VideoProgress::where('session_id', $sessionId)->update(['is_completed' => true, 'max_percent_reached' => 95]);
        $completion = $this->service()->evaluate($current->fresh(), $sessionId, null);

        $this->assertNotNull($completion);
        $this->assertTrue($completion->nextPost->is($nextPost));
    }

    public function test_manual_next_article_mode_is_respected(): void
    {
        $author = User::factory()->create();
        $manualTarget = Post::factory()->published()->create(['author_id' => $author->id]);
        $post = Post::factory()->published()->create([
            'author_id' => $author->id,
            'next_article_mode' => NextArticleMode::Manual,
            'next_article_id' => $manualTarget->id,
        ]);

        $sessionId = (string) Str::uuid();
        $this->articleSession($post, $sessionId, 95, true);

        $completion = $this->service()->evaluate($post->fresh(), $sessionId, null);

        $this->assertTrue($completion->nextPost->is($manualTarget));
    }

    public function test_next_article_never_resolves_to_a_draft_or_unpublished_post(): void
    {
        $author = User::factory()->create();
        $draft = Post::factory()->create(['author_id' => $author->id, 'status' => PostStatus::Draft]);
        $post = Post::factory()->published()->create([
            'author_id' => $author->id,
            'next_article_mode' => NextArticleMode::Manual,
            'next_article_id' => $draft->id,
        ]);

        $sessionId = (string) Str::uuid();
        $this->articleSession($post, $sessionId, 95, true);

        $completion = $this->service()->evaluate($post->fresh(), $sessionId, null);

        $this->assertNotNull($completion);
        $this->assertNull($completion->next_post_id);
    }
}
