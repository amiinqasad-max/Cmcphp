<?php

namespace App\Services;

use App\Models\AdEvent;

/**
 * Records Internal Ad Analytics events (§26) — deliberately simple (no
 * completion-style derived state to compute) compared to
 * EngagementTrackingService, since these are just counters.
 */
class AdTrackingService
{
    /**
     * @param  array<int, array>  $events
     */
    public function processBatch(array $events, string $sessionId): void
    {
        foreach ($events as $event) {
            AdEvent::query()->insertOrIgnore([
                'ad_slot_id' => $event['ad_slot_id'],
                'ad_placement_id' => $event['ad_placement_id'] ?? null,
                'post_id' => $event['post_id'] ?? null,
                'session_id' => $sessionId,
                'event_type' => $event['event_type'],
                'event_uuid' => $event['event_uuid'],
                'created_at' => now(),
            ]);
        }
    }
}
