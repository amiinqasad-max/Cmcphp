<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\PublicContentCache;
use App\Services\SeoService;
use Illuminate\Pagination\Paginator;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        private readonly PublicContentCache $cache,
        private readonly SeoService $seo,
    ) {}

    public function show(string $slug): View
    {
        $category = Category::with('image')->where('slug', $slug)->firstOrFail();
        $page = Paginator::resolveCurrentPage() ?: 1;

        $posts = $this->cache->rememberPosts("categories.{$category->id}.page.{$page}", fn () => $category->posts()
            ->published()
            ->with(['author', 'featuredImage'])
            ->latest('published_at')
            ->paginate(12));

        return view('public.categories.show', [
            'category' => $category,
            'posts' => $posts,
            ...$this->seo->forCategory($category),
        ]);
    }
}
