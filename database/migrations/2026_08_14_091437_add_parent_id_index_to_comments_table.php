<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unlike Laravel's foreignId()->constrained() shorthand, a manual
        // uuid()+foreign() pair (needed here for the self-referencing FK
        // ordering fix) does not auto-index the column — comment threads
        // querying replies by parent_id were doing a full scan.
        Schema::table('comments', function (Blueprint $table) {
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['parent_id']);
        });
    }
};
