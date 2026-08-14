<?php

namespace App\Services;

use App\Enums\AdEventType;
use App\Enums\EventType;
use App\Models\AdEvent;
use App\Models\ArticleCompletion;
use App\Models\ArticleSession;
use App\Models\DailyRollup;
use App\Models\EngagementEvent;
use App\Models\VideoProgress;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Rolls raw tracking tables up into one row per (date, post) plus a
 * site-wide (post_id = null) row, so the dashboard and per-article
 * analytics page never scan engagement_events/video_progress/ad_events
 * directly (§29/§54). Idempotent: re-running for a date replaces that
 * date's rows rather than accumulating duplicates.
 */
class AnalyticsAggregationService
{
    public function aggregateForDate(CarbonInterface $date): void
    {
        $dateString = $date->toDateString();

        DailyRollup::where('date', $dateString)->delete();

        $postIds = $this->affectedPostIds($dateString);
        $totals = array_fill_keys([
            'pageviews', 'sessions_count', 'completions_count',
            'video_plays', 'video_completions', 'ad_requests', 'ad_renders',
        ], 0);

        foreach ($postIds as $postId) {
            $row = $this->computeForPost($postId, $dateString);

            DailyRollup::create(array_merge(['date' => $dateString, 'post_id' => $postId], $row));

            foreach (array_keys($totals) as $key) {
                $totals[$key] += $row[$key];
            }
        }

        DailyRollup::create(array_merge(
            ['date' => $dateString, 'post_id' => null],
            $this->computeSiteWide($dateString, $totals)
        ));
    }

    /**
     * @return Collection<int, string>
     */
    private function affectedPostIds(string $date): Collection
    {
        return ArticleSession::whereDate('started_at', $date)->pluck('post_id')
            ->merge(AdEvent::whereDate('created_at', $date)->whereNotNull('post_id')->pluck('post_id'))
            ->unique()
            ->values();
    }

    private function computeForPost(string $postId, string $date): array
    {
        $sessions = ArticleSession::where('post_id', $postId)->whereDate('started_at', $date)->get();

        return [
            'pageviews' => EngagementEvent::where('post_id', $postId)
                ->where('event_type', EventType::ArticleOpen->value)
                ->whereDate('created_at', $date)
                ->count(),
            'sessions_count' => $sessions->count(),
            'visitors_count' => $sessions->pluck('session_id')->unique()->count(),
            'avg_progress_percent' => $sessions->isNotEmpty() ? (int) round($sessions->avg('progress_percent')) : null,
            'avg_reading_seconds' => $sessions->isNotEmpty() ? (int) round($sessions->avg('time_spent_seconds')) : null,
            'completions_count' => ArticleCompletion::where('post_id', $postId)->whereDate('completed_at', $date)->count(),
            'video_plays' => EngagementEvent::where('post_id', $postId)
                ->where('event_type', EventType::VideoPlay->value)
                ->whereDate('created_at', $date)
                ->count(),
            'video_completions' => VideoProgress::where('post_id', $postId)
                ->where('is_completed', true)
                ->whereDate('completed_at', $date)
                ->count(),
            'ad_requests' => AdEvent::where('post_id', $postId)
                ->where('event_type', AdEventType::Requested->value)
                ->whereDate('created_at', $date)
                ->count(),
            'ad_renders' => AdEvent::where('post_id', $postId)
                ->where('event_type', AdEventType::Rendered->value)
                ->whereDate('created_at', $date)
                ->count(),
        ];
    }

    private function computeSiteWide(string $date, array $totals): array
    {
        $sessions = ArticleSession::whereDate('started_at', $date);
        $avgProgress = (clone $sessions)->avg('progress_percent');
        $avgReading = (clone $sessions)->avg('time_spent_seconds');

        return array_merge($totals, [
            'sessions_count' => (clone $sessions)->count(),
            'visitors_count' => (clone $sessions)->distinct('session_id')->count('session_id'),
            'avg_progress_percent' => $avgProgress !== null ? (int) round($avgProgress) : null,
            'avg_reading_seconds' => $avgReading !== null ? (int) round($avgReading) : null,
        ]);
    }
}
