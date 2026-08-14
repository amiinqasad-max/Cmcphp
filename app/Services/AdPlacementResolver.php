<?php

namespace App\Services;

use App\Enums\AdPlacementType;
use App\Enums\AdSlotStatus;
use App\Models\AdPlacement;
use App\Models\AdSlot;
use App\Models\Post;
use Illuminate\Support\Collection;

/**
 * Merges manual per-article ad placements with the automatic placement
 * rules (§23/§24) into the same block sequence ArticleContentRenderer
 * produces, so the article view can render content and ads from one
 * unified, ordered list. Never exceeds the configured maximum, never
 * places ads on excluded categories/posts/pages, and never runs on
 * articles too short to carry them.
 */
class AdPlacementResolver
{
    private ?AdSlot $cachedDefaultSlot = null;

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @param  Collection  $blocks  The ArticleContentRenderer block sequence.
     * @return Collection The same blocks with 'ad' entries spliced in.
     */
    public function interleave(Post $post, Collection $blocks): Collection
    {
        if ($this->isExcluded($post) || $this->tooShort($blocks)) {
            return $blocks;
        }

        $maxAds = $this->settings->maxAdsPerArticle();
        $insertions = $this->manualInsertions($post, $blocks);

        if ($insertions->count() < $maxAds && $this->settings->adsAutoPlacementEnabled()) {
            foreach ($this->autoInsertions($blocks, $insertions, $maxAds) as $index => $value) {
                $insertions->put($index, $value);
            }
        }

        $insertions = $insertions->sortKeys()->take($maxAds);

        return $this->splice($blocks, $insertions);
    }

    private function isExcluded(Post $post): bool
    {
        if (in_array($post->id, $this->settings->excludedAdPostIds(), true)) {
            return true;
        }

        return $post->category_id && in_array($post->category_id, $this->settings->excludedAdCategoryIds(), true);
    }

    private function tooShort(Collection $blocks): bool
    {
        return $blocks->where('isParagraph', true)->count() < $this->settings->minParagraphsRequiredForAds();
    }

    /**
     * @return Collection<int, AdPlacement> keyed by the block index to insert after (-1 = before everything)
     */
    private function manualInsertions(Post $post, Collection $blocks): Collection
    {
        $placements = AdPlacement::query()
            ->where('post_id', $post->id)
            ->where('status', AdSlotStatus::Active->value)
            ->with('adSlot')
            ->orderBy('position_order')
            ->get()
            ->filter(fn (AdPlacement $p) => $p->adSlot?->isActive());

        $result = collect();

        foreach ($placements as $placement) {
            $index = $this->resolveIndex($placement, $blocks);

            if ($index !== null && ! $result->has($index)) {
                $result->put($index, $placement);
            }
        }

        return $result;
    }

    private function resolveIndex(AdPlacement $placement, Collection $blocks): ?int
    {
        return match ($placement->placement_type) {
            AdPlacementType::Top => -1,
            AdPlacementType::Bottom => $blocks->count() - 1,
            AdPlacementType::Middle => intdiv(max($blocks->count() - 1, 0), 2),
            AdPlacementType::BeforeConclusion => max(0, $blocks->count() - 2),
            AdPlacementType::AfterParagraph => $this->indexOfNthParagraph($blocks, $placement->paragraph_index ?? 1),
            AdPlacementType::BeforeVideo => $this->indexOfVideo($blocks, $placement->video_position) - 1,
            AdPlacementType::AfterVideo => $this->indexOfVideo($blocks, $placement->video_position),
        };
    }

    private function indexOfNthParagraph(Collection $blocks, int $n): ?int
    {
        $seen = 0;

        foreach ($blocks as $index => $block) {
            if ($block['isParagraph']) {
                $seen++;

                if ($seen === $n) {
                    return $index;
                }
            }
        }

        return null;
    }

    private function indexOfVideo(Collection $blocks, ?int $position): ?int
    {
        if (! $position) {
            return null;
        }

        foreach ($blocks as $index => $block) {
            if ($block['type'] === 'video' && $block['video']?->position === $position) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Fills remaining ad slots (up to the configured maximum) after every
     * Nth paragraph (the configured minimum spacing), skipping any index a
     * manual placement already claimed.
     *
     * @return Collection<int, null>
     */
    private function autoInsertions(Collection $blocks, Collection $existing, int $maxAds): Collection
    {
        $spacing = max(1, $this->settings->minParagraphSpacing());
        $result = collect();
        $paragraphCount = 0;

        foreach ($blocks as $index => $block) {
            if ($existing->count() + $result->count() >= $maxAds) {
                break;
            }

            if (! $block['isParagraph']) {
                continue;
            }

            $paragraphCount++;

            if ($paragraphCount % $spacing === 0 && ! $existing->has($index)) {
                $result->put($index, null);
            }
        }

        return $result;
    }

    private function splice(Collection $blocks, Collection $insertions): Collection
    {
        $result = collect();

        if ($insertions->has(-1)) {
            $result->push($this->toAdBlock($insertions->get(-1)));
        }

        foreach ($blocks as $index => $block) {
            $result->push($block);

            if ($insertions->has($index)) {
                $result->push($this->toAdBlock($insertions->get($index)));
            }
        }

        return $result->values();
    }

    private function toAdBlock(?AdPlacement $placement): array
    {
        $adSlot = $placement?->adSlot ?? $this->defaultAdSlot();

        if (! $adSlot) {
            return ['type' => 'content', 'html' => '', 'video' => null, 'isParagraph' => false];
        }

        return [
            'type' => 'ad',
            'html' => null,
            'video' => null,
            'isParagraph' => false,
            'adSlot' => $adSlot,
            'adPlacementId' => $placement?->id,
        ];
    }

    private function defaultAdSlot(): ?AdSlot
    {
        return $this->cachedDefaultSlot ??= AdSlot::query()
            ->where('status', AdSlotStatus::Active->value)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->first();
    }
}
