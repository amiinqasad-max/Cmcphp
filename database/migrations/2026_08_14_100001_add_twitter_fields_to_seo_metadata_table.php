<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 (§9): seo_metadata already covered OG + a bare twitter_card
 * selector, but not distinct Twitter/X title/description/image overrides.
 * SeoService (App\Services\SeoService) falls back to the OG fields when
 * these are blank, so nothing existing breaks — this only adds the
 * ability to diverge Twitter/X copy from Open Graph when an editor wants to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_metadata', function (Blueprint $table) {
            $table->string('twitter_title')->nullable()->after('twitter_card');
            $table->string('twitter_description')->nullable()->after('twitter_title');
            $table->foreignUuid('twitter_image_media_id')->nullable()->after('twitter_description')
                ->constrained('media')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seo_metadata', function (Blueprint $table) {
            $table->dropConstrainedForeignId('twitter_image_media_id');
            $table->dropColumn(['twitter_title', 'twitter_description']);
        });
    }
};
