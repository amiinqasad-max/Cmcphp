<?php

namespace Tests\Feature\Public;

use App\Enums\AdPlacementType;
use App\Models\AdPlacement;
use App\Models\AdSlot;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §25: ad placements must remain clearly distinguishable from content —
 * verified at the rendering layer, not just the resolver.
 */
class AdSlotDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_ad_is_labeled_as_advertisement_on_the_article_page(): void
    {
        $post = Post::factory()->published()->create([
            'author_id' => User::factory()->create()->id,
            'content' => collect(range(1, 6))->map(fn ($i) => "<p>Paragraph {$i}.</p>")->implode(''),
        ]);
        $slot = AdSlot::factory()->create();
        AdPlacement::factory()->for($post)->for($slot, 'adSlot')->create(['placement_type' => AdPlacementType::Top]);

        $response = $this->get(route('articles.show', $post));

        $response->assertOk();
        $response->assertSee('Advertisement');
        $response->assertSee('adsbygoogle', false);
    }

    /**
     * §14/§34 "environment-only AdSense credentials": a site only has one
     * AdSense publisher ID, so the rendered <ins data-ad-client> must
     * always come from ADSENSE_CLIENT_ID, never from the AdSlot row —
     * even if the row itself carries a different (stale/inconsistent)
     * value.
     */
    public function test_rendered_ad_client_always_comes_from_config_not_the_ad_slot_row(): void
    {
        config(['services.adsense.client_id' => 'ca-pub-1111111111111111']);

        $post = Post::factory()->published()->create([
            'author_id' => User::factory()->create()->id,
            'content' => collect(range(1, 6))->map(fn ($i) => "<p>Paragraph {$i}.</p>")->implode(''),
        ]);
        $slot = AdSlot::factory()->create(['ad_client' => 'ca-pub-9999999999999999']);
        AdPlacement::factory()->for($post)->for($slot, 'adSlot')->create(['placement_type' => AdPlacementType::Top]);

        $response = $this->get(route('articles.show', $post));

        $response->assertOk();
        $response->assertSee('data-ad-client="ca-pub-1111111111111111"', false);
        $response->assertDontSee('ca-pub-9999999999999999', false);
    }
}
