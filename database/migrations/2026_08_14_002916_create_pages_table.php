<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->foreignUuid('featured_image_media_id')->nullable()->constrained('media')->nullOnDelete();

            $table->string('status', 20)->default('draft'); // App\Enums\PageStatus
            $table->timestampTz('published_at')->nullable();

            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->string('canonical_url')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
