<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

return new class extends Migration
{
    /**
     * Outside the base language a translation may hold only a description (or only a name/title), so the
     * text columns become nullable. A row with no value at all is never stored; the writers delete it.
     * No data changes: every existing row already has a name/title.
     */
    public function up(): void
    {
        Schema::table('taxonomy_term_translations', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
        });

        Schema::table('menu_item_translations', function (Blueprint $table) {
            $table->string('title')->nullable()->change();
        });
    }

    /**
     * Restores NOT NULL; refused while a row without a name/title exists, because it cannot be converted.
     */
    public function down(): void
    {
        if (DB::table('taxonomy_term_translations')->whereNull('name')->exists()
            || DB::table('menu_item_translations')->whereNull('title')->exists()) {
            throw ValidationException::withMessages(['rollback' => 'Translations without a name or title exist; remove or complete them before rolling back.']);
        }

        Schema::table('taxonomy_term_translations', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
        });

        Schema::table('menu_item_translations', function (Blueprint $table) {
            $table->string('title')->nullable(false)->change();
        });
    }
};
