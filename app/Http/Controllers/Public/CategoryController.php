<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(string $slug): View
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        $posts = $category->posts()
            ->published()
            ->with(['author', 'featuredImage'])
            ->latest('published_at')
            ->paginate(12);

        return view('public.categories.show', [
            'category' => $category,
            'posts' => $posts,
            'title' => $category->seo_title ?: $category->name,
            'description' => $category->seo_description ?: $category->description,
        ]);
    }
}
