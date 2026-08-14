<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PostResource;
use App\Models\ArticleSession;
use App\Models\EngagementEvent;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_analytics_page_renders_with_real_numbers(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $post = Post::factory()->published()->create(['author_id' => $admin->id]);

        ArticleSession::create([
            'post_id' => $post->id, 'session_id' => (string) Str::uuid(),
            'progress_percent' => 80, 'time_spent_seconds' => 120,
            'started_at' => now(), 'last_activity_at' => now(),
        ]);
        EngagementEvent::create([
            'event_type' => 'article_open', 'post_id' => $post->id, 'session_id' => (string) Str::uuid(),
            'event_uuid' => (string) Str::uuid(), 'payload' => [], 'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(PostResource::getUrl('analytics', ['record' => $post]));

        $response->assertOk();
        $response->assertSee('Views');
        $response->assertSee('1'); // one article_open event
    }

    public function test_author_cannot_view_analytics_for_another_authors_post(): void
    {
        $author = User::factory()->create();
        $author->assignRole('author');
        $otherAuthor = User::factory()->create();
        $otherAuthor->assignRole('author');
        $post = Post::factory()->published()->create(['author_id' => $otherAuthor->id]);

        $this->actingAs($author)
            ->get(PostResource::getUrl('analytics', ['record' => $post]))
            ->assertForbidden();
    }
}
