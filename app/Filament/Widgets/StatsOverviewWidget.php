<?php

namespace App\Filament\Widgets;

use App\Enums\PostStatus;
use App\Models\DailyRollup;
use App\Models\Post;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $since = Carbon::now()->subDays(30)->toDateString();

        $rollups = DailyRollup::siteWide()->where('date', '>=', $since)->get();

        $pageviews = (int) $rollups->sum('pageviews');
        $sessions = (int) $rollups->sum('sessions_count');
        $visitors = (int) $rollups->sum('visitors_count');
        $completions = (int) $rollups->sum('completions_count');
        $avgReadingSeconds = $rollups->whereNotNull('avg_reading_seconds')->avg('avg_reading_seconds');
        $videoPlays = (int) $rollups->sum('video_plays');
        $videoCompletions = (int) $rollups->sum('video_completions');
        $adRequests = (int) $rollups->sum('ad_requests');
        $adRenders = (int) $rollups->sum('ad_renders');

        $completionRate = $sessions > 0 ? round(($completions / $sessions) * 100, 1) : 0;
        $videoCompletionRate = $videoPlays > 0 ? round(($videoCompletions / $videoPlays) * 100, 1) : 0;
        $adRenderRate = $adRequests > 0 ? round(($adRenders / $adRequests) * 100, 1) : 0;

        return [
            Stat::make('Visitors (30d)', number_format($visitors)),
            Stat::make('Pageviews (30d)', number_format($pageviews)),
            Stat::make('Sessions (30d)', number_format($sessions)),
            Stat::make('Published articles', Post::query()->where('status', PostStatus::Published)->count()),
            Stat::make('Draft articles', Post::query()->where('status', PostStatus::Draft)->count()),
            Stat::make('Article completion rate', "{$completionRate}%")
                ->description('Sessions that reached full completion'),
            Stat::make('Avg. reading time', $avgReadingSeconds ? gmdate('i:s', (int) $avgReadingSeconds) : '—'),
            Stat::make('Video completion rate', "{$videoCompletionRate}%"),
            Stat::make('Ad requests (30d, internal)', number_format($adRequests)),
            Stat::make('Ad render rate (internal)', "{$adRenderRate}%")
                ->description('Internal Ad Analytics — not official AdSense reporting'),
        ];
    }
}
