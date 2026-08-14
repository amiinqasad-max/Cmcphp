<?php

namespace Tests\Feature;

use App\Enums\AdPlacementType;
use App\Models\AdPlacement;
use App\Models\AdSlot;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostVideo;
use App\Models\Setting;
use App\Models\User;
use App\Services\AdPlacementResolver;
use App\Services\ArticleContentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdPlacementResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): AdPlacementResolver
    {
        return app(AdPlacementResolver::class);
    }

    private function paragraphs(int $count): string
    {
        return collect(range(1, $count))->map(fn ($i) => "<p>Paragraph {$i}.</p>")->implode('');
    }

    private function blocksFor(Post $post): Collection
    {
        return app(ArticleContentRenderer::class)->blocks($post->load('videos.media'));
    }

    public function test_manual_top_placement_is_inserted_before_all_content(): void
    {
        $post = Post::factory()->published()->create([
            'author_id' => User::factory()->create()->id,
            'content' => $this->paragraphs(6),
        ]);
        $slot = AdSlot::factory()->create();
        AdPlacement::factory()->for($post)->for($slot, 'adSlot')->create(['placement_type' => AdPlacementType::Top]);

        $result = $this->resolver()->interleave($post, $this->blocksFor($post));

        $this->assertSame('ad', $result->first()['type']);
    }

    public function test_manual_after_paragraph_placement_lands_after_the_correct_paragraph(): void
    {
        $post = Post::factory()->published()->create([
            'author_id' => User::factory()->create()->id,
            'content' => $this->paragraphs(6),
        ]);
        $slot = AdSlot::factory()->create();
        AdPlacement::factory()->for($post)->for($slot, 'adSlot')->afterParagraph(3)->create();

        $result = $this->resolver()->interleave($post, $this->blocksFor($post))->values();

        $adIndex = $result->search(fn ($b) => $b['type'] === 'ad');
        // The 3 paragraphs before the ad, in order.
        $this->assertStringContainsString('Paragraph 1.', $result[$adIndex - 3]['html']);
        $this->assertStringContainsString('Paragraph 2.', $result[$adIndex - 2]['html']);
        $this->assertStringContainsString('Paragraph 3.', $result[$adIndex - 1]['html']);
        $this->assertStringContainsString('Paragraph 4.', $result[$adIndex + 1]['html']);
    }

    public function test_before_and_after_video_placements_bracket_the_video_block(): void
    {
        $post = Post::factory()->published()->create([
            'author_id' => User::factory()->create()->id,
            'content' => '<p>Intro.</p><p>[[VIDEO_1]]</p><p>Outro.</p>'.$this->paragraphs(4),
        ]);
        PostVideo::factory()->create(['post_id' => $post->id, 'position' => 1]);
        $beforeSlot = AdSlot::factory()->create(['name' => 'before']);
        $afterSlot = AdSlot::factory()->create(['name' => 'after']);
        AdPlacement::factory()->for($post)->for($beforeSlot, 'adSlot')->create([
            'placement_type' => AdPlacementType::BeforeVideo,
            'video_position' => 1,
            'position_order' => 1,
        ]);
        AdPlacement::factory()->for($post)->for($afterSlot, 'adSlot')->create([
            'placement_type' => AdPlacementType::AfterVideo,
            'video_position' => 1,
            'position_order' => 2,
        ]);

        $result = $this->resolver()->interleave($post, $this->blocksFor($post))->values();
        $videoIndex = $result->search(fn ($b) => $b['type'] === 'video');

        $this->assertSame('ad', $result[$videoIndex - 1]['type']);
        $this->assertSame('ad', $result[$videoIndex + 1]['type']);
    }

    public function test_total_ads_never_exceed_the_configured_maximum(): void
    {
        Setting::create(['group' => 'ads', 'key' => 'max_ads_per_article', 'value' => 2]);
        Setting::create(['group' => 'ads', 'key' => 'min_paragraph_spacing', 'value' => 1]);
        Setting::create(['group' => 'ads', 'key' => 'min_paragraphs_required', 'value' => 1]);

        $post = Post::factory()->published()->create([
            'author_id' => User::factory()->create()->id,
            'content' => $this->paragraphs(10),
        ]);
        AdSlot::factory()->default()->create();

        $result = $this->resolver()->interleave($post, $this->blocksFor($post));

        $this->assertSame(2, $result->where('type', 'ad')->count());
    }

    public function test_no_ads_on_articles_shorter_than_the_minimum_paragraph_count(): void
    {
        Setting::create(['group' => 'ads', 'key' => 'min_paragraphs_required', 'value' => 10]);

        $post = Post::factory()->published()->create([
            'author_id' => User::factory()->create()->id,
            'content' => $this->paragraphs(3),
        ]);
        AdSlot::factory()->default()->create();

        $result = $this->resolver()->interleave($post, $this->blocksFor($post));

        $this->assertSame(0, $result->where('type', 'ad')->count());
    }

    public function test_no_ads_on_excluded_categories(): void
    {
        $category = Category::factory()->create();
        Setting::create(['group' => 'ads', 'key' => 'excluded_category_ids', 'value' => [$category->id]]);

        $post = Post::factory()->published()->create([
            'author_id' => User::factory()->create()->id,
            'category_id' => $category->id,
            'content' => $this->paragraphs(10),
        ]);
        AdSlot::factory()->default()->create();

        $result = $this->resolver()->interleave($post, $this->blocksFor($post));

        $this->assertSame(0, $result->where('type', 'ad')->count());
    }

    public function test_no_ads_on_excluded_individual_posts(): void
    {
        $post = Post::factory()->published()->create([
            'author_id' => User::factory()->create()->id,
            'content' => $this->paragraphs(10),
        ]);
        Setting::create(['group' => 'ads', 'key' => 'excluded_post_ids', 'value' => [$post->id]]);
        AdSlot::factory()->default()->create();

        $result = $this->resolver()->interleave($post, $this->blocksFor($post));

        $this->assertSame(0, $result->where('type', 'ad')->count());
    }

    public function test_automatic_placement_respects_minimum_paragraph_spacing(): void
    {
        Setting::create(['group' => 'ads', 'key' => 'min_paragraph_spacing', 'value' => 4]);
        Setting::create(['group' => 'ads', 'key' => 'max_ads_per_article', 'value' => 5]);
        Setting::create(['group' => 'ads', 'key' => 'min_paragraphs_required', 'value' => 1]);

        $post = Post::factory()->published()->create([
            'author_id' => User::factory()->create()->id,
            'content' => $this->paragraphs(9), // expect ads after paragraph 4 and 8
        ]);
        AdSlot::factory()->default()->create();

        $result = $this->resolver()->interleave($post, $this->blocksFor($post))->values();
        $adIndexes = $result->filter(fn ($b) => $b['type'] === 'ad')->keys();

        $this->assertCount(2, $adIndexes);
    }

    public function test_disabling_automatic_placement_leaves_only_manual_ads(): void
    {
        Setting::create(['group' => 'ads', 'key' => 'auto_placement_enabled', 'value' => false]);

        $post = Post::factory()->published()->create([
            'author_id' => User::factory()->create()->id,
            'content' => $this->paragraphs(10),
        ]);
        AdSlot::factory()->default()->create(); // would otherwise be auto-filled

        $result = $this->resolver()->interleave($post, $this->blocksFor($post));

        $this->assertSame(0, $result->where('type', 'ad')->count());
    }
}
