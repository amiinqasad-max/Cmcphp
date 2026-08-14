<?php

namespace App\Console\Commands;

use App\Services\AnalyticsAggregationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AggregateDailyAnalytics extends Command
{
    protected $signature = 'analytics:aggregate {date? : Y-m-d date to aggregate (defaults to yesterday)}';

    protected $description = 'Roll up engagement/video/ad events into daily_rollups for one date.';

    public function handle(AnalyticsAggregationService $service): int
    {
        $date = $this->argument('date')
            ? Carbon::parse($this->argument('date'))
            : Carbon::yesterday();

        $this->info("Aggregating analytics for {$date->toDateString()}...");
        $service->aggregateForDate($date);
        $this->info('Done.');

        return self::SUCCESS;
    }
}
