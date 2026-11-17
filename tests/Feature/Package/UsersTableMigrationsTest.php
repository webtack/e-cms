<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webtack\Ecms\Tests\TestCase;

/**
 * The package extends the host's `users` table without touching the rows that already exist: no role, active, no
 * avatar, no chosen locale. The test starts from a host that has users and has not run the package migrations.
 */
class UsersTableMigrationsTest extends TestCase
{
    private function hostUser(string $email): void
    {
        DB::table('users')->insert(['name' => 'Host user', 'email' => $email, 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_existing_users_keep_their_rows_and_get_neutral_values(): void
    {
        $this->hostUser('a@example.com');
        $this->hostUser('b@example.com');
        $this->assertFalse(Schema::hasColumn('users', 'role_id'));

        $this->assertSame(0, Artisan::call('migrate'), Artisan::output());

        foreach (['locale', 'role_id', 'is_active', 'avatar'] as $column) {
            $this->assertTrue(Schema::hasColumn('users', $column), "users.{$column} is missing");
        }

        $users = DB::table('users')->orderBy('email')->get();
        $this->assertSame(['a@example.com', 'b@example.com'], $users->pluck('email')->all());
        $this->assertSame(['Host user', 'Host user'], $users->pluck('name')->all());
        $this->assertSame([null, null], $users->pluck('role_id')->all(), 'The migrations never assign a role');
        $this->assertEquals([1, 1], $users->pluck('is_active')->all());
        $this->assertSame([null, null], $users->pluck('avatar')->all());
        $this->assertSame([null, null], $users->pluck('locale')->all());
    }

    public function test_no_migration_of_the_package_creates_a_table_of_the_host(): void
    {
        foreach (glob($this->packagePath('database/migrations/*.php')) as $file) {
            foreach (['users', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens'] as $table) {
                $this->assertStringNotContainsString("Schema::create('{$table}'", file_get_contents($file), basename($file).' creates a host table');
            }
        }
    }

    public function test_the_avatar_migration_alone_keeps_the_rows(): void
    {
        $this->hostUser('a@example.com');
        Artisan::call('migrate');
        DB::table('users')->update(['avatar' => 'abc.jpg']);

        $path = $this->packagePath('database/migrations/2026_11_16_110000_add_avatar_to_users_table.php');
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--path' => $path, '--realpath' => true]));
        $this->assertFalse(Schema::hasColumn('users', 'avatar'));
        $this->assertSame(1, DB::table('users')->count());

        $this->assertSame(0, Artisan::call('migrate', ['--path' => $path, '--realpath' => true]));
        $this->assertNull(DB::table('users')->value('avatar'));
        $this->assertSame('a@example.com', DB::table('users')->value('email'));
    }
}
