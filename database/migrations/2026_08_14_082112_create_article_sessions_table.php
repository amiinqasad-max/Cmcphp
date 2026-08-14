<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->string('session_id', 64);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->boolean('bottom_reached')->default(false);
            $table->boolean('is_completed')->default(false);

            $table->timestampTz('started_at');
            $table->timestampTz('last_activity_at');
            $table->timestampTz('completed_at')->nullable();

            $table->timestamps();

            $table->unique(['post_id', 'session_id']);
            $table->index('post_id');
            $table->index('session_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_sessions');
    }
};
