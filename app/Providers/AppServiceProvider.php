<?php

namespace App\Providers;

use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Observers\PageObserver;
use App\Observers\PostObserver;
use App\Observers\UserObserver;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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

        Post::observe(PostObserver::class);
        Page::observe(PageObserver::class);
        User::observe(UserObserver::class);

        Event::listen(Login::class, function (Login $event) {
            app(ActivityLogger::class)->log('auth.login', $event->user, [], $event->user);
        });

        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                app(ActivityLogger::class)->log('auth.logout', $event->user, [], $event->user);
            }
        });
    }
}
