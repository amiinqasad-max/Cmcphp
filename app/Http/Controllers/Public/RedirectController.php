<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\RedirectResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Registered as Route::fallback() in routes/web.php — Laravel only invokes
 * a fallback route once *every* other registered route (public, auth,
 * api/track, and the Filament admin panel's own routes, registered from a
 * separate service provider) has failed to match. That's what keeps this
 * from ever interfering with admin/API/auth routes (§6): those all match
 * their own real routes first, so a redirect source can never shadow them
 * even if an admin mistakenly creates one at, say, "/admin".
 */
class RedirectController extends Controller
{
    public function __construct(private readonly RedirectResolver $resolver) {}

    public function __invoke(Request $request): Response
    {
        // Redirects are a page-navigation concept; anything else falling
        // through to here (a stray POST, a bad API call) is a genuine 404.
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            throw new NotFoundHttpException;
        }

        $match = $this->resolver->resolve('/'.$request->path());

        if ($match === null) {
            throw new NotFoundHttpException;
        }

        $this->resolver->recordHit($match['id']);

        return redirect($match['destination'], $match['status_code']);
    }
}
