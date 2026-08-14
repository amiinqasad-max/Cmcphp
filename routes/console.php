<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rolls yesterday's activity into daily_rollups every night (§39).
Schedule::command('analytics:aggregate')->dailyAt('01:00');

// Prunes raw tracking events already captured in daily_rollups (§39).
Schedule::command('tracking:prune')->dailyAt('02:00');

// Publishes scheduled posts whose published_at has arrived (§4, §39).
Schedule::command('posts:publish-scheduled')->everyFiveMinutes();
