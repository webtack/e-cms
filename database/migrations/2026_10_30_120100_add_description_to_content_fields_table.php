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
            // Instruction for the node editor, shown under the field's label.
            $table->text('description')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_fields', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
