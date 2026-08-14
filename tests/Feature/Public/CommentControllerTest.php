<?php

namespace Tests\Feature\Public;

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_submit_a_comment_pending_approval_by_default(): void
    {
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);

        $response = $this->post(route('comments.store'), [
            'post_id' => $post->id,
            'author_name' => 'Jane Reader',
            'author_email' => 'jane@example.com',
            'body' => 'Great article, thanks for sharing!',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('comments', [
            'post_id' => $post->id,
            'author_name' => 'Jane Reader',
            'status' => CommentStatus::Pending->value,
        ]);
    }

    public function test_authenticated_user_does_not_need_to_supply_name_or_email(): void
    {
        $user = User::factory()->create(['name' => 'Logged In User']);
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);

        $response = $this->actingAs($user)->post(route('comments.store'), [
            'post_id' => $post->id,
            'body' => 'Nice read.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('comments', [
            'post_id' => $post->id,
            'user_id' => $user->id,
            'author_name' => 'Logged In User',
        ]);
    }

    public function test_guest_without_name_or_email_is_rejected(): void
    {
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);

        $this->post(route('comments.store'), [
            'post_id' => $post->id,
            'body' => 'Missing my details.',
        ])->assertSessionHasErrors(['author_name', 'author_email']);
    }

    public function test_cannot_comment_on_a_draft_post(): void
    {
        $post = Post::factory()->create(['author_id' => User::factory()->create()->id]);

        $this->post(route('comments.store'), [
            'post_id' => $post->id,
            'author_name' => 'Jane',
            'author_email' => 'jane@example.com',
            'body' => 'Trying to comment on a draft.',
        ])->assertSessionHasErrors(['post_id']);
    }

    public function test_comments_are_auto_approved_when_moderation_is_disabled(): void
    {
        Setting::create(['group' => 'content', 'key' => 'comments_require_approval', 'value' => false]);
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);

        $this->post(route('comments.store'), [
            'post_id' => $post->id,
            'author_name' => 'Jane',
            'author_email' => 'jane@example.com',
            'body' => 'Auto approved comment.',
        ]);

        $this->assertDatabaseHas('comments', ['status' => CommentStatus::Approved->value]);
    }

    public function test_returns_403_when_comments_are_disabled_site_wide(): void
    {
        Setting::create(['group' => 'content', 'key' => 'comments_enabled', 'value' => false]);
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);

        $this->post(route('comments.store'), [
            'post_id' => $post->id,
            'author_name' => 'Jane',
            'author_email' => 'jane@example.com',
            'body' => 'Should not be allowed.',
        ])->assertForbidden();
    }

    public function test_only_approved_comments_are_shown_on_the_article_page(): void
    {
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);
        $approved = Comment::factory()->approved()->create(['post_id' => $post->id, 'body' => 'Visible comment body']);
        $pending = Comment::factory()->create(['post_id' => $post->id, 'body' => 'Hidden pending comment body']);

        $response = $this->get(route('articles.show', $post));

        $response->assertSee('Visible comment body');
        $response->assertDontSee('Hidden pending comment body');
    }
}
