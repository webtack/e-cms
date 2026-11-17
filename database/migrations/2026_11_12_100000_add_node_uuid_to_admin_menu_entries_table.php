<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A third kind of admin menu row: a link to a node (`node_uuid` set). It is unique, so a node is in the menu at
     * most once, and deleting the node removes its row. The label is never stored; it is read from the node title
     * at display time. See docs/admin-navigation-menu.md.
     */
    public function up(): void
    {
        Schema::table('admin_menu_entries', function (Blueprint $table) {
            $table->uuid('node_uuid')->nullable()->unique()->after('type_key');
            $table->foreign('node_uuid')->references('uuid')->on('content_nodes')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_menu_entries', function (Blueprint $table) {
            $table->dropForeign(['node_uuid']);
            $table->dropUnique(['node_uuid']);
            $table->dropColumn('node_uuid');
        });
    }
};
