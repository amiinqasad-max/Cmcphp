<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_videos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignUuid('media_id')->constrained('media')->restrictOnDelete();

            $table->unsignedTinyInteger('position'); // 1..3, see App\Enums\VideoPosition-style validation in the model
            $table->unsignedInteger('duration_seconds')->nullable(); // auto-detected, never hard-coded — §6
            $table->boolean('is_required')->default(true);
            $table->unsignedTinyInteger('completion_threshold')->default(90); // percent
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->unique(['post_id', 'position']);
            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_videos');
    }
};
