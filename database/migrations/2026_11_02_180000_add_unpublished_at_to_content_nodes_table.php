<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the optional `unpublished_at` to `content_nodes`: the moment the node stops being visible.
     * Existing nodes keep `NULL` (no end date), so they stay visible as before.
     */
    public function up(): void
    {
        Schema::table('content_nodes', function (Blueprint $table) {
            $table->dateTime('unpublished_at')->nullable()->after('published_at');
        });
    }

    /**
     * Reverse the migrations. The unpublish dates are lost.
     */
    public function down(): void
    {
        Schema::table('content_nodes', function (Blueprint $table) {
            $table->dropColumn('unpublished_at');
        });
    }
};
