<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 3 (§2): PageResource's SEO section now uses the same polymorphic
 * `seo_metadata` relation as PostResource (giving Pages OG/Twitter/robots
 * support, which the old flat pages.seo_title/seo_description/canonical_url
 * columns never had). This is a one-time, production-safe data migration
 * copying any already-entered values across so nothing an editor typed is
 * lost. The flat columns are left in place (not dropped) — read-only
 * legacy columns are harmless, and dropping them is a separate, reversible
 * decision an operator can make later once confident nothing reads them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pages = DB::table('pages')
            ->whereNotNull('seo_title')
            ->orWhereNotNull('seo_description')
            ->orWhereNotNull('canonical_url')
            ->get(['id', 'seo_title', 'seo_description', 'canonical_url']);

        foreach ($pages as $page) {
            $exists = DB::table('seo_metadata')
                ->where('seoable_type', 'App\\Models\\Page')
                ->where('seoable_id', $page->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('seo_metadata')->insert([
                'id' => (string) Str::uuid(),
                'seoable_type' => 'App\\Models\\Page',
                'seoable_id' => $page->id,
                'seo_title' => $page->seo_title,
                'meta_description' => $page->seo_description,
                'canonical_url' => $page->canonical_url,
                'robots_index' => true,
                'robots_follow' => true,
                'twitter_card' => 'summary_large_image',
                'schema_type' => 'website',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: this only copies data forward, it
        // never deletes anything a down() migration would need to restore.
    }
};
