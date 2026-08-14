<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_placements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->nullable()->constrained('posts')->cascadeOnDelete();
            $table->foreignUuid('ad_slot_id')->constrained('ad_slots')->cascadeOnDelete();

            $table->string('placement_type', 20); // App\Enums\AdPlacementType
            $table->unsignedTinyInteger('paragraph_index')->nullable(); // for after_paragraph
            $table->unsignedTinyInteger('video_position')->nullable(); // 1-3, for before/after_video
            $table->unsignedSmallInteger('position_order')->default(0);
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_placements');
    }
};
