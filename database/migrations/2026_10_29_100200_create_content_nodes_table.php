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
        Schema::create('content_nodes', function (Blueprint $table) {
            $table->id();
            // Stable external identifier (UUID v7) used to match nodes between environments.
            $table->uuid('uuid')->unique();
            $table->string('content_type', 64);
            $slug = $table->string('slug', 190)->unique();
            // The slug is unique case-insensitively: MySQL's default collation already is,
            // SQLite needs NOCASE spelled out.
            if (DB::connection()->getDriverName() === 'sqlite') {
                $slug->collation('nocase');
            }
            $table->timestamps();

            $table->index(['content_type', 'id']);
            // Target of the composite FK from the value tables (value type = node type).
            $table->unique(['id', 'content_type']);
            $table->foreign('content_type')->references('key')->on('content_types')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_nodes');
    }
};
