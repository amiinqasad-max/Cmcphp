<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\AdPlacementResolver;
use App\Services\ArticleContentRenderer;
use App\Services\SettingsService;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function __construct(
        private readonly ArticleContentRenderer $contentRenderer,
        private readonly AdPlacementResolver $adPlacementResolver,
        private readonly SettingsService $settings,
    ) {}

    public function index(): View
    {
        $posts = Post::published()
            ->with(['category', 'author', 'featuredImage'])
            ->latest('published_at')
            ->paginate(12);

        return view('public.articles.index', [
            'posts' => $posts,
            'title' => 'Articles — '.config('app.name'),
            'description' => 'The latest articles.',
        ]);
    }

    public function show(string $slug): View
    {
        $post = Post::published()
            ->with(['category', 'author', 'featuredImage', 'tags', 'seo.ogImage', 'videos.media.thumbnail', 'approvedTopLevelComments'])
            ->where('slug', $slug)
            ->firstOrFail();

        $blocks = $this->adPlacementResolver->interleave($post, $this->contentRenderer->blocks($post));

        $related = Post::published()
            ->whereKeyNot($post->id)
            ->when($post->category_id, fn ($query) => $query->where('category_id', $post->category_id))
            ->with(['featuredImage'])
            ->latest('published_at')
            ->limit(3)
            ->get();

        $ogImage = $post->seo?->ogImage?->url ?: $post->featuredImage?->url;

        return view('public.articles.show', [
            'post' => $post,
            'blocks' => $blocks,
            'related' => $related,
            'commentsEnabled' => $this->settings->commentsEnabled(),
            'title' => $post->seo?->seo_title ?: $post->title,
            'description' => $post->seo?->meta_description ?: $post->excerpt,
            'canonical' => $post->seo?->canonical_url ?: route('articles.show', $post),
            'ogImage' => $ogImage,
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $post->title,
                'description' => $post->excerpt,
                'image' => $ogImage ? [$ogImage] : [],
                'datePublished' => $post->published_at?->toIso8601String(),
                'dateModified' => $post->updated_at?->toIso8601String(),
                'author' => [
                    '@type' => 'Person',
                    'name' => $post->author?->name,
                ],
            ],
        ]);
    }
}
