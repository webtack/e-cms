<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations: translated descriptions of files, one row per file and additional language.
     * `media.description` stays the base description. Deleting a file deletes its translations.
     */
    public function up(): void
    {
        $binary = DB::connection()->getDriverName() === 'sqlite' ? 'binary' : 'utf8mb4_bin';

        Schema::create('media_translations', function (Blueprint $table) use ($binary) {
            $table->id();
            $table->char('file_uuid', 32);
            // A normalised language code, binary like `content_values_*` (`pt-BR` differs from `pt-br`).
            $table->string('lang', 16)->collation($binary);
            $table->text('description');
            $table->timestamps();

            $table->unique(['file_uuid', 'lang']);

            $table->foreign('file_uuid')->references('uuid')->on('media')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations. Translated descriptions are lost; base descriptions stay.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_translations');
    }
};
