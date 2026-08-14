<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pre-aggregated daily stats so the dashboard never has to scan raw
        // engagement_events/video_progress/ad_events directly (§29/§54).
        // post_id IS NULL rows are site-wide totals for that date.
        Schema::create('daily_rollups', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignUuid('post_id')->nullable()->constrained('posts')->cascadeOnDelete();

            $table->unsignedInteger('pageviews')->default(0);
            $table->unsignedInteger('sessions_count')->default(0);
            $table->unsignedInteger('visitors_count')->default(0);

            $table->unsignedTinyInteger('avg_progress_percent')->nullable();
            $table->unsignedInteger('avg_reading_seconds')->nullable();
            $table->unsignedInteger('completions_count')->default(0);

            $table->unsignedInteger('video_plays')->default(0);
            $table->unsignedInteger('video_completions')->default(0);

            $table->unsignedInteger('ad_requests')->default(0);
            $table->unsignedInteger('ad_renders')->default(0);

            $table->timestamps();

            $table->unique(['date', 'post_id']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_rollups');
    }
};
