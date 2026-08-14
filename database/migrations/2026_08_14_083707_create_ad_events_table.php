<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Internal Ad Analytics only — explicitly NOT official Google
        // AdSense reporting (§26/§27). High volume, append-only.
        Schema::create('ad_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('ad_slot_id')->constrained('ad_slots')->cascadeOnDelete();
            $table->foreignUuid('ad_placement_id')->nullable()->constrained('ad_placements')->nullOnDelete();
            $table->foreignUuid('post_id')->nullable()->constrained('posts')->cascadeOnDelete();
            $table->string('session_id', 64);
            $table->string('event_type', 20); // App\Enums\AdEventType
            $table->uuid('event_uuid')->unique(); // client-generated idempotency key
            $table->timestampTz('created_at');

            $table->index('ad_slot_id');
            $table->index('post_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_events');
    }
};
