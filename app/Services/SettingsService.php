<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Typed, cached access to the `settings` table. Full admin UI for every
 * group (general/reading/seo/social/analytics/ads/content/security/
 * performance) is Phase 3 scope; the accessors below cover exactly the
 * settings Phases 4-8 need to be genuinely configurable rather than
 * hard-coded, per docs/ARCHITECTURE.md §54.
 */
class SettingsService
{
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("settings.{$group}.{$key}", function () use ($group, $key, $default) {
            $value = Setting::query()->where('group', $group)->where('key', $key)->value('value');

            return $value ?? $default;
        });
    }

    public function set(string $group, string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value]);
    }

    // ---- Reading / completion (§10, §11, §32) ----

    public function completionReadingThreshold(): int
    {
        return (int) $this->get('reading', 'completion_threshold', 90);
    }

    public function completionRequiredVideos(): int
    {
        return (int) $this->get('reading', 'required_videos', 3);
    }

    public function autoNextEnabled(): bool
    {
        return (bool) $this->get('reading', 'auto_next_enabled', true);
    }

    public function autoNextDelaySeconds(): float
    {
        return (float) $this->get('reading', 'auto_next_delay_seconds', 1.5);
    }

    public function relatedArticlesCount(): int
    {
        return (int) $this->get('reading', 'related_articles_count', 3);
    }

    // ---- Advertisements (§24, §32) ----

    public function maxAdsPerArticle(): int
    {
        return (int) $this->get('ads', 'max_ads_per_article', 5);
    }

    public function minParagraphSpacing(): int
    {
        return (int) $this->get('ads', 'min_paragraph_spacing', 4);
    }

    public function minParagraphsRequiredForAds(): int
    {
        return (int) $this->get('ads', 'min_paragraphs_required', 4);
    }

    public function adsAutoPlacementEnabled(): bool
    {
        return (bool) $this->get('ads', 'auto_placement_enabled', true);
    }

    /** @return array<int, string> */
    public function excludedAdCategoryIds(): array
    {
        return (array) $this->get('ads', 'excluded_category_ids', []);
    }

    /** @return array<int, string> */
    public function excludedAdPostIds(): array
    {
        return (array) $this->get('ads', 'excluded_post_ids', []);
    }

    // ---- Content / comments (§31, §32) ----

    public function commentsEnabled(): bool
    {
        return (bool) $this->get('content', 'comments_enabled', true);
    }

    public function commentsRequireApproval(): bool
    {
        return (bool) $this->get('content', 'comments_require_approval', true);
    }

    // ---- Site identity (§7 "General Settings") ----

    public function siteName(): string
    {
        return (string) $this->get('general', 'site_name', config('app.name'));
    }

    public function siteTagline(): ?string
    {
        return $this->get('general', 'site_tagline');
    }

    public function siteDescription(): ?string
    {
        return $this->get('general', 'site_description');
    }

    /** UUID of a `media` row, or null. Resolve with app(SeoService::class) helpers, not directly. */
    public function siteLogoMediaId(): ?string
    {
        return $this->get('general', 'logo_media_id');
    }

    public function siteFaviconMediaId(): ?string
    {
        return $this->get('general', 'favicon_media_id');
    }

    public function defaultSocialImageMediaId(): ?string
    {
        return $this->get('general', 'default_social_image_media_id');
    }

    // ---- Contact (§7) ----

    public function contactEmail(): ?string
    {
        return $this->get('contact', 'email');
    }

    public function supportEmail(): ?string
    {
        return $this->get('contact', 'support_email');
    }

    public function contactPhone(): ?string
    {
        return $this->get('contact', 'phone');
    }

    public function contactAddress(): ?string
    {
        return $this->get('contact', 'address');
    }

    // ---- Social links (§7) ----

    /**
     * Platform => URL. Deliberately one JSON map under a single key rather
     * than one settings row per platform, so a new platform never needs a
     * migration or a hard-coded field — see §7 "do not hard-code the
     * platforms into public templates in a way that prevents future
     * expansion". Blank/absent entries are filtered out.
     *
     * @return array<string, string>
     */
    public function socialLinks(): array
    {
        return array_filter((array) $this->get('social', 'links', []));
    }

    // ---- SEO defaults (§2, §10) ----

    public function seoDefaultTitle(): ?string
    {
        return $this->get('seo', 'default_title');
    }

    public function seoDefaultDescription(): ?string
    {
        return $this->get('seo', 'default_description');
    }

    public function seoDefaultRobotsIndex(): bool
    {
        return (bool) $this->get('seo', 'default_robots_index', true);
    }

    public function seoDefaultRobotsFollow(): bool
    {
        return (bool) $this->get('seo', 'default_robots_follow', true);
    }

    public function seoDefaultOgImageMediaId(): ?string
    {
        return $this->get('seo', 'default_og_image_media_id');
    }

    public function seoDefaultTwitterCard(): string
    {
        return (string) $this->get('seo', 'default_twitter_card', 'summary_large_image');
    }
}
