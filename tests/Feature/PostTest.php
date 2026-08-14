<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_is_generated_from_title_when_blank(): void
    {
        $post = Post::factory()->make(['title' => 'How To Save Money Fast', 'slug' => null]);
        $post->author_id = User::factory()->create()->id;
        $post->save();

        $this->assertSame('how-to-save-money-fast', $post->slug);
    }

    public function test_duplicate_slugs_are_disambiguated(): void
    {
        $author = User::factory()->create();

        $first = Post::factory()->create(['title' => 'Budgeting 101', 'slug' => null, 'author_id' => $author->id]);
        $second = Post::factory()->make(['title' => 'Budgeting 101', 'slug' => null, 'author_id' => $author->id]);
        $second->save();

        $this->assertSame('budgeting-101', $first->slug);
        $this->assertSame('budgeting-101-1', $second->slug);
    }

    public function test_reading_time_is_estimated_from_content(): void
    {
        $words = implode(' ', array_fill(0, 400, 'word'));

        $post = Post::factory()->create([
            'content' => "<p>{$words}</p>",
            'author_id' => User::factory()->create()->id,
        ]);

        // 400 words / 200 wpm = 2 minutes.
        $this->assertSame(2, $post->reading_time_minutes);
    }

    public function test_published_scope_excludes_drafts_scheduled_and_future_dated_posts(): void
    {
        $author = User::factory()->create();

        $published = Post::factory()->published()->create(['author_id' => $author->id]);
        Post::factory()->create(['author_id' => $author->id, 'status' => PostStatus::Draft]);
        Post::factory()->create([
            'author_id' => $author->id,
            'status' => PostStatus::Published,
            'published_at' => now()->addDay(), // future-dated: not yet live
        ]);

        $results = Post::published()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($published));
    }

    public function test_post_belongs_to_category_and_tags(): void
    {
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();
        $post = Post::factory()->create([
            'author_id' => User::factory()->create()->id,
            'category_id' => $category->id,
        ]);
        $post->tags()->attach($tag);

        $this->assertTrue($post->category->is($category));
        $this->assertTrue($post->tags->contains($tag));
    }

    public function test_seo_metadata_can_be_attached_via_morph_relation(): void
    {
        $post = Post::factory()->create(['author_id' => User::factory()->create()->id]);

        $post->seo()->create([
            'seo_title' => 'Custom SEO title',
            'meta_description' => 'Custom description',
        ]);

        $this->assertSame('Custom SEO title', $post->refresh()->seo->seo_title);
    }
}
