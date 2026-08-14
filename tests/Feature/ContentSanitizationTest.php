<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §34 defense-in-depth: content is sanitized server-side on every save,
 * independent of the RichEditor's own client-side restrictions — a direct
 * POST, or a lower-trust "author" account, bypasses those entirely.
 */
class ContentSanitizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_script_tags_are_stripped_from_post_content(): void
    {
        $post = Post::factory()->create([
            'author_id' => User::factory()->create()->id,
            'content' => '<p>Hello <script>alert(document.cookie)</script>world</p>',
        ]);

        $this->assertStringNotContainsString('<script', $post->content);
        $this->assertStringContainsString('Hello', $post->content);
        $this->assertStringContainsString('world', $post->content);
    }

    public function test_inline_event_handlers_are_stripped(): void
    {
        $post = Post::factory()->create([
            'author_id' => User::factory()->create()->id,
            'content' => '<img src="x" onerror="alert(1)">',
        ]);

        $this->assertStringNotContainsString('onerror', $post->content);
    }

    public function test_javascript_urls_are_stripped_from_links(): void
    {
        $post = Post::factory()->create([
            'author_id' => User::factory()->create()->id,
            'content' => '<a href="javascript:alert(1)">click me</a>',
        ]);

        $this->assertStringNotContainsString('javascript:', $post->content);
    }

    public function test_legitimate_rich_content_survives_sanitization(): void
    {
        $content = '<h2>Heading</h2><p>Paragraph with <strong>bold</strong> and a <a href="https://example.com">link</a>.</p>'
            .'<ul><li>Item</li></ul><blockquote>Quote</blockquote>';

        $post = Post::factory()->create(['author_id' => User::factory()->create()->id, 'content' => $content]);

        $this->assertStringContainsString('<h2>Heading</h2>', $post->content);
        $this->assertStringContainsString('<strong>bold</strong>', $post->content);
        $this->assertStringContainsString('href="https://example.com"', $post->content);
        $this->assertStringContainsString('<blockquote>Quote</blockquote>', $post->content);
    }

    public function test_video_marker_tokens_survive_sanitization(): void
    {
        $post = Post::factory()->create([
            'author_id' => User::factory()->create()->id,
            'content' => '<p>Intro.</p><p>[[VIDEO_1]]</p><p>Outro.</p>',
        ]);

        $this->assertStringContainsString('[[VIDEO_1]]', $post->content);
    }

    public function test_youtube_embed_iframe_is_preserved(): void
    {
        $post = Post::factory()->create([
            'author_id' => User::factory()->create()->id,
            'content' => '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" allowfullscreen></iframe>',
        ]);

        $this->assertStringContainsString('youtube.com/embed', $post->content);
    }

    public function test_arbitrary_iframe_sources_are_stripped(): void
    {
        $post = Post::factory()->create([
            'author_id' => User::factory()->create()->id,
            'content' => '<iframe src="https://evil.example.com/phishing"></iframe>',
        ]);

        $this->assertStringNotContainsString('evil.example.com', $post->content);
    }

    public function test_page_content_is_also_sanitized(): void
    {
        $page = Page::factory()->create(['content' => '<p>Hi</p><script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script', $page->content);
    }
}
