<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index `content_nodes.views`: the public "popular" list and the admin table sort over it.
     * Kept apart from the migration that adds the column, so the two changes stay independent.
     */
    public function up(): void
    {
        Schema::table('content_nodes', function (Blueprint $table) {
            $table->index('views', 'content_nodes_views_index');
        });
    }

    /**
     * Reverse the migrations. Only the index goes; the counters stay.
     */
    public function down(): void
    {
        Schema::table('content_nodes', function (Blueprint $table) {
            $table->dropIndex('content_nodes_views_index');
        });
    }
};
