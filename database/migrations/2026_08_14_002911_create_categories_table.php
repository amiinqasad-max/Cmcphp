<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id');
            $table->uuid('parent_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignUuid('image_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            // Explicit primary() before the self-referencing FK: Laravel defers a
            // chained ->primary() column modifier to a trailing ALTER TABLE on
            // PostgreSQL, which runs *after* foreign() commands and breaks a
            // self-referencing FK ("no unique constraint matching given keys").
            $table->primary('id');
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
            $table->index('parent_id');
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
