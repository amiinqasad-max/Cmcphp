<?php

namespace App\Jobs;

use App\DTOs\EngagementEventData;
use App\Services\EngagementTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessEngagementEventBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<int, array>  $events  Validated raw event arrays (kept
     *                                     as arrays, not DTOs, so the job
     *                                     payload serializes trivially).
     */
    public function __construct(
        public readonly array $events,
        public readonly string $sessionId,
        public readonly ?string $userId,
    ) {}

    public function handle(EngagementTrackingService $service): void
    {
        $dtos = array_map(fn (array $event) => EngagementEventData::fromArray($event), $this->events);

        $service->processBatch($dtos, $this->sessionId, $this->userId);
    }
}
