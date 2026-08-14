<?php

namespace App\Console\Commands;

use App\Models\AdEvent;
use App\Models\EngagementEvent;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * engagement_events and ad_events are deliberately high-volume, append-only
 * raw logs (§29/§36) — once AnalyticsAggregationService has rolled a date
 * into daily_rollups, the raw rows for that date have no further purpose
 * and just cost storage/index size. Deletes in bounded batches rather than
 * one giant statement, since these tables are exactly the ones expected to
 * grow largest in production.
 */
class PruneStaleTrackingEvents extends Command
{
    protected $signature = 'tracking:prune {--days=90 : Delete raw events older than this many days}';

    protected $description = 'Delete raw engagement/ad events older than the retention window (already rolled up into daily_rollups).';

    private const BATCH_SIZE = 1000;

    public function handle(): int
    {
        $cutoff = Carbon::now()->subDays((int) $this->option('days'));

        $engagementDeleted = $this->pruneInBatches(EngagementEvent::query(), $cutoff);
        $adDeleted = $this->pruneInBatches(AdEvent::query(), $cutoff);

        $this->info("Pruned {$engagementDeleted} engagement events and {$adDeleted} ad events older than {$cutoff->toDateString()}.");

        return self::SUCCESS;
    }

    private function pruneInBatches(Builder $query, Carbon $cutoff): int
    {
        $total = 0;

        do {
            $deleted = (clone $query)
                ->where('created_at', '<', $cutoff)
                ->limit(self::BATCH_SIZE)
                ->delete();

            $total += $deleted;
        } while ($deleted === self::BATCH_SIZE);

        return $total;
    }
}
