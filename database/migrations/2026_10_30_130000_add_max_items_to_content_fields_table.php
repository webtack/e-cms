<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('content_fields', function (Blueprint $table) {
            // Upper bound of the elements of a multiple field; 0 means no limit.
            $table->unsignedSmallInteger('max_items')->default(0)->after('is_multiple');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_fields', function (Blueprint $table) {
            $table->dropColumn('max_items');
        });
    }
};
