<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignUuid('post_video_id')->constrained('post_videos')->cascadeOnDelete();
            $table->string('session_id', 64);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->decimal('watched_seconds', 8, 2)->default(0);
            $table->unsignedTinyInteger('max_percent_reached')->default(0);
            $table->unsignedSmallInteger('play_count')->default(0);
            $table->unsignedSmallInteger('pause_count')->default(0);
            $table->boolean('is_completed')->default(false);

            $table->timestampTz('started_at');
            $table->timestampTz('last_watched_at');
            $table->timestampTz('completed_at')->nullable();

            $table->timestamps();

            $table->unique(['post_video_id', 'session_id']);
            $table->index('post_id');
            $table->index('post_video_id');
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_progress');
    }
};
