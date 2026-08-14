<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();

            // Deliberately a plain string, not a DB enum: config('menus.locations')
            // supplies the suggested list the Filament select offers, but the
            // column itself accepts anything so new locations never need a
            // migration (§1 — "additional menu locations... without redesigning").
            $table->string('location')->nullable();

            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('location');
        });

        if (DB::getDriverName() === 'pgsql') {
            // At most one *active* menu per location, enforced at the DB
            // level — a partial unique index rather than a plain unique
            // constraint so inactive/draft duplicates (e.g. while building a
            // replacement menu before switching it live) are still allowed.
            DB::statement(
                'CREATE UNIQUE INDEX menus_active_location_unique ON menus (location) '
                .'WHERE is_active = true AND location IS NOT NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
