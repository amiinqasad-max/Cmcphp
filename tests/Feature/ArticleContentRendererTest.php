<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Post;
use App\Models\PostVideo;
use App\Models\User;
use App\Services\ArticleContentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleContentRendererTest extends TestCase
{
    use RefreshDatabase;

    private function renderer(): ArticleContentRenderer
    {
        return app(ArticleContentRenderer::class);
    }

    public function test_video_markers_are_replaced_with_the_matching_video_block(): void
    {
        $post = Post::factory()->create([
            'author_id' => User::factory()->create()->id,
            'content' => '<p>Intro.</p><p>[[VIDEO_1]]</p><p>Middle.</p><p>[[VIDEO_2]]</p><p>Outro.</p>',
        ]);

        $video1 = PostVideo::factory()->create(['post_id' => $post->id, 'position' => 1]);
        $video2 = PostVideo::factory()->create(['post_id' => $post->id, 'position' => 2]);

        $blocks = $this->renderer()->blocks($post->fresh()->load('videos.media'))->values();

        $this->assertSame('content', $blocks[0]['type']);
        $this->assertSame('video', $blocks[1]['type']);
        $this->assertTrue($blocks[1]['video']->is($video1));
        $this->assertSame('content', $blocks[2]['type']);
        $this->assertSame('video', $blocks[3]['type']);
        $this->assertTrue($blocks[3]['video']->is($video2));
        $this->assertSame('content', $blocks[4]['type']);
    }

    public function test_paragraphs_are_flagged_but_headings_and_videos_are_not(): void
    {
        $post = Post::factory()->create([
            'author_id' => User::factory()->create()->id,
            'content' => '<h2>Heading</h2><p>Paragraph one.</p><p>[[VIDEO_1]]</p>',
        ]);

        PostVideo::factory()->create(['post_id' => $post->id, 'position' => 1]);

        $blocks = $this->renderer()->blocks($post->fresh()->load('videos.media'))->values();

        $this->assertFalse($blocks[0]['isParagraph']); // heading
        $this->assertTrue($blocks[1]['isParagraph']);  // paragraph
        $this->assertFalse($blocks[2]['isParagraph']); // video
    }

    public function test_marker_for_missing_video_is_dropped_silently(): void
    {
        $post = Post::factory()->create([
            'author_id' => User::factory()->create()->id,
            'content' => '<p>[[VIDEO_3]]</p>',
        ]);

        $blocks = $this->renderer()->blocks($post->fresh()->load('videos.media'))->values();

        $this->assertSame('content', $blocks[0]['type']);
        $this->assertSame('', $blocks[0]['html']);
    }

    public function test_video_duration_is_copied_from_media_when_not_set_explicitly(): void
    {
        $post = Post::factory()->create(['author_id' => User::factory()->create()->id]);
        $media = Media::factory()->video(durationSeconds: 123)->create();

        $video = PostVideo::create([
            'post_id' => $post->id,
            'media_id' => $media->id,
            'position' => 1,
        ]);

        $this->assertSame(123, $video->duration_seconds);
    }
}
