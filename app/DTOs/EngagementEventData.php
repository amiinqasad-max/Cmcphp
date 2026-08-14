<?php

namespace App\DTOs;

use App\Enums\EventType;

/**
 * A single validated milestone event from a tracking batch. Deliberately
 * dumb: it carries what the client observed, not what's true — completion
 * is always recomputed server-side from persisted watched_seconds/percent,
 * never taken from a client-asserted "complete" event (§46).
 */
final readonly class EngagementEventData
{
    public function __construct(
        public EventType $eventType,
        public string $postId,
        public ?string $postVideoId,
        public string $eventUuid,
        public array $payload,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            eventType: EventType::from($data['event_type']),
            postId: $data['post_id'],
            postVideoId: $data['post_video_id'] ?? null,
            eventUuid: $data['event_uuid'],
            payload: $data['payload'] ?? [],
        );
    }
}
