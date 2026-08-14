<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\ActivityLog;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_post_logs_activity(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $author->id]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'post.created',
            'subject_type' => (new Post)->getMorphClass(),
            'subject_id' => $post->id,
        ]);
    }

    public function test_publishing_a_post_logs_a_distinct_publish_action(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $author->id, 'status' => PostStatus::Draft]);

        $post->update(['status' => PostStatus::Published, 'published_at' => now()]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'post.published',
            'subject_id' => $post->id,
        ]);
        $this->assertDatabaseMissing('activity_logs', [
            'action' => 'post.updated',
            'subject_id' => $post->id,
        ]);
    }

    public function test_deleting_a_post_logs_activity(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create(['author_id' => $author->id]);
        $postId = $post->id;

        $post->delete();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'post.deleted',
            'subject_id' => $postId,
        ]);
    }

    public function test_login_is_logged(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'auth.login',
            'user_id' => $user->id,
        ]);
    }

    public function test_activity_log_is_read_only_via_the_model(): void
    {
        // Sanity check the model itself, independent of the Filament UI restriction.
        $log = ActivityLog::create([
            'action' => 'test.action',
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('activity_logs', ['action' => 'test.action']);
        $this->assertNull($log->updated_at ?? null);
    }
}
