<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Stored normalized: leading slash, no trailing slash (except
            // "/"), no query string — see Redirect::normalizePath(). The
            // unique index is what lets RedirectResolver do a single indexed
            // lookup instead of scanning.
            $table->string('source_path')->unique();
            $table->string('destination');

            $table->unsignedSmallInteger('status_code')->default(301); // App\Enums\RedirectStatus: 301|302|307|308
            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('hit_count')->default(0);
            $table->timestampTz('last_hit_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
