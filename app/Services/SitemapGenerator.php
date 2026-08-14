<?php

namespace App\Services;

use App\Enums\PageStatus;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * §4: builds the /sitemap.xml entry list. Cached as one flat collection
 * (short TTL, tag-invalidated on any post/page/category write — see
 * PostObserver/PageObserver and Category's own boot hook) so a crawler
 * hammering the sitemap doesn't run these queries on every hit.
 *
 * Deliberately returns entries as a plain collection of ['loc','lastmod']
 * rather than writing XML directly — if the site outgrows a single
 * sitemap file, SitemapController can start paginating *this* collection
 * into indexed chunks without this class changing at all.
 */
class SitemapGenerator
{
    public const CACHE_TAG = 'sitemap';

    private const TTL_MINUTES = 15;

    /** @return Collection<int, array{loc: string, lastmod: ?string}> */
    public function entries(): Collection
    {
        return Cache::tags([self::CACHE_TAG])->remember('sitemap.entries', now()->addMinutes(self::TTL_MINUTES), function () {
            return collect()
                ->push(['loc' => route('home'), 'lastmod' => $this->latestPostTimestamp()])
                ->merge($this->posts())
                ->merge($this->pages())
                ->merge($this->categories())
                ->values();
        });
    }

    /** @return Collection<int, array{loc: string, lastmod: ?string}> */
    private function posts(): Collection
    {
        return Post::published()
            ->with('seo:id,seoable_id,seoable_type,robots_index')
            ->select(['id', 'slug', 'updated_at'])
            ->orderBy('id')
            ->cursor()
            ->filter(fn (Post $post) => $post->seo?->robots_index !== false)
            ->map(fn (Post $post) => [
                'loc' => route('articles.show', $post),
                'lastmod' => $post->updated_at?->toAtomString(),
            ])
            ->values()
            ->collect();
    }

    /** @return Collection<int, array{loc: string, lastmod: ?string}> */
    private function pages(): Collection
    {
        return Page::query()
            ->where('status', PageStatus::Published)
            ->where('published_at', '<=', now())
            ->with('seo:id,seoable_id,seoable_type,robots_index')
            ->select(['id', 'slug', 'updated_at'])
            ->orderBy('id')
            ->cursor()
            ->filter(fn (Page $page) => $page->seo?->robots_index !== false)
            ->map(fn (Page $page) => [
                'loc' => route('pages.show', $page),
                'lastmod' => $page->updated_at?->toAtomString(),
            ])
            ->values()
            ->collect();
    }

    /** Only categories with at least one published post — no point indexing an empty listing. */
    private function categories(): Collection
    {
        return Category::query()
            ->whereHas('posts', fn ($query) => $query->published())
            ->select(['id', 'slug', 'updated_at'])
            ->orderBy('id')
            ->cursor()
            ->map(fn (Category $category) => [
                'loc' => route('categories.show', $category),
                'lastmod' => $category->updated_at?->toAtomString(),
            ])
            ->values()
            ->collect();
    }

    private function latestPostTimestamp(): ?string
    {
        return Post::published()->latest('published_at')->first(['published_at'])?->published_at?->toAtomString();
    }
}
