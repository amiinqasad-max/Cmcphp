<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\PublicContentCache;
use Illuminate\Pagination\Paginator;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(private readonly PublicContentCache $cache) {}

    public function show(string $slug): View
    {
        $category = Category::where('slug', $slug)->firstOrFail();
        $page = Paginator::resolveCurrentPage() ?: 1;

        $posts = $this->cache->rememberPosts("categories.{$category->id}.page.{$page}", fn () => $category->posts()
            ->published()
            ->with(['author', 'featuredImage'])
            ->latest('published_at')
            ->paginate(12));

        return view('public.categories.show', [
            'category' => $category,
            'posts' => $posts,
            'title' => $category->seo_title ?: $category->name,
            'description' => $category->seo_description ?: $category->description,
        ]);
    }
}
