<?php

namespace Webtack\Ecms\Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->withRole(Role::factory()->withPermissions(['users' => 'allow'])->create())->create();
    }

    public function test_manage_matrix(): void
    {
        $admin = User::factory()->create();
        $staff = $this->staff();
        $peer = $this->staff();

        $this->assertTrue(Gate::forUser($admin)->allows('manage', $admin));
        $this->assertTrue(Gate::forUser($admin)->allows('manage', $staff));
        $this->assertTrue(Gate::forUser($staff)->allows('manage', $peer));
        $this->assertTrue(Gate::forUser($staff)->allows('manage', $staff));
        $this->assertFalse(Gate::forUser($staff)->allows('manage', $admin));
    }

    public function test_assign_role_matrix(): void
    {
        $admin = User::factory()->create();
        $staff = $this->staff();
        $system = Role::query()->where('is_system', true)->sole();
        $plain = Role::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('assignRole', [User::class, $system]));
        $this->assertTrue(Gate::forUser($admin)->allows('assignRole', [User::class, $plain]));
        $this->assertTrue(Gate::forUser($staff)->allows('assignRole', [User::class, $plain]));
        $this->assertFalse(Gate::forUser($staff)->allows('assignRole', [User::class, $system]));
    }
}
