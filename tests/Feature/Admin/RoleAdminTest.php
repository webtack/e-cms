<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class RoleAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/roles')->assertRedirect('/login');
    }

    public function test_the_list_shows_roles_with_user_counts_and_marks_the_system_one(): void
    {
        $editor = Role::factory()->create(['name' => 'Editor']);
        User::factory()->count(2)->withRole($editor)->create();

        $this->admin()->get('/admin/roles')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Roles/Index')
            ->has('table.rows', 2)
            ->where('table.rows.0.name', 'admin')
            ->where('table.rows.0.is_system', true)
            ->where('table.rows.0.users_count', 1)
            ->where('table.rows.1.name', 'Editor')
            ->where('table.rows.1.users_count', 2));
    }

    public function test_the_role_pages_need_the_roles_permission(): void
    {
        $role = Role::factory()->withPermissions(['users' => 'allow'])->create();
        $this->actingAs(User::factory()->withRole($role)->create());

        $this->get('/admin/roles')->assertForbidden();
        $this->get('/admin/roles/create')->assertForbidden();
        $this->post('/admin/roles', ['name' => 'X'])->assertForbidden();
        $this->get("/admin/roles/{$role->id}/edit")->assertForbidden();
        $this->delete("/admin/roles/{$role->id}")->assertForbidden();
    }

    public function test_a_role_is_created_without_rights_when_none_are_sent(): void
    {
        $this->admin()->post('/admin/roles', ['name' => 'Guest'])->assertRedirect('/admin/roles');

        $role = Role::where('name', 'Guest')->sole();
        $this->assertFalse($role->is_system);
        $this->assertSame(0, $role->permissions()->count());
    }

    public function test_a_duplicate_name_is_rejected(): void
    {
        Role::factory()->create(['name' => 'Editor']);

        $this->admin()->from('/admin/roles/create')->post('/admin/roles', ['name' => 'Editor'])
            ->assertRedirect('/admin/roles/create')->assertSessionHasErrors('name');
        $this->assertSame(2, Role::count());
    }

    public function test_the_matrix_is_saved_and_shown_again(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);

        $this->admin()->post('/admin/roles', [
            'name' => 'Editor',
            'permissions' => [
                'system' => ['menus' => true, 'taxonomy' => false],
                'nodes' => ['news' => 'own', 'home' => 'none', 'ghost' => 'any'],
                'media' => 'any',
            ],
        ])->assertRedirect();

        $role = Role::where('name', 'Editor')->sole();
        $this->assertEquals(['menus' => 'allow', 'node:news' => 'own', 'media' => 'any'], $role->permissions()->pluck('level', 'scope')->all());

        $this->get("/admin/roles/{$role->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Roles/Edit')
            ->where('role.permissions.system.menus', true)
            ->where('role.permissions.system.taxonomy', false)
            ->where('role.permissions.nodes.news', 'own')
            ->where('role.permissions.nodes.home', 'none')
            ->where('role.permissions.media', 'any')
            ->where('role.is_system', false)
            ->where('groups.0.kind', 'content')
            ->where('groups.0.types.0', ['key' => 'news', 'name' => 'News'])
            ->where('groups.1.types.0.key', 'home')
            ->where('groups.2.types', []));
    }

    public function test_invalid_levels_are_rejected(): void
    {
        $this->admin()->post('/admin/roles', ['name' => 'X', 'permissions' => ['nodes' => ['news' => 'root'], 'media' => 'all']])
            ->assertSessionHasErrors(['permissions.nodes.news', 'permissions.media']);
    }

    public function test_a_type_created_after_the_role_shows_none(): void
    {
        $role = Role::factory()->withPermissions(['media' => 'own'])->create();
        ContentType::create(['key' => 'late', 'name' => 'Late']);

        $this->admin()->get("/admin/roles/{$role->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('role.permissions.nodes.late', 'none'));
    }

    public function test_an_update_changes_the_rights_of_the_users_of_the_role_at_once(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $role = Role::factory()->withPermissions(['node:news' => 'own'])->create();
        $user = User::factory()->withRole($role)->create();
        $access = app(AccessResolver::class);

        $this->assertSame('own', $access->nodeLevel($user, 'news'));

        $this->admin()->from("/admin/roles/{$role->id}")->put("/admin/roles/{$role->id}", [
            'name' => 'Renamed',
            'permissions' => ['system' => ['menus' => true], 'nodes' => ['news' => 'any']],
        ])->assertRedirect("/admin/roles/{$role->id}");

        $this->assertSame('Renamed', $role->fresh()->name);
        $this->assertSame('any', $access->nodeLevel($user, 'news'));
        $this->assertTrue($access->allows($user, 'menus'));
    }

    public function test_keeping_the_own_name_on_update_is_not_a_duplicate(): void
    {
        $role = Role::factory()->create(['name' => 'Editor']);

        $this->admin()->from("/admin/roles/{$role->id}")->put("/admin/roles/{$role->id}", ['name' => 'Editor'])->assertRedirect("/admin/roles/{$role->id}");
    }

    public function test_the_system_role_is_viewable_but_cannot_be_changed_or_deleted(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $admin = Role::admin();

        $this->admin()->get("/admin/roles/{$admin->id}/edit")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('role.is_system', true)
            ->where('role.permissions.system.roles', true)
            ->where('role.permissions.nodes.news', 'any')
            ->where('role.permissions.media', 'any'));

        $this->put("/admin/roles/{$admin->id}", ['name' => 'Hacked', 'permissions' => []])->assertForbidden();
        $this->delete("/admin/roles/{$admin->id}")->assertForbidden();

        $this->assertSame('admin', $admin->fresh()->name);
        $this->assertSame(0, $admin->permissions()->count());
    }

    public function test_a_role_with_users_is_not_deleted(): void
    {
        $role = Role::factory()->create();
        User::factory()->withRole($role)->create();

        $this->admin()->delete("/admin/roles/{$role->id}")->assertRedirect()->assertSessionHas('error');

        $this->assertNotNull(Role::find($role->id));
    }

    public function test_an_empty_role_is_deleted_with_its_permissions(): void
    {
        $role = Role::factory()->withPermissions(['menus' => 'allow'])->create();

        $this->admin()->delete("/admin/roles/{$role->id}")->assertRedirect('/admin/roles')->assertSessionHas('success');

        $this->assertNull(Role::find($role->id));
        $this->assertDatabaseMissing('role_permissions', ['role_id' => $role->id]);
    }
}
