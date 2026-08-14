<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Public homepage. Post listings are wired up in Phase 2 (CMS core);
     * this currently renders the base public layout as a placeholder.
     */
    public function __invoke(): View
    {
        return view('public.home', [
            'title' => config('app.name').' — '.config('app.tagline', 'A modern content platform'),
            'description' => 'A fast, SEO-friendly content platform built on Laravel and Filament.',
        ]);
    }
}
