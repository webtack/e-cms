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
        Schema::create('content_fields', function (Blueprint $table) {
            $table->id();
            $table->string('content_type', 64);
            // Immutable; unique within the type. (content_type, key) identifies the field.
            $table->string('key', 64);
            $table->string('name');
            // Key of a field type from the registry (config/content.php).
            $table->string('type', 32);
            $table->boolean('is_multiple')->default(false);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_translatable')->default(false);
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('weight')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['content_type', 'key']);
            $table->foreign('content_type')->references('key')->on('content_types')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_fields');
    }
};
