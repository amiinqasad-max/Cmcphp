<?php

namespace App\Filament\Widgets;

use App\Models\DailyRollup;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class VisitorsTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Visitors & pageviews (last 14 days)';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected function getData(): array
    {
        $since = Carbon::now()->subDays(13)->startOfDay();

        $rollups = DailyRollup::siteWide()
            ->where('date', '>=', $since->toDateString())
            ->orderBy('date')
            ->get()
            ->keyBy(fn (DailyRollup $r) => $r->date->toDateString());

        $labels = [];
        $visitors = [];
        $pageviews = [];

        for ($day = $since->copy(); $day->lte(Carbon::today()); $day->addDay()) {
            $key = $day->toDateString();
            $labels[] = $day->format('M j');
            $visitors[] = (int) ($rollups->get($key)?->visitors_count ?? 0);
            $pageviews[] = (int) ($rollups->get($key)?->pageviews ?? 0);
        }

        return [
            'datasets' => [
                ['label' => 'Visitors', 'data' => $visitors, 'borderColor' => '#f59e0b'],
                ['label' => 'Pageviews', 'data' => $pageviews, 'borderColor' => '#6366f1'],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
