<?php

namespace App\Models\Concerns;

use App\Models\SeoMetadata;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Shared by Post and Page. The polymorphic `seo_metadata` row holds the
 * "advanced" SEO fields (OG/Twitter/schema/robots); resolving effective,
 * defaulted SEO output for rendering is the job of SeoService (Phase 3).
 */
trait HasSeoMetadata
{
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }
}
