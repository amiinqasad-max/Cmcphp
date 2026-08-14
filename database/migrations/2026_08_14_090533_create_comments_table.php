<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id');
            $table->foreignUuid('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name')->nullable(); // guest comments, if enabled
            $table->string('author_email')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->text('body');
            $table->string('status', 20)->default('pending'); // App\Enums\CommentStatus
            $table->timestamps();

            // Self-referencing FK: primary() must precede it on PostgreSQL —
            // see docs/ARCHITECTURE.md's Phase 2 implementation notes.
            $table->primary('id');
            $table->foreign('parent_id')->references('id')->on('comments')->cascadeOnDelete();

            $table->index('post_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
