<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\AdPlacementResolver;
use App\Services\ArticleContentRenderer;
use App\Services\PublicContentCache;
use App\Services\SeoService;
use App\Services\SettingsService;
use Illuminate\Pagination\Paginator;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function __construct(
        private readonly ArticleContentRenderer $contentRenderer,
        private readonly AdPlacementResolver $adPlacementResolver,
        private readonly SettingsService $settings,
        private readonly PublicContentCache $cache,
        private readonly SeoService $seo,
    ) {}

    public function index(): View
    {
        $page = Paginator::resolveCurrentPage() ?: 1;

        $posts = $this->cache->rememberPosts("articles.index.page.{$page}", fn () => Post::published()
            ->with(['category', 'author', 'featuredImage'])
            ->latest('published_at')
            ->paginate(12));

        return view('public.articles.index', [
            'posts' => $posts,
            'title' => 'Articles — '.config('app.name'),
            'description' => 'The latest articles.',
            'canonical' => route('articles.index'),
        ]);
    }

    public function show(string $slug): View
    {
        $post = Post::published()
            ->with(['category', 'author', 'featuredImage', 'tags', 'seo.ogImage', 'seo.twitterImage', 'videos.media.thumbnail', 'approvedTopLevelComments'])
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

        return view('public.articles.show', [
            'post' => $post,
            'blocks' => $blocks,
            'related' => $related,
            'commentsEnabled' => $this->settings->commentsEnabled(),
            ...$this->seo->forPost($post),
        ]);
    }
}
