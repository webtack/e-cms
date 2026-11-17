<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $collation = DB::connection()->getDriverName() === 'sqlite' ? 'binary' : 'utf8mb4_bin';

        Schema::create('media_relations', function (Blueprint $table) use ($collation) {
            $table->id();
            // RESTRICT: a file that is used cannot be deleted. `media.uuid` has a unique index.
            $table->char('file_uuid', 32);
            // Key of the owner type (`content_node`) and the owner's id; the owner is polymorphic, so no FK:
            // the hooks of the owner keep the rows in step (see docs/media-library.md).
            $table->string('owner_type', 64);
            $table->unsignedBigInteger('owner_id');
            // What the owner uses the file for (the field key for content).
            $table->string('role', 64);
            // `default` or a language code, as in `content_values_*`.
            $table->string('lang', 16)->default('default')->collation($collation);
            $table->timestamps();

            $table->unique(['file_uuid', 'owner_type', 'owner_id', 'role', 'lang'], 'media_relations_unique');
            $table->index(['owner_type', 'owner_id'], 'media_relations_owner');

            $table->foreign('file_uuid')->references('uuid')->on('media')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_relations');
    }
};
