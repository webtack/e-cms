<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webtack\Ecms\Support\UserModel;

return new class extends Migration
{
    /**
     * Add `published_at`, `is_active` and `user_id` to `content_nodes`.
     *
     * Columns are added nullable, backfilled (`published_at = created_at`, `user_id` = the
     * administrator from ADMIN_EMAIL, like DatabaseSeeder) and only then made NOT NULL, so the
     * migration works on MySQL and SQLite. A non-empty table without the administrator fails
     * before any change is made.
     */
    public function up(): void
    {
        $adminId = null;

        if (DB::table('content_nodes')->exists()) {
            $email = env('ADMIN_EMAIL', app()->isProduction() ? null : 'admin@example.com');
            $adminId = $email ? UserModel::query()->where('email', $email)->value('id') : null;

            if ($adminId === null) {
                throw new RuntimeException(
                    'Cannot backfill content_nodes.user_id: no administrator found for ADMIN_EMAIL ['.($email ?? 'not set').']. '
                    .'Set ADMIN_EMAIL to an existing user and run the migration again.'
                );
            }
        }

        Schema::table('content_nodes', function (Blueprint $table) {
            $table->dateTime('published_at')->nullable()->after('slug');
            $table->boolean('is_active')->default(true)->after('published_at');
            // The author; restrictOnDelete keeps users with nodes from being deleted.
            $table->foreignId('user_id')->nullable()->after('is_active')->constrained()->restrictOnDelete();
        });

        if ($adminId !== null) {
            DB::table('content_nodes')->update(['published_at' => DB::raw('created_at'), 'user_id' => $adminId]);
        }

        Schema::table('content_nodes', function (Blueprint $table) {
            $table->dateTime('published_at')->nullable(false)->change();
            $table->foreignId('user_id')->nullable(false)->change();
            $table->index(['content_type', 'published_at']);
        });
    }

    /**
     * Reverse the migrations. The service attributes are lost.
     */
    public function down(): void
    {
        Schema::table('content_nodes', function (Blueprint $table) {
            $table->dropIndex(['content_type', 'published_at']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['is_active', 'published_at']);
        });
    }
};
