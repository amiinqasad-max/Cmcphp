<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // High-volume, append-only raw event log — bigint identity rather
        // than UUID, no `updated_at` (rows are never updated). Rolled up
        // into daily_rollups and pruned by a scheduled command (Phase 8/10).
        Schema::create('engagement_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 30); // App\Enums\EventType
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignUuid('post_video_id')->nullable()->constrained('post_videos')->nullOnDelete();
            $table->string('session_id', 64);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('event_uuid')->unique(); // client-generated idempotency key
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at');

            $table->index('event_type');
            $table->index('post_id');
            $table->index('session_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_events');
    }
};
