<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * A host account that existed before the CMS (`role_id` is null) has no rights, and it is a legal target of the
 * user management actions, so an administrator can give it a role.
 */
class RolelessUserTest extends TestCase
{
    use RefreshDatabase;

    private function roleless(): User
    {
        return User::factory()->create(['role_id' => null]);
    }

    public function test_every_scope_is_none_and_every_system_permission_is_denied(): void
    {
        $type = ContentType::factory()->create();
        $user = $this->roleless();
        $access = app(AccessResolver::class);

        foreach (AccessResolver::SYSTEM as $permission) {
            $this->assertFalse($access->allows($user, $permission), "{$permission} must be denied");
        }

        $this->assertSame(AccessResolver::NONE, $access->level($user, "node:{$type->key}"));
        $this->assertSame(AccessResolver::NONE, $access->nodeLevel($user, $type));
        $this->assertSame(AccessResolver::NONE, $access->mediaLevel($user));
        $this->assertFalse($access->isAdministrator($user));
        $this->assertFalse($access->canEditAnyContent($user));
    }

    public function test_the_client_permissions_are_empty_and_do_not_throw(): void
    {
        $type = ContentType::factory()->create();
        $client = app(AccessResolver::class)->forClient($this->roleless());

        $this->assertSame([], array_filter($client['system']));
        $this->assertSame([$type->key => AccessResolver::NONE], $client['nodes']);
        $this->assertSame(AccessResolver::NONE, $client['media']);
        $this->assertFalse($client['edit']);
        $this->assertSame([], array_filter($client['kinds']));
    }

    public function test_a_roleless_user_cannot_open_the_admin_panel(): void
    {
        $this->actingAs($this->roleless())->get('/admin/users')->assertForbidden();
    }

    public function test_the_admin_users_list_labels_the_user_without_a_role(): void
    {
        $roleless = $this->roleless();

        $this->actingAs(User::factory()->create())
            ->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->where('table.rows', fn ($rows) => collect($rows)->firstWhere('email', $roleless->email)['role'] === __('No role')));
    }

    public function test_the_administrator_can_manage_a_roleless_user_and_give_it_a_role(): void
    {
        $target = $this->roleless();
        $admin = User::factory()->create();
        $editor = Role::factory()->create(['name' => 'editor']);

        $gate = Gate::forUser($admin);
        $this->assertTrue($gate->allows('manage', $target));
        $this->assertTrue($gate->allows('assignRole', [User::class, $editor]));

        $this->actingAs($admin)->get("/admin/users/{$target->id}/edit")->assertOk();
        $this->actingAs($admin)->put("/admin/users/{$target->id}", [
            'name' => $target->name, 'email' => $target->email, 'role_id' => $editor->id, 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame($editor->id, $target->fresh()->role_id);
    }

    public function test_a_holder_of_the_users_permission_manages_a_roleless_user_but_not_an_administrator(): void
    {
        $manager = User::factory()->withRole(Role::factory()->withPermissions(['users' => 'allow'])->create(['name' => 'hr']))->create();
        $gate = Gate::forUser($manager);

        $this->assertTrue($gate->allows('manage', $this->roleless()));
        $this->assertFalse($gate->allows('manage', User::factory()->create()), 'An administrator is protected');
    }

    public function test_a_roleless_user_can_be_deleted_by_an_administrator(): void
    {
        $target = $this->roleless();

        $this->actingAs(User::factory()->create())->delete("/admin/users/{$target->id}")->assertSessionHasNoErrors();

        $this->assertModelMissing($target);
    }
}
