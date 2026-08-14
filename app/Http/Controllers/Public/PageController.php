<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\PublicContentCache;
use App\Services\SeoService;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __construct(
        private readonly PublicContentCache $cache,
        private readonly SeoService $seo,
    ) {}

    public function show(string $slug): View
    {
        $page = $this->cache->rememberPages(
            "pages.{$slug}",
            fn () => Page::published()->with(['seo.ogImage', 'seo.twitterImage', 'featuredImage'])->where('slug', $slug)->firstOrFail()
        );

        return view('public.pages.show', [
            'page' => $page,
            ...$this->seo->forPage($page),
        ]);
    }
}
