<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Support\Str;

/**
 * Single place that resolves "what SEO metadata does this page actually
 * render" (§10 — Defaults and Overrides). Every content type follows the
 * same four-tier priority, never letting a blank/invalid value clobber a
 * valid fallback further down the chain:
 *
 *   1. Explicit content SEO value  (an editor typed it on this post/page)
 *   2. Content-specific fallback   (the post's own title/excerpt/image)
 *   3. Global site setting         (settings.seo.* / settings.general.*)
 *   4. Safe system default         (config('app.name'), a generic string)
 *
 * Controllers call one `for*()` method and pass the result straight to
 * <x-seo-head>; nothing downstream re-implements fallback logic.
 */
class SeoService
{
    /** Per-request memoization so a repeated settings-driven media lookup (site logo/default social image) never re-queries. */
    private array $mediaUrlCache = [];

    public function __construct(private readonly SettingsService $settings) {}

    /** @return array<string, mixed> */
    public function forPost(Post $post): array
    {
        $seo = $post->seo;

        $title = $this->firstFilled($seo?->seo_title, $post->title, $this->settings->seoDefaultTitle(), $this->settings->siteName());
        $description = $this->firstFilled(
            $seo?->meta_description,
            $post->excerpt,
            $this->generateExcerpt($post->content ?? ''),
            $this->settings->seoDefaultDescription(),
        );
        // Read straight off the already eager-loaded seo.ogImage/seo.twitterImage/
        // featuredImage relations (ArticleController::show() loads them) rather
        // than re-querying Media by ID — only the site-wide default (settings)
        // tier ever needs a fresh lookup, and defaultSocialImageUrl() memoizes
        // that within the request.
        $ogImage = $seo?->ogImage?->url ?: ($post->featuredImage?->url ?: $this->defaultSocialImageUrl());
        $twitterImage = $seo?->twitterImage?->url ?: $ogImage;

        $canonical = $this->firstFilled($seo?->canonical_url, route('articles.show', $post));

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robotsIndex' => $seo ? (bool) $seo->robots_index : $this->settings->seoDefaultRobotsIndex(),
            'robotsFollow' => $seo ? (bool) $seo->robots_follow : $this->settings->seoDefaultRobotsFollow(),
            'ogTitle' => $this->firstFilled($seo?->og_title, $title),
            'ogDescription' => $this->firstFilled($seo?->og_description, $description),
            'ogImage' => $ogImage,
            'ogType' => 'article',
            'twitterCard' => $seo?->twitter_card ?: $this->settings->seoDefaultTwitterCard(),
            'twitterTitle' => $this->firstFilled($seo?->twitter_title, $seo?->og_title, $title),
            'twitterDescription' => $this->firstFilled($seo?->twitter_description, $seo?->og_description, $description),
            'twitterImage' => $twitterImage,
            'jsonLd' => [
                $this->articleJsonLd($post, $ogImage, $description),
                $this->breadcrumbsJsonLd($this->postBreadcrumbs($post)),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function forPage(Page $page): array
    {
        $seo = $page->seo;

        $title = $this->firstFilled($seo?->seo_title, $page->title, $this->settings->seoDefaultTitle(), $this->settings->siteName());
        $description = $this->firstFilled(
            $seo?->meta_description,
            $this->generateExcerpt($page->content ?? ''),
            $this->settings->seoDefaultDescription(),
        );
        $ogImage = $seo?->ogImage?->url ?: ($page->featuredImage?->url ?: $this->defaultSocialImageUrl());

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $this->firstFilled($seo?->canonical_url, route('pages.show', $page)),
            'robotsIndex' => $seo ? (bool) $seo->robots_index : $this->settings->seoDefaultRobotsIndex(),
            'robotsFollow' => $seo ? (bool) $seo->robots_follow : $this->settings->seoDefaultRobotsFollow(),
            'ogTitle' => $this->firstFilled($seo?->og_title, $title),
            'ogDescription' => $this->firstFilled($seo?->og_description, $description),
            'ogImage' => $ogImage,
            'ogType' => 'website',
            'twitterCard' => $seo?->twitter_card ?: $this->settings->seoDefaultTwitterCard(),
            'twitterTitle' => $this->firstFilled($seo?->twitter_title, $seo?->og_title, $title),
            'twitterDescription' => $this->firstFilled($seo?->twitter_description, $seo?->og_description, $description),
            'twitterImage' => $seo?->twitterImage?->url ?: $ogImage,
            'jsonLd' => $this->breadcrumbsJsonLd([
                ['name' => 'Home', 'url' => route('home')],
                ['name' => $page->title, 'url' => route('pages.show', $page)],
            ]),
        ];
    }

    /** @return array<string, mixed> */
    public function forCategory(Category $category): array
    {
        $title = $this->firstFilled($category->seo_title, $category->name, $this->settings->seoDefaultTitle());
        $description = $this->firstFilled($category->seo_description, $category->description, $this->settings->seoDefaultDescription());

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => route('categories.show', $category),
            'robotsIndex' => $this->settings->seoDefaultRobotsIndex(),
            'robotsFollow' => $this->settings->seoDefaultRobotsFollow(),
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogImage' => $category->image?->url ?: $this->defaultSocialImageUrl(),
            'ogType' => 'website',
            'twitterCard' => $this->settings->seoDefaultTwitterCard(),
            'jsonLd' => $this->breadcrumbsJsonLd([
                ['name' => 'Home', 'url' => route('home')],
                ['name' => $category->name, 'url' => route('categories.show', $category)],
            ]),
        ];
    }

    /** @return array<string, mixed> */
    public function forTag(Tag $tag): array
    {
        $title = $this->firstFilled($tag->seo_title, "#{$tag->name}", $this->settings->seoDefaultTitle());
        $description = $this->firstFilled($tag->seo_description, $tag->description, $this->settings->seoDefaultDescription());

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => route('tags.show', $tag),
            'robotsIndex' => $this->settings->seoDefaultRobotsIndex(),
            'robotsFollow' => $this->settings->seoDefaultRobotsFollow(),
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogImage' => $this->defaultSocialImageUrl(),
            'ogType' => 'website',
            'twitterCard' => $this->settings->seoDefaultTwitterCard(),
        ];
    }

    /** @return array<string, mixed> */
    public function forHome(): array
    {
        $title = $this->firstFilled(
            $this->settings->seoDefaultTitle(),
            $this->settings->siteName().($this->settings->siteTagline() ? ' — '.$this->settings->siteTagline() : ''),
            config('app.name'),
        );
        $description = $this->firstFilled(
            $this->settings->seoDefaultDescription(),
            $this->settings->siteDescription(),
            'A fast, SEO-friendly content platform.',
        );

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => route('home'),
            'robotsIndex' => $this->settings->seoDefaultRobotsIndex(),
            'robotsFollow' => $this->settings->seoDefaultRobotsFollow(),
            'ogTitle' => $title,
            'ogDescription' => $description,
            'ogImage' => $this->defaultSocialImageUrl(),
            'ogType' => 'website',
            'twitterCard' => $this->settings->seoDefaultTwitterCard(),
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@graph' => [$this->websiteJsonLdNode(), $this->organizationJsonLdNode()],
            ],
        ];
    }

    /** First non-blank value; never lets an empty string/null win over a real fallback. */
    private function firstFilled(?string ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (filled($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Strip tags/markers and take the first ~160 characters at a word boundary. */
    public function generateExcerpt(string $html, int $length = 160): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('[[VIDEO_', ' [[VIDEO_', $html))));

        return $text === '' ? null : Str::limit($text, $length);
    }

    private function mediaUrl(?string $mediaId): ?string
    {
        if (blank($mediaId)) {
            return null;
        }

        return $this->mediaUrlCache[$mediaId] ??= Media::find($mediaId)?->url;
    }

    /** The site-wide fallback social image (settings.seo.default_og_image_media_id), memoized. */
    private function defaultSocialImageUrl(): ?string
    {
        return $this->mediaUrl($this->settings->seoDefaultOgImageMediaId());
    }

    /** @return array<string, mixed> */
    private function articleJsonLd(Post $post, ?string $imageUrl, ?string $description): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $description,
            'image' => $imageUrl ? [$imageUrl] : null,
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at?->toIso8601String(),
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => route('articles.show', $post)],
            'author' => $post->author ? ['@type' => 'Person', 'name' => $post->author->name] : null,
            'publisher' => $this->organizationJsonLdNode(),
        ], fn ($value) => $value !== null);
    }

    /** @return array<int, array<string, string>> */
    private function postBreadcrumbs(Post $post): array
    {
        $crumbs = [['name' => 'Home', 'url' => route('home')]];

        if ($post->category) {
            $crumbs[] = ['name' => $post->category->name, 'url' => route('categories.show', $post->category)];
        }

        $crumbs[] = ['name' => $post->title, 'url' => route('articles.show', $post)];

        return $crumbs;
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    public function breadcrumbsJsonLd(array $crumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->values()->map(fn (array $crumb, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function websiteJsonLdNode(): array
    {
        return array_filter([
            '@type' => 'WebSite',
            'name' => $this->settings->siteName(),
            'url' => route('home'),
            'description' => $this->settings->siteDescription(),
            'publisher' => ['@id' => route('home').'#organization'],
        ], fn ($value) => $value !== null);
    }

    /** @return array<string, mixed> */
    private function organizationJsonLdNode(): array
    {
        return array_filter([
            '@type' => 'Organization',
            '@id' => route('home').'#organization',
            'name' => $this->settings->siteName(),
            'url' => route('home'),
            'logo' => $this->mediaUrl($this->settings->siteLogoMediaId()),
        ], fn ($value) => $value !== null);
    }
}
