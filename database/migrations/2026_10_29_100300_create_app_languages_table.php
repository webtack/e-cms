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
        Schema::create('app_languages', function (Blueprint $table) {
            $table->id();
            // Normalised BCP-47 code only, compared case-sensitively (binary collation).
            $code = $table->string('code', 16)->unique();
            $code->collation(DB::connection()->getDriverName() === 'sqlite' ? 'binary' : 'utf8mb4_bin');
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('weight')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_languages');
    }
};
