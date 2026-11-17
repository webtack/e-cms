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
        Schema::create('content_types', function (Blueprint $table) {
            $table->id();
            // Immutable machine key (^[a-z][a-z0-9_]*$); other tables reference it, not `id`.
            $table->string('key', 64);
            $table->string('name');
            $table->text('description')->nullable();
            // Organisational tree of types: points to the parent type's key.
            $table->string('parent_type', 64)->nullable();
            $table->string('kind', 32)->default('content');
            $table->json('settings')->nullable();
            $table->timestamps();

            // Declared before the FK: MySQL needs the referenced unique key to exist first.
            $table->unique('key');
            $table->foreign('parent_type')->references('key')->on('content_types')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_types');
    }
};
