<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->uuid('id');
            $table->foreignUuid('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->uuid('parent_id')->nullable();

            $table->string('type', 20)->default('custom'); // App\Enums\MenuItemType: page|post|category|custom

            // Internal reference (type = page|post|category). No DB-level FK is
            // possible against three different target tables from one column
            // pair, so MenuItem::resolvedUrl()/isBroken() check existence (and
            // published status) at render/admin time instead — see §1 "Prevent
            // broken internal references where possible".
            $table->nullableUuidMorphs('linkable', 'menu_items_linkable_index');

            $table->string('label');
            $table->string('url')->nullable(); // type = custom, or an explicit override
            $table->string('target', 10)->default('_self'); // _self|_blank
            $table->string('rel')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            // Explicit primary() before the self-referencing FK — PostgreSQL
            // note documented in docs/ARCHITECTURE.md (chained ->primary()
            // compiles to a trailing ALTER TABLE that runs after foreign()).
            $table->primary('id');
            $table->foreign('parent_id')->references('id')->on('menu_items')->cascadeOnDelete();

            $table->index('parent_id');
            $table->index(['menu_id', 'parent_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
