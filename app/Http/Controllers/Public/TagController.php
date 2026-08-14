<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\PublicContentCache;
use App\Services\SeoService;
use Illuminate\Pagination\Paginator;
use Illuminate\View\View;

class TagController extends Controller
{
    public function __construct(
        private readonly PublicContentCache $cache,
        private readonly SeoService $seo,
    ) {}

    public function show(string $slug): View
    {
        $tag = Tag::where('slug', $slug)->firstOrFail();
        $page = Paginator::resolveCurrentPage() ?: 1;

        $posts = $this->cache->rememberPosts("tags.{$tag->id}.page.{$page}", fn () => $tag->posts()
            ->published()
            ->with(['author', 'category', 'featuredImage'])
            ->latest('published_at')
            ->paginate(12));

        return view('public.tags.show', [
            'tag' => $tag,
            'posts' => $posts,
            ...$this->seo->forTag($tag),
        ]);
    }
}
