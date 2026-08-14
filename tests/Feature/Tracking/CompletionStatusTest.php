<?php

namespace Tests\Feature\Tracking;

use App\Models\ArticleCompletion;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompletionStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_not_completed_when_no_record_exists(): void
    {
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);

        $this->getJson("/api/track/completion?post_id={$post->id}")
            ->assertOk()
            ->assertJson(['completed' => false]);
    }

    public function test_returns_completed_with_next_article_once_recorded(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->published()->create(['author_id' => $author->id]);
        $nextPost = Post::factory()->published()->create(['author_id' => $author->id]);

        // Let the middleware issue and encrypt the session cookie itself,
        // then round-trip its decrypted value back on the second request —
        // hand-crafting an encrypted cookie value bypasses how the app
        // actually reads/writes it.
        $cookieName = config('cms.anonymous_session_cookie');

        $first = $this->getJson("/api/track/completion?post_id={$post->id}");
        $first->assertOk()->assertJson(['completed' => false]);
        $sessionId = $first->getCookie($cookieName)->getValue();

        ArticleCompletion::create([
            'post_id' => $post->id,
            'session_id' => $sessionId,
            'reading_progress_percent' => 95,
            'videos_completed_count' => 0,
            'videos_required_count' => 0,
            'completed_at' => now(),
            'next_post_id' => $nextPost->id,
        ]);

        // getJson() doesn't send cookies unless credentials are explicitly
        // opted in (it mirrors browser fetch()'s same default) — the real
        // client-side tracker JS always sets `credentials: 'same-origin'`.
        $response = $this->withCredentials()
            ->withCookie($cookieName, $sessionId)
            ->getJson("/api/track/completion?post_id={$post->id}");

        $response->assertOk()
            ->assertJson([
                'completed' => true,
                'next_article' => ['title' => $nextPost->title],
            ]);
    }

    public function test_rejects_invalid_post_id(): void
    {
        $this->getJson('/api/track/completion?post_id=not-a-uuid')
            ->assertStatus(422);
    }
}
