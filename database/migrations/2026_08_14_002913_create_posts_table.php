<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->uuid('id');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable(); // sanitized HTML from the article editor
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('featured_image_media_id')->nullable()->constrained('media')->nullOnDelete();

            $table->string('status', 20)->default('draft'); // App\Enums\PostStatus
            $table->timestampTz('published_at')->nullable();

            $table->uuid('next_article_id')->nullable();
            $table->string('next_article_mode', 20)->default('auto'); // App\Enums\NextArticleMode

            $table->unsignedSmallInteger('reading_time_minutes')->nullable();

            // Nullable overrides — fall back to global `settings` (reading.*) when null.
            $table->unsignedTinyInteger('completion_reading_threshold')->nullable();
            $table->unsignedTinyInteger('completion_required_videos')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // See categories migration: explicit primary() must precede the
            // self-referencing foreign() on PostgreSQL.
            $table->primary('id');
            $table->foreign('next_article_id')->references('id')->on('posts')->nullOnDelete();

            $table->index('slug');
            $table->index('status');
            $table->index('published_at');
            $table->index('category_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            // Generated full-text search column + GIN index (section 49: site search).
            DB::statement(<<<'SQL'
                ALTER TABLE posts ADD COLUMN search_vector tsvector
                GENERATED ALWAYS AS (
                    setweight(to_tsvector('english', coalesce(title, '')), 'A') ||
                    setweight(to_tsvector('english', coalesce(excerpt, '')), 'B') ||
                    setweight(to_tsvector('english', coalesce(regexp_replace(content, '<[^>]+>', ' ', 'g'), '')), 'C')
                ) STORED
            SQL);
            DB::statement('CREATE INDEX posts_search_vector_index ON posts USING GIN (search_vector)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
