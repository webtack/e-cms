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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            // Stable external identifier (UUID v7); also the file name on disk.
            $table->uuid('uuid')->unique();
            // Upload profile key from config/files.php; the disk is stored too, so a later config change
            // does not move existing files.
            $table->string('profile', 64);
            $table->string('disk', 64);
            $table->string('path', 512);
            $table->string('original_name', 255);
            $table->string('mime', 127);
            $table->unsignedBigInteger('size');
            // sha256 of the content; informational, not unique (identical uploads make separate rows).
            $table->char('hash', 64);
            // Pixel dimensions, images only.
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->timestamps();

            // Used by `files:prune` to find files older than the grace period.
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
