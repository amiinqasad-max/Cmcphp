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
}
