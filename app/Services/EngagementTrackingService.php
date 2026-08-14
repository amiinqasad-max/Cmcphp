<?php

namespace App\Services;

use App\DTOs\EngagementEventData;
use App\Enums\EventType;
use App\Models\ArticleSession;
use App\Models\EngagementEvent;
use App\Models\Post;
use App\Models\PostVideo;
use App\Models\VideoProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processes a batch of validated tracking events. This is the one place
 * that decides `is_completed` for a reading session or a video — always
 * derived from the actual persisted numbers, never from a client-sent
 * "complete" flag (§10, §46).
 */
class EngagementTrackingService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @param  EngagementEventData[]  $events
     */
    public function processBatch(array $events, string $sessionId, ?string $userId): void
    {
        foreach ($events as $event) {
            try {
                $this->processOne($event, $sessionId, $userId);
            } catch (\Throwable $e) {
                // One malformed/out-of-range event should never fail the
                // whole batch (or the queue worker) — log and move on.
                Log::warning('Failed to process engagement event', [
                    'event_uuid' => $event->eventUuid,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function processOne(EngagementEventData $event, string $sessionId, ?string $userId): void
    {
        DB::transaction(function () use ($event, $sessionId, $userId) {
            $inserted = EngagementEvent::query()->insertOrIgnore([
                'event_type' => $event->eventType->value,
                'post_id' => $event->postId,
                'post_video_id' => $event->postVideoId,
                'session_id' => $sessionId,
                'user_id' => $userId,
                'event_uuid' => $event->eventUuid,
                'payload' => json_encode($event->payload),
                'created_at' => now(),
            ]);

            // event_uuid already seen — this exact event was already
            // applied to the aggregate rows below, so skip re-applying it.
            if (! $inserted) {
                return;
            }

            if ($event->eventType->isArticleEvent()) {
                $this->applyArticleEvent($event, $sessionId, $userId);
            } elseif ($event->eventType->isVideoEvent()) {
                $this->applyVideoEvent($event, $sessionId, $userId);
            }
        });
    }

    private function applyArticleEvent(EngagementEventData $event, string $sessionId, ?string $userId): void
    {
        $post = Post::find($event->postId);

        if (! $post) {
            return;
        }

        $threshold = $post->completion_reading_threshold ?? $this->settings->completionReadingThreshold();
        $now = now();

        /** @var ArticleSession $session */
        $session = ArticleSession::query()->firstOrNew([
            'post_id' => $event->postId,
            'session_id' => $sessionId,
        ]);

        if (! $session->exists) {
            $session->started_at = $now;
            $session->progress_percent = 0;
            $session->time_spent_seconds = 0;
        }

        if (isset($event->payload['percent'])) {
            $session->progress_percent = max($session->progress_percent, $this->clampPercent($event->payload['percent']));
        }

        if (isset($event->payload['time_spent_seconds'])) {
            $session->time_spent_seconds = max($session->time_spent_seconds, (int) $event->payload['time_spent_seconds']);
        }

        if ($event->eventType === EventType::ArticleBottom) {
            $session->bottom_reached = true;
        }

        // Once associated with a signed-in user, never revert to anonymous.
        $session->user_id = $session->user_id ?: $userId;
        $session->last_activity_at = $now;

        $wasCompleted = $session->is_completed;
        $session->is_completed = $session->progress_percent >= $threshold;

        if ($session->is_completed && ! $wasCompleted) {
            $session->completed_at = $now;
        }

        $session->save();
    }

    private function applyVideoEvent(EngagementEventData $event, string $sessionId, ?string $userId): void
    {
        if (! $event->postVideoId) {
            return;
        }

        $postVideo = PostVideo::find($event->postVideoId);

        if (! $postVideo) {
            return;
        }

        $now = now();

        /** @var VideoProgress $progress */
        $progress = VideoProgress::query()->firstOrNew([
            'post_video_id' => $event->postVideoId,
            'session_id' => $sessionId,
        ]);

        if (! $progress->exists) {
            $progress->post_id = $postVideo->post_id;
            $progress->started_at = $now;
            $progress->watched_seconds = 0;
            $progress->max_percent_reached = 0;
            $progress->play_count = 0;
            $progress->pause_count = 0;
        }

        if (isset($event->payload['watched_seconds'])) {
            $watched = (float) $event->payload['watched_seconds'];

            // Reject/clamp impossible values rather than trusting the client
            // outright (§46): negative is invalid, and watched time can't
            // meaningfully exceed the video's own duration by more than a
            // small tolerance (seek/replay jitter).
            if ($watched >= 0) {
                $cap = $postVideo->duration_seconds ? $postVideo->duration_seconds * 1.05 : $watched;
                $progress->watched_seconds = (string) max((float) $progress->watched_seconds, min($watched, $cap));
            }
        }

        if (isset($event->payload['percent'])) {
            $progress->max_percent_reached = max($progress->max_percent_reached, $this->clampPercent($event->payload['percent']));
        }

        match ($event->eventType) {
            EventType::VideoPlay => $progress->play_count++,
            EventType::VideoResume => $progress->play_count++,
            EventType::VideoPause => $progress->pause_count++,
            default => null,
        };

        $progress->user_id = $progress->user_id ?: $userId;
        $progress->last_watched_at = $now;

        $wasCompleted = $progress->is_completed;
        $progress->is_completed = $progress->max_percent_reached >= $postVideo->completion_threshold;

        if ($progress->is_completed && ! $wasCompleted) {
            $progress->completed_at = $now;
        }

        $progress->save();
    }

    private function clampPercent(int|float $value): int
    {
        return (int) max(0, min(100, $value));
    }
}
