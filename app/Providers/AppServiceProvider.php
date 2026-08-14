<?php

namespace App\Providers;

use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\User;
use App\Observers\MenuObserver;
use App\Observers\PageObserver;
use App\Observers\PostObserver;
use App\Observers\RedirectObserver;
use App\Observers\UserObserver;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        // §3 canonical URLs: generated URLs (route()/url(), and therefore
        // every canonical/OG/sitemap link) always match APP_URL's own
        // scheme, regardless of what scheme the incoming request arrived
        // on. Without this, a app running behind a proxy that doesn't
        // forward HTTPS detection correctly (see deploy/nginx's
        // `fastcgi_param HTTPS on`) could silently generate http:// canonical
        // links on an https:// site.
        if ($scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME)) {
            URL::forceScheme($scheme);
        }

        RateLimiter::for('tracking', function (Request $request) {
            $key = $request->attributes->get('anonymous_session_id') ?: $request->ip();

            return Limit::perMinute(config('cms.tracking.rate_limit_per_minute'))->by($key);
        });

        Post::observe(PostObserver::class);
        Page::observe(PageObserver::class);
        User::observe(UserObserver::class);
        Redirect::observe(RedirectObserver::class);
        Menu::observe(MenuObserver::class);

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
