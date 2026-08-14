<?php

namespace Tests\Feature;

use App\Enums\PageStatus;
use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Services\SeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoServiceTest extends TestCase
{
    use RefreshDatabase;

    private SeoService $seo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seo = app(SeoService::class);
    }

    public function test_post_title_falls_back_to_the_post_title_when_no_seo_title_is_set(): void
    {
        $post = Post::factory()->create(['title' => 'My Great Post', 'author_id' => User::factory()->create()->id]);

        $this->assertSame('My Great Post', $this->seo->forPost($post)['title']);
    }

    public function test_post_title_prefers_an_explicit_seo_title_over_the_post_title(): void
    {
        $post = Post::factory()->create(['title' => 'Post Title', 'author_id' => User::factory()->create()->id]);
        $post->seo()->create(['seo_title' => 'Custom SEO Title']);

        $this->assertSame('Custom SEO Title', $this->seo->forPost($post->fresh())['title']);
    }

    public function test_meta_description_falls_back_to_post_excerpt_then_generated_excerpt(): void
    {
        $withExcerpt = Post::factory()->create([
            'excerpt' => 'A hand-written excerpt.',
            'content' => '<p>Body content here.</p>',
            'author_id' => User::factory()->create()->id,
        ]);
        $this->assertSame('A hand-written excerpt.', $this->seo->forPost($withExcerpt)['description']);

        $withoutExcerpt = Post::factory()->create([
            'excerpt' => null,
            'content' => '<p>This is the body of the article used to generate an excerpt.</p>',
            'author_id' => User::factory()->create()->id,
        ]);
        $this->assertStringContainsString('This is the body', $this->seo->forPost($withoutExcerpt)['description']);
    }

    public function test_explicit_meta_description_is_never_overwritten_by_a_fallback(): void
    {
        $post = Post::factory()->create(['excerpt' => 'Fallback excerpt', 'author_id' => User::factory()->create()->id]);
        $post->seo()->create(['meta_description' => 'Explicit description']);

        $this->assertSame('Explicit description', $this->seo->forPost($post->fresh())['description']);
    }

    public function test_canonical_url_defaults_to_the_generated_route_when_not_overridden(): void
    {
        $post = Post::factory()->create(['author_id' => User::factory()->create()->id]);

        $this->assertSame(route('articles.show', $post), $this->seo->forPost($post)['canonical']);
    }

    public function test_canonical_url_uses_the_explicit_override_when_set(): void
    {
        $post = Post::factory()->create(['author_id' => User::factory()->create()->id]);
        $post->seo()->create(['canonical_url' => 'https://example.com/canonical']);

        $this->assertSame('https://example.com/canonical', $this->seo->forPost($post->fresh())['canonical']);
    }

    public function test_og_title_falls_back_to_the_resolved_title(): void
    {
        $post = Post::factory()->create(['title' => 'Fallback Title', 'author_id' => User::factory()->create()->id]);

        $this->assertSame('Fallback Title', $this->seo->forPost($post)['ogTitle']);
    }

    public function test_og_image_is_null_when_no_explicit_image_featured_image_or_site_default_exists(): void
    {
        $post = Post::factory()->create(['author_id' => User::factory()->create()->id]);

        $this->assertNull($this->seo->forPost($post)['ogImage']);
    }

    public function test_og_image_falls_back_to_the_post_featured_image_when_no_explicit_og_image_is_set(): void
    {
        $image = Media::factory()->image()->create();
        $post = Post::factory()->create(['author_id' => User::factory()->create()->id, 'featured_image_media_id' => $image->id]);

        $this->assertSame($image->url, $this->seo->forPost($post->fresh())['ogImage']);
    }

    public function test_explicit_og_image_overrides_the_featured_image_fallback(): void
    {
        $featured = Media::factory()->image()->create();
        $explicit = Media::factory()->image()->create();
        $post = Post::factory()->create(['author_id' => User::factory()->create()->id, 'featured_image_media_id' => $featured->id]);
        $post->seo()->create(['og_image_media_id' => $explicit->id]);

        $this->assertSame($explicit->url, $this->seo->forPost($post->fresh())['ogImage']);
    }

    public function test_twitter_fields_fall_back_to_og_fields_when_blank(): void
    {
        $post = Post::factory()->create(['title' => 'A Title', 'author_id' => User::factory()->create()->id]);
        $post->seo()->create(['og_title' => 'OG Title Only']);

        $resolved = $this->seo->forPost($post->fresh());

        $this->assertSame('OG Title Only', $resolved['twitterTitle']);
    }

    public function test_explicit_twitter_title_overrides_the_og_fallback(): void
    {
        $post = Post::factory()->create(['author_id' => User::factory()->create()->id]);
        $post->seo()->create(['og_title' => 'OG Title', 'twitter_title' => 'Twitter-specific Title']);

        $this->assertSame('Twitter-specific Title', $this->seo->forPost($post->fresh())['twitterTitle']);
    }

    public function test_robots_defaults_to_index_and_follow_when_no_seo_row_exists(): void
    {
        $post = Post::factory()->create(['author_id' => User::factory()->create()->id]);

        $resolved = $this->seo->forPost($post);

        $this->assertTrue($resolved['robotsIndex']);
        $this->assertTrue($resolved['robotsFollow']);
    }

    public function test_robots_respects_an_explicit_noindex(): void
    {
        $post = Post::factory()->create(['author_id' => User::factory()->create()->id]);
        $post->seo()->create(['robots_index' => false, 'robots_follow' => true]);

        $resolved = $this->seo->forPost($post->fresh());

        $this->assertFalse($resolved['robotsIndex']);
        $this->assertTrue($resolved['robotsFollow']);
    }

    public function test_global_seo_default_title_is_used_when_content_has_no_title_candidate(): void
    {
        Setting::query()->create(['group' => 'seo', 'key' => 'default_description', 'value' => 'Global fallback description']);

        $category = Category::factory()->create(['name' => 'Widgets', 'seo_description' => null, 'description' => null]);

        $this->assertSame('Global fallback description', $this->seo->forCategory($category)['description']);
    }

    public function test_page_seo_uses_the_same_fallback_chain_as_posts(): void
    {
        $page = Page::factory()->create(['title' => 'About Us', 'status' => PageStatus::Published, 'published_at' => now()->subDay()]);

        $resolved = $this->seo->forPage($page);

        $this->assertSame('About Us', $resolved['title']);
        $this->assertSame(route('pages.show', $page), $resolved['canonical']);
    }

    public function test_tag_seo_falls_back_to_a_hash_prefixed_name(): void
    {
        $tag = Tag::factory()->create(['name' => 'laravel', 'seo_title' => null]);

        $this->assertSame('#laravel', $this->seo->forTag($tag)['title']);
    }

    public function test_home_seo_includes_website_and_organization_json_ld(): void
    {
        $resolved = $this->seo->forHome();

        $this->assertSame('https://schema.org', $resolved['jsonLd']['@context']);
        $types = collect($resolved['jsonLd']['@graph'])->pluck('@type')->all();
        $this->assertContains('WebSite', $types);
        $this->assertContains('Organization', $types);
    }

    public function test_article_json_ld_includes_a_breadcrumb_list(): void
    {
        $post = Post::factory()->create(['author_id' => User::factory()->create()->id]);

        $nodes = $this->seo->forPost($post)['jsonLd'];

        $types = collect($nodes)->pluck('@type')->all();
        $this->assertContains('Article', $types);
        $this->assertContains('BreadcrumbList', $types);
    }
}
