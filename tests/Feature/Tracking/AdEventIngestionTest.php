<?php

namespace Tests\Feature\Tracking;

use App\Models\AdEvent;
use App\Models\AdPlacement;
use App\Models\AdSlot;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdEventIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_ad_event_batch_is_accepted_and_recorded(): void
    {
        $slot = AdSlot::factory()->create();
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);

        $response = $this->postJson('/api/track/ad-events', [
            'events' => [[
                'event_type' => 'ad_requested',
                'ad_slot_id' => $slot->id,
                'post_id' => $post->id,
                'event_uuid' => (string) Str::uuid(),
            ]],
        ]);

        $response->assertStatus(202);

        $this->assertDatabaseHas('ad_events', [
            'ad_slot_id' => $slot->id,
            'event_type' => 'ad_requested',
        ]);
    }

    public function test_unknown_ad_slot_is_rejected(): void
    {
        $this->postJson('/api/track/ad-events', [
            'events' => [[
                'event_type' => 'ad_requested',
                'ad_slot_id' => (string) Str::uuid(),
                'event_uuid' => (string) Str::uuid(),
            ]],
        ])->assertStatus(422);
    }

    public function test_unknown_event_type_is_rejected(): void
    {
        $slot = AdSlot::factory()->create();

        $this->postJson('/api/track/ad-events', [
            'events' => [[
                'event_type' => 'ad_clicked_a_lot',
                'ad_slot_id' => $slot->id,
                'event_uuid' => (string) Str::uuid(),
            ]],
        ])->assertStatus(422);
    }

    public function test_duplicate_event_uuid_is_deduped(): void
    {
        $slot = AdSlot::factory()->create();
        $eventUuid = (string) Str::uuid();

        $payload = [
            'events' => [[
                'event_type' => 'ad_rendered',
                'ad_slot_id' => $slot->id,
                'event_uuid' => $eventUuid,
            ]],
        ];

        $this->postJson('/api/track/ad-events', $payload)->assertStatus(202);
        $this->postJson('/api/track/ad-events', $payload)->assertStatus(202);

        $this->assertSame(1, AdEvent::where('event_uuid', $eventUuid)->count());
    }

    public function test_ad_placement_belongs_check_is_not_required_but_id_must_exist(): void
    {
        $slot = AdSlot::factory()->create();
        $post = Post::factory()->published()->create(['author_id' => User::factory()->create()->id]);
        $placement = AdPlacement::factory()->for($post)->for($slot, 'adSlot')->create();

        $this->postJson('/api/track/ad-events', [
            'events' => [[
                'event_type' => 'ad_viewable',
                'ad_slot_id' => $slot->id,
                'ad_placement_id' => $placement->id,
                'post_id' => $post->id,
                'event_uuid' => (string) Str::uuid(),
            ]],
        ])->assertStatus(202);

        $this->assertDatabaseHas('ad_events', ['ad_placement_id' => $placement->id, 'event_type' => 'ad_viewable']);
    }
}
