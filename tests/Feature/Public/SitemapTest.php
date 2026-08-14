<?php

namespace Tests\Feature\Public;

use App\Enums\PageStatus;
use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_route_returns_valid_xml(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertStringContainsString('<urlset', $response->getContent());
    }

    public function test_published_post_appears_in_the_sitemap(): void
    {
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);

        $response = $this->get('/sitemap.xml');

        $response->assertSee(route('articles.show', $post), false);
    }

    public function test_draft_post_does_not_appear_in_the_sitemap(): void
    {
        $post = Post::factory()->create(['status' => PostStatus::Draft, 'author_id' => User::factory()->create()->id]);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee(route('articles.show', $post), false);
    }

    public function test_scheduled_post_not_yet_due_does_not_appear_in_the_sitemap(): void
    {
        $post = Post::factory()->scheduled()->create(['author_id' => User::factory()->create()->id]);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee(route('articles.show', $post), false);
    }

    public function test_noindex_post_does_not_appear_in_the_sitemap(): void
    {
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);
        $post->seo()->create(['robots_index' => false]);

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee(route('articles.show', $post), false);
    }

    public function test_soft_deleted_post_does_not_appear_in_the_sitemap(): void
    {
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);
        $url = route('articles.show', $post);
        $post->delete();

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee($url, false);
    }

    public function test_published_page_appears_in_the_sitemap(): void
    {
        $page = Page::factory()->create(['status' => PageStatus::Published, 'published_at' => now()->subDay()]);

        $response = $this->get('/sitemap.xml');

        $response->assertSee(route('pages.show', $page), false);
    }

    public function test_category_with_a_published_post_appears_in_the_sitemap(): void
    {
        $category = Category::factory()->create();
        Post::factory()->published()->create(['author_id' => User::factory()->create()->id, 'category_id' => $category->id]);

        $response = $this->get('/sitemap.xml');

        $response->assertSee(route('categories.show', $category), false);
    }

    public function test_category_with_no_published_posts_is_excluded_from_the_sitemap(): void
    {
        $category = Category::factory()->create();

        $response = $this->get('/sitemap.xml');

        $response->assertDontSee(route('categories.show', $category), false);
    }

    public function test_sitemap_entries_include_a_last_modification_date(): void
    {
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);

        $response = $this->get('/sitemap.xml');

        $response->assertSee('<lastmod>'.$post->updated_at->toAtomString().'</lastmod>', false);
    }

    public function test_homepage_is_always_included(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertSee(route('home'), false);
    }
}
