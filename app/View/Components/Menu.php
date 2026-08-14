<?php

namespace App\View\Components;

use App\Models\Menu as MenuModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * <x-menu location="primary_navigation" /> — renders whatever active menu
 * is assigned to that location, or nothing at all if none exists yet (a
 * fresh install still works fine with no menus configured, see the
 * fallback nav markup in layouts/public.blade.php).
 */
class Menu extends Component
{
    public ?Collection $items;

    public function __construct(public string $location, public bool $mobile = false)
    {
        // Same pattern as PublicContentCache: cache the resolved Eloquent
        // tree itself (not just "does a menu exist"), tagged so any
        // Menu/MenuItem write anywhere invalidates every location at once.
        $this->items = Cache::tags([MenuModel::CACHE_TAG])->remember(
            "menus.location.{$location}.tree",
            now()->addMinutes(30),
            function () use ($location) {
                $menu = MenuModel::query()->where('location', $location)->where('is_active', true)->first();

                return $menu?->renderableTree();
            }
        );
    }

    public function render(): View
    {
        return view('components.menu');
    }
}
