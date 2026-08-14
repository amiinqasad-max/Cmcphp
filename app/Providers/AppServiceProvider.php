<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('tracking', function (Request $request) {
            $key = $request->attributes->get('anonymous_session_id') ?: $request->ip();

            return Limit::perMinute(config('cms.tracking.rate_limit_per_minute'))->by($key);
        });
    }
}
