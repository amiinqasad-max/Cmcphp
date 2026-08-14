<?php

namespace App\Services;

use App\Models\Redirect;
use Illuminate\Support\Facades\Cache;

/**
 * §6: resolves an unmatched request path against admin-managed redirects
 * without a query on every request. The whole active-redirect table is
 * small and rarely changes, so it's cached as one flat map (forever,
 * invalidated by Redirect's own save/delete hooks) rather than doing a
 * `WHERE source_path = ?` query per lookup.
 */
class RedirectResolver
{
    /** @return array{id: string, destination: string, status_code: int}|null */
    public function resolve(string $path): ?array
    {
        $map = $this->map();
        $normalized = Redirect::normalizePath($path);

        return $map[$normalized] ?? null;
    }

    public function recordHit(string $redirectId): void
    {
        Redirect::find($redirectId)?->incrementHit();
    }

    /** @return array<string, array{id: string, destination: string, status_code: int}> */
    private function map(): array
    {
        return Cache::rememberForever(Redirect::CACHE_KEY, fn () => Redirect::query()
            ->where('is_active', true)
            ->get(['id', 'source_path', 'destination', 'status_code'])
            ->mapWithKeys(fn (Redirect $redirect) => [
                $redirect->source_path => [
                    'id' => $redirect->id,
                    'destination' => $redirect->destination,
                    'status_code' => $redirect->status_code->value,
                ],
            ])
            ->all());
    }
}
