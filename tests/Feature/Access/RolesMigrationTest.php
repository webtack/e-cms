<?php

namespace Webtack\Ecms\Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class RolesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_03_100000_create_roles_and_permissions.php';

    private function rollback(): void
    {
        Artisan::call('migrate:rollback', ['--path' => $this->packagePath(self::MIGRATION), '--realpath' => true]);
    }

    private function migrate(): void
    {
        Artisan::call('migrate', ['--path' => $this->packagePath(self::MIGRATION), '--realpath' => true]);
    }

    /**
     * Step 3 of the package series: the migration never assigns a role (the demo's migration made every existing user
     * an administrator). Existing accounts keep their data, stay active and have no role until the installer or an
     * administrator gives them one.
     */
    public function test_existing_users_are_kept_active_and_without_a_role(): void
    {
        $users = User::factory()->count(2)->create();

        $this->rollback();

        $this->assertFalse(Schema::hasTable('roles'));
        $this->assertFalse(Schema::hasColumn('users', 'role_id'));
        $this->assertSame(2, DB::table('users')->count());

        $this->migrate();

        $this->assertTrue(Role::where('name', 'admin')->sole()->is_system);
        $this->assertSame(2, User::whereNull('role_id')->where('is_active', true)->count());
        $this->assertEqualsCanonicalizing($users->pluck('email')->all(), User::pluck('email')->all());
    }

    public function test_it_works_on_a_database_without_users(): void
    {
        $this->rollback();
        $this->migrate();

        $this->assertSame(1, Role::count());
        $this->assertTrue(Role::where('name', 'admin')->sole()->is_system);
        $this->assertSame(0, User::count());
    }

    public function test_a_user_without_a_role_can_be_created(): void
    {
        DB::table('users')->insert([
            'name' => 'No role', 'email' => 'x@example.com', 'password' => 'x', 'role_id' => null,
        ]);

        $this->assertNull(User::where('email', 'x@example.com')->sole()->role_id);
    }
}
