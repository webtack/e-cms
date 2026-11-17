<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the service column `views` to `content_nodes`: the view counter of a node.
     * Existing nodes get `0` from the column default, so no backfill is needed.
     */
    public function up(): void
    {
        Schema::table('content_nodes', function (Blueprint $table) {
            $table->unsignedBigInteger('views')->default(0)->after('unpublished_at');
        });
    }

    /**
     * Reverse the migrations. The view counters are lost.
     */
    public function down(): void
    {
        Schema::table('content_nodes', function (Blueprint $table) {
            $table->dropColumn('views');
        });
    }
};
