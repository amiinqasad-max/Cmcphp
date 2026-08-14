<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\View\View;

class TagController extends Controller
{
    public function show(string $slug): View
    {
        $tag = Tag::where('slug', $slug)->firstOrFail();

        $posts = $tag->posts()
            ->published()
            ->with(['author', 'category', 'featuredImage'])
            ->latest('published_at')
            ->paginate(12);

        return view('public.tags.show', [
            'tag' => $tag,
            'posts' => $posts,
            'title' => $tag->seo_title ?: "#{$tag->name}",
            'description' => $tag->seo_description ?: $tag->description,
        ]);
    }
}
