<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\PublicContentCache;
use App\Services\SeoService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __construct(
        private readonly PublicContentCache $cache,
        private readonly SeoService $seo,
    ) {}

    public function __invoke(): View
    {
        $posts = $this->cache->rememberPosts('home.posts', fn () => Post::published()
            ->with(['category', 'author', 'featuredImage'])
            ->latest('published_at')
            ->limit(9)
            ->get());

        return view('public.home', [
            'posts' => $posts,
            ...$this->seo->forHome(),
        ]);
    }
}
