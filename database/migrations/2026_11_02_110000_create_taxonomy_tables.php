<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Terms are linked by `uuid`, a vocabulary by its immutable `key`; the numeric `id` is never a
     * link. Every foreign key is RESTRICT (or CASCADE for translations only): the database refuses to delete a vocabulary or a
     * term that still has terms below it, because cascades fire no model events.
     */
    public function up(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $binary = $isSqlite ? 'binary' : 'utf8mb4_bin';

        Schema::create('taxonomy_vocabularies', function (Blueprint $table) {
            $table->id();
            // Immutable machine name: terms and fields (`settings.vocabulary`) refer to a vocabulary by it.
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // `max_depth` (optional positive integer): the deepest level a term may have.
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('taxonomy_terms', function (Blueprint $table) use ($isSqlite) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('vocabulary_key', 64);
            $table->uuid('parent_uuid')->nullable();
            // Unique case-insensitively inside a vocabulary: MySQL's default collation already is,
            // SQLite needs NOCASE spelled out (as for content_nodes.slug).
            $slug = $table->string('slug', 190);
            if ($isSqlite) {
                $slug->collation('nocase');
            }
            $table->integer('weight')->default(0);
            // `{uuid}.{ext}` of a file of the `image` profile; usage is tracked in media_relations.
            $table->string('image', 64)->nullable();
            $table->timestamps();

            $table->unique(['vocabulary_key', 'slug']);
            $table->index(['parent_uuid', 'weight']);

            $table->foreign('vocabulary_key')->references('key')->on('taxonomy_vocabularies')->restrictOnDelete();
        });

        // A self-reference needs the unique index of `uuid` to exist already, so it is added apart.
        Schema::table('taxonomy_terms', function (Blueprint $table) {
            $table->foreign('parent_uuid')->references('uuid')->on('taxonomy_terms')->restrictOnDelete();
        });

        Schema::create('taxonomy_term_translations', function (Blueprint $table) use ($binary) {
            $table->id();
            $table->uuid('term_uuid');
            // `default` or a normalised language code, as in `content_values_*`.
            $table->string('lang', 16)->collation($binary);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['term_uuid', 'lang']);

            $table->foreign('term_uuid')->references('uuid')->on('taxonomy_terms')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations. Fields of type `term` must be deleted first: their values lie in
     * `content_values_string` and would be left pointing at nothing.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxonomy_term_translations');
        Schema::dropIfExists('taxonomy_terms');
        Schema::dropIfExists('taxonomy_vocabularies');
    }
};
