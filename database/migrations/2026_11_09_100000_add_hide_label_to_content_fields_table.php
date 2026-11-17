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
            // Public pages do not print the field name as a label when set; existing fields keep it.
            $table->boolean('hide_label')->default(false)->after('is_translatable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_fields', function (Blueprint $table) {
            $table->dropColumn('hide_label');
        });
    }
};
