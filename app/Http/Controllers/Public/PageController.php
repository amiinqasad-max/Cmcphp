<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::published()->where('slug', $slug)->firstOrFail();

        return view('public.pages.show', [
            'page' => $page,
            'title' => $page->seo_title ?: $page->title,
            'description' => $page->seo_description ?: null,
            'canonical' => $page->canonical_url ?: route('pages.show', $page),
        ]);
    }
}
