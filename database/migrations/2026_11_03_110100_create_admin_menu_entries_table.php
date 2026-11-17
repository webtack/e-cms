<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The admin menu shown to everyone: a row with `type_key` is a link to a type, a row without it is a group
     * (with a translatable `title`). `parent_id` is null for root rows. Depth 2 and the one-link-per-type rule
     * are kept by `Webtack\Ecms\Navigation\AdminMenu`; `type_key` is unique here too. A later migration adds `node_uuid`
     * for links to nodes. See docs/admin-navigation-menu.md.
     */
    public function up(): void
    {
        Schema::create('admin_menu_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('type_key', 64)->nullable()->unique();
            // `{"default": "...", "en": "..."}` for a group; null for a link.
            $table->json('title')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['parent_id', 'position']);
            $table->foreign('parent_id')->references('id')->on('admin_menu_entries')->cascadeOnDelete();
            $table->foreign('type_key')->references('key')->on('content_types')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_menu_entries');
    }
};
