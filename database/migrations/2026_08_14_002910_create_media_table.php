<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id');
            $table->string('disk');
            $table->string('path');
            $table->string('url')->nullable();
            $table->string('type', 20); // App\Enums\MediaType
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');

            $table->string('title')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('caption')->nullable();
            $table->text('description')->nullable();

            // Video-only metadata (nullable for images/documents)
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->uuid('thumbnail_media_id')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->primary('id');
            $table->foreign('thumbnail_media_id')->references('id')->on('media')->nullOnDelete();

            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
