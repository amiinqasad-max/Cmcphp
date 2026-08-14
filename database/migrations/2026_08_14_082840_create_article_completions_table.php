<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The authoritative record: a row here means the server itself
        // verified reading progress + all required videos, never a
        // client-asserted flag (§10, §46).
        Schema::create('article_completions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('session_id', 64);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedTinyInteger('reading_progress_percent');
            $table->unsignedTinyInteger('videos_completed_count');
            $table->unsignedTinyInteger('videos_required_count');

            $table->timestampTz('completed_at');
            $table->foreignUuid('next_post_id')->nullable()->constrained('posts')->nullOnDelete();

            $table->timestamps();

            $table->unique(['post_id', 'session_id']);
            $table->index('post_id');
            $table->index('completed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_completions');
    }
};
