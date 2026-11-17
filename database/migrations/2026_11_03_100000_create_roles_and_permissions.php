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
     * Creates `roles` and `role_permissions`, adds `users.role_id` / `users.is_active`, creates the system
     * role `admin` and leaves `role_id` nullable. Existing users of the host are NOT given any role: a user without
     * a role has no CMS access, and the installer (`ecms:install`) assigns `admin` explicitly.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // The system role `admin` is allowed everything and cannot be changed or deleted.
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            // System key (`users`, `roles`, `menus`, `settings`, `taxonomy`, `types`), `node:{type_key}` or `media`.
            $table->string('scope');
            // `allow` for system keys; `own` | `any` for `node:*` and `media`. A missing row means `none`.
            $table->string('level');
            $table->unique(['role_id', 'scope']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->after('role_id');
        });

        $now = now();
        DB::table('roles')->insert([
            'name' => 'admin',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Reverse the migrations (roles and their permissions are lost).
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn('is_active');
        });

        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
