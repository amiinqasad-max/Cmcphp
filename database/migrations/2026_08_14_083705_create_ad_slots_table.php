<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_slots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('ad_client'); // AdSense publisher id, e.g. ca-pub-XXXX
            $table->string('ad_slot_code'); // AdSense slot id
            $table->string('format', 20)->default('auto'); // App\Enums\AdSlotFormat
            $table->boolean('is_responsive')->default(true);
            $table->boolean('is_default')->default(false); // used to fill automatic placements with no specific slot chosen
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_slots');
    }
};
