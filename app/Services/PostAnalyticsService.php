<?php

namespace App\Services;

use App\Enums\AdEventType;
use App\Enums\EventType;
use App\Models\AdEvent;
use App\Models\ArticleCompletion;
use App\Models\ArticleSession;
use App\Models\EngagementEvent;
use App\Models\Post;
use App\Models\VideoProgress;

/**
 * Live, all-time deep-dive stats for a single article's admin analytics
 * page (§40) — deliberately not rollup-backed like the site-wide dashboard,
 * since a single post's data volume is bounded and this is a low-traffic
 * on-demand view, not the dashboard hot path.
 */
class PostAnalyticsService
{
    public function forPost(Post $post): array
    {
        $sessions = ArticleSession::where('post_id', $post->id);
        $sessionCount = (clone $sessions)->count();

        $views = EngagementEvent::where('post_id', $post->id)
            ->where('event_type', EventType::ArticleOpen->value)
            ->count();

        $avgProgress = (clone $sessions)->avg('progress_percent');
        $avgReadingSeconds = (clone $sessions)->avg('time_spent_seconds');
        $completions = ArticleCompletion::where('post_id', $post->id)->count();

        return [
            'views' => $views,
            'unique_sessions' => $sessionCount,
            'avg_progress_percent' => $avgProgress !== null ? round($avgProgress, 1) : 0,
            'avg_reading_seconds' => (int) round($avgReadingSeconds ?? 0),
            'completion_rate' => $sessionCount > 0 ? round(($completions / $sessionCount) * 100, 1) : 0,
            'videos' => $post->videos->map(fn ($video) => $this->videoStats($video)),
            'ads' => $this->adStats($post),
        ];
    }

    private function videoStats($video): array
    {
        $played = EngagementEvent::where('post_video_id', $video->id)
            ->where('event_type', EventType::VideoPlay->value)
            ->count();

        $completed = VideoProgress::where('post_video_id', $video->id)
            ->where('is_completed', true)
            ->count();

        return [
            'position' => $video->position,
            'played' => $played,
            'completed' => $completed,
            'completion_rate' => $played > 0 ? round(($completed / $played) * 100, 1) : 0,
        ];
    }

    private function adStats(Post $post): array
    {
        $requests = AdEvent::where('post_id', $post->id)->where('event_type', AdEventType::Requested->value)->count();
        $rendered = AdEvent::where('post_id', $post->id)->where('event_type', AdEventType::Rendered->value)->count();

        return [
            'requests' => $requests,
            'rendered' => $rendered,
            'render_rate' => $requests > 0 ? round(($rendered / $requests) * 100, 1) : 0,
        ];
    }
}
