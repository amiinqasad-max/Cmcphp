<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_metadata', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('seoable'); // seoable_type, seoable_id

            $table->string('seo_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('canonical_url')->nullable();

            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);

            $table->string('og_title')->nullable();
            $table->string('og_description')->nullable();
            $table->foreignUuid('og_image_media_id')->nullable()->constrained('media')->nullOnDelete();

            $table->string('twitter_card', 30)->default('summary_large_image');

            $table->string('schema_type', 30)->default('article'); // article|website|organization|none
            $table->jsonb('schema_json')->nullable(); // manual override

            $table->timestamps();

            $table->unique(['seoable_type', 'seoable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_metadata');
    }
};
