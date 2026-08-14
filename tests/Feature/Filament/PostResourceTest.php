<?php

namespace Tests\Feature\Filament;

use App\Enums\PostStatus;
use App\Filament\Resources\PostResource;
use App\Filament\Resources\PostResource\Pages\CreatePost;
use App\Filament\Resources\PostResource\Pages\ListPosts;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PostResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_super_admin_can_see_posts_in_the_table(): void
    {
        $admin = $this->userWithRole('super_admin');
        $posts = Post::factory()->count(3)->create(['author_id' => $admin->id]);

        $this->actingAs($admin);

        Livewire::test(ListPosts::class)->assertCanSeeTableRecords($posts);
    }

    public function test_user_without_a_cms_role_cannot_access_the_admin_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(PostResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_author_can_edit_their_own_post(): void
    {
        $author = $this->userWithRole('author');
        $post = Post::factory()->create(['author_id' => $author->id]);

        $this->actingAs($author)
            ->get(PostResource::getUrl('edit', ['record' => $post]))
            ->assertOk();
    }

    public function test_author_cannot_edit_another_authors_post(): void
    {
        $author = $this->userWithRole('author');
        $otherAuthor = $this->userWithRole('author');
        $post = Post::factory()->create(['author_id' => $otherAuthor->id]);

        $this->actingAs($author)
            ->get(PostResource::getUrl('edit', ['record' => $post]))
            ->assertForbidden();
    }

    public function test_editor_can_edit_any_authors_post(): void
    {
        $editor = $this->userWithRole('editor');
        $author = $this->userWithRole('author');
        $post = Post::factory()->create(['author_id' => $author->id]);

        $this->actingAs($editor)
            ->get(PostResource::getUrl('edit', ['record' => $post]))
            ->assertOk();
    }

    public function test_post_can_be_created_through_the_form_with_seo_metadata(): void
    {
        $editor = $this->userWithRole('editor');
        $this->actingAs($editor);

        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'How to Save Money',
                'slug' => 'how-to-save-money',
                'excerpt' => 'Practical tips.',
                'content' => '<p>Practical tips for saving money.</p>',
                'status' => PostStatus::Draft->value,
                'author_id' => $editor->id,
                'seo' => [
                    'seo_title' => 'How to Save Money — Tips',
                    'meta_description' => 'Practical, no-nonsense savings tips.',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::where('slug', 'how-to-save-money')->firstOrFail();

        $this->assertSame('How to Save Money', $post->title);
        $this->assertSame('How to Save Money — Tips', $post->seo->seo_title);
    }

    public function test_post_can_be_created_with_a_video_attached_via_the_repeater(): void
    {
        $editor = $this->userWithRole('editor');
        $this->actingAs($editor);
        $video = Media::factory()->video(durationSeconds: 42)->create();

        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'Video Article',
                'slug' => 'video-article',
                'content' => '<p>Intro.</p><p>[[VIDEO_1]]</p>',
                'status' => PostStatus::Draft->value,
                'author_id' => $editor->id,
                'videos' => [
                    'a' => [
                        'media_id' => $video->id,
                        'is_required' => true,
                        'completion_threshold' => 90,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::where('slug', 'video-article')->firstOrFail();

        $this->assertCount(1, $post->videos);
        $this->assertSame(1, $post->videos->first()->position);
        $this->assertSame(42, $post->videos->first()->duration_seconds);
    }
}
