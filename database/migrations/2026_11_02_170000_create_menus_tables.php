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
     * Items are linked by `uuid`, a menu by its immutable `key`; the numeric `id` is never a link.
     * Every foreign key is RESTRICT (or CASCADE for translations only): the database refuses to
     * delete a menu or an item that still has items below it, because cascades fire no model events.
     */
    public function up(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $binary = $isSqlite ? 'binary' : 'utf8mb4_bin';

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            // Immutable machine name: items refer to a menu by it.
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // `max_depth` (optional positive integer): the deepest level an item may have.
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('menu_key', 64);
            $table->uuid('parent_uuid')->nullable();
            $table->integer('weight')->default(0);
            // Relative (`/`, `#`, `?`) or absolute (`http(s)`, `mailto`, `tel`); NULL is a group heading.
            $table->string('url', 2048)->nullable();
            $table->string('target', 8)->default('_self');
            $table->string('css_class')->nullable();
            // `{uuid}.{ext}` of a file of the `image` profile; usage is tracked in media_relations.
            $table->string('image', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('published_at')->nullable();
            $table->dateTime('unpublished_at')->nullable();
            $table->timestamps();

            $table->index('menu_key');
            $table->index(['parent_uuid', 'weight']);

            $table->foreign('menu_key')->references('key')->on('menus')->restrictOnDelete();
        });

        // A self-reference needs the unique index of `uuid` to exist already, so it is added apart.
        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreign('parent_uuid')->references('uuid')->on('menu_items')->restrictOnDelete();
        });

        Schema::create('menu_item_translations', function (Blueprint $table) use ($binary) {
            $table->id();
            $table->uuid('item_uuid');
            // `default` or a normalised language code, as in `content_values_*`.
            $table->string('lang', 16)->collation($binary);
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['item_uuid', 'lang']);

            $table->foreign('item_uuid')->references('uuid')->on('menu_items')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations. Delete menus through the model first so image relations in
     * `media_relations` are released.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_item_translations');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
