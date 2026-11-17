<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class TestUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_builds_the_configured_user_model_with_a_role(): void
    {
        $role = Role::factory()->create(['name' => 'editor']);

        $user = User::factory()->withRole($role)->create();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($role->id, $user->role->id);
        $this->assertFalse(app(AccessResolver::class)->allows($user, 'users'));
    }

    public function test_default_factory_user_is_an_administrator(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(app(AccessResolver::class)->allows($user, 'users'));
    }
}
