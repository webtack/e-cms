<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations: a translated display name per file and language; a row now holds a name, a description
     * or both, so `description` becomes nullable. `media.original_name` is never touched.
     */
    public function up(): void
    {
        Schema::table('media_translations', function (Blueprint $table) {
            $table->string('name')->nullable()->after('lang');
            $table->text('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations. Translated names are lost; rows that held only a name are removed.
     */
    public function down(): void
    {
        Schema::table('media_translations', function (Blueprint $table) {
            $table->dropColumn('name');
        });
        DB::table('media_translations')->whereNull('description')->delete();
        Schema::table('media_translations', function (Blueprint $table) {
            $table->text('description')->nullable(false)->change();
        });
    }
};
