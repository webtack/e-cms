<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Access\LastAdminException;
use Webtack\Ecms\Access\LastAdminGuard;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Files\FileRelations;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['name' => 'Boss']);
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin);
    }

    private function payload(array $over = []): array
    {
        return ['name' => 'Anna', 'email' => 'anna@example.com', 'password' => 'secret-pass', 'role_id' => Role::factory()->create()->id, ...$over];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_the_pages_need_the_users_permission(): void
    {
        $role = Role::factory()->withPermissions(['roles' => 'allow'])->create();
        $user = User::factory()->withRole($role)->create();
        $this->actingAs($user);

        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
        $this->post('/admin/users', $this->payload())->assertForbidden();
        $this->get("/admin/users/{$this->admin->id}/edit")->assertForbidden();
        $this->put("/admin/users/{$this->admin->id}", $this->payload())->assertForbidden();
        $this->delete("/admin/users/{$this->admin->id}")->assertForbidden();
        $this->assertSame(1, User::where('email', $this->admin->email)->count());
    }

    public function test_the_list_shows_name_email_role_and_activity(): void
    {
        $editor = Role::factory()->create(['name' => 'Editor']);
        User::factory()->withRole($editor)->inactive()->create(['name' => 'Zed', 'email' => 'zed@example.com']);

        $this->asAdmin()->get('/admin/users')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Index')
            ->has('table.rows', 2)
            ->where('table.rows.0', fn ($row) => $row['name'] === 'Boss' && $row['role'] === 'admin' && $row['is_active'] === true && $row['is_self'] === true)
            ->where('table.rows.1.email', 'zed@example.com')
            ->where('table.rows.1.role', 'Editor')
            ->where('table.rows.1.is_active', false));
    }

    public function test_a_user_is_created_active_verified_and_can_log_in(): void
    {
        $roleId = Role::factory()->create()->id;

        $this->asAdmin()->post('/admin/users', $this->payload(['role_id' => $roleId]))->assertRedirect('/admin/users');

        $user = User::where('email', 'anna@example.com')->sole();
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame($roleId, $user->role_id);

        auth()->logout();
        $this->post('/login', ['email' => 'anna@example.com', 'password' => 'secret-pass'])->assertRedirect('/admin/dashboard');
    }

    public function test_a_user_can_be_created_inactive(): void
    {
        $this->asAdmin()->post('/admin/users', $this->payload(['is_active' => false]))->assertRedirect();

        $this->assertFalse(User::where('email', 'anna@example.com')->sole()->is_active);
    }

    public function test_a_duplicate_email_is_rejected(): void
    {
        $this->asAdmin()->from('/admin/users/create')->post('/admin/users', $this->payload(['email' => $this->admin->email]))
            ->assertRedirect('/admin/users/create')->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    public function test_a_role_is_required_and_must_exist(): void
    {
        $this->asAdmin()->post('/admin/users', $this->payload(['role_id' => null]))->assertSessionHasErrors('role_id');
        $this->post('/admin/users', $this->payload(['role_id' => 9999]))->assertSessionHasErrors('role_id');
        $this->post('/admin/users', collect($this->payload())->except('role_id')->all())->assertSessionHasErrors('role_id');
        $this->assertSame(1, User::count());
    }

    public function test_the_password_is_required_on_create_and_has_a_minimum(): void
    {
        $this->asAdmin()->post('/admin/users', $this->payload(['password' => '']))->assertSessionHasErrors('password');
        $this->post('/admin/users', $this->payload(['password' => 'short']))->assertSessionHasErrors('password');
    }

    public function test_an_empty_password_keeps_the_current_one_and_a_new_one_replaces_it(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $data = ['name' => 'Renamed', 'email' => $user->email, 'role_id' => $user->role_id, 'is_active' => true];

        $this->asAdmin()->from("/admin/users/{$user->id}")->put("/admin/users/{$user->id}", [...$data, 'password' => ''])->assertRedirect("/admin/users/{$user->id}");
        $this->assertSame('Renamed', $user->fresh()->name);
        $this->assertTrue(password_verify('old-password', $user->fresh()->password));

        $this->put("/admin/users/{$user->id}", [...$data, 'password' => 'brand-new-pass'])->assertRedirect();
        $this->assertTrue(password_verify('brand-new-pass', $user->fresh()->password));
    }

    public function test_a_reset_password_signs_the_target_out_but_not_the_administrator(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $staleHash = Auth::guard()->hashPasswordForCookie($user->password);

        $this->asAdmin()->put("/admin/users/{$user->id}", ['name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id, 'is_active' => true, 'password' => 'brand-new-pass'])->assertRedirect();
        $this->get('/admin/users')->assertOk();

        $this->flushSession();
        Auth::forgetGuards();
        $this->withSession(['password_hash_web' => $staleHash])->actingAs($user->fresh())->get('/admin/profile')->assertRedirect('/login');
    }

    public function test_keeping_the_own_email_on_update_is_not_a_duplicate(): void
    {
        $user = User::factory()->create();

        $this->asAdmin()->from("/admin/users/{$user->id}")->put("/admin/users/{$user->id}", ['name' => 'X', 'email' => $user->email, 'role_id' => $user->role_id])->assertRedirect("/admin/users/{$user->id}");
    }

    public function test_a_role_change_applies_from_the_next_request_of_that_user(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $editor = Role::factory()->withPermissions(['node:news' => 'any'])->create();
        $none = Role::factory()->create();
        $user = User::factory()->withRole($editor)->create();

        $this->actingAs($user)->get('/admin/content-types/news/nodes')->assertOk();

        $this->actingAs($this->admin)->put("/admin/users/{$user->id}", ['name' => $user->name, 'email' => $user->email, 'role_id' => $none->id])->assertRedirect();

        $this->actingAs($user->fresh())->get('/admin/content-types/news/nodes')->assertForbidden();
    }

    public function test_a_user_cannot_change_their_own_role_or_deactivate_themselves(): void
    {
        $other = Role::factory()->create();
        $base = ['name' => 'Boss', 'email' => $this->admin->email];

        $this->asAdmin()->put("/admin/users/{$this->admin->id}", [...$base, 'role_id' => $other->id, 'is_active' => true])->assertSessionHasErrors('role_id');
        $this->put("/admin/users/{$this->admin->id}", [...$base, 'role_id' => $this->admin->role_id, 'is_active' => false])->assertSessionHasErrors('is_active');

        $fresh = $this->admin->fresh();
        $this->assertSame($this->admin->role_id, $fresh->role_id);
        $this->assertTrue($fresh->is_active);

        $this->put("/admin/users/{$this->admin->id}", [...$base, 'name' => 'Renamed', 'role_id' => $this->admin->role_id, 'is_active' => true])->assertRedirect();
        $this->assertSame('Renamed', $this->admin->fresh()->name);
    }

    public function test_deactivation_keeps_the_records_of_the_author(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        $author = User::factory()->create();
        $node = app(ContentNodeCreator::class)->create($type, ['title' => 'T'], authorId: $author->id);

        $this->asAdmin()->put("/admin/users/{$author->id}", ['name' => $author->name, 'email' => $author->email, 'role_id' => $author->role_id, 'is_active' => false])->assertRedirect();

        $this->assertFalse($author->fresh()->is_active);
        $this->assertSame($author->id, $node->fresh()->user_id);

        $this->put("/admin/users/{$author->id}", ['name' => $author->name, 'email' => $author->email, 'role_id' => $author->role_id, 'is_active' => true])->assertRedirect();
        $this->assertTrue($author->fresh()->is_active);
    }

    public function test_a_user_without_records_is_deleted(): void
    {
        $user = User::factory()->create();

        $this->asAdmin()->delete("/admin/users/{$user->id}")->assertRedirect('/admin/users')->assertSessionHas('success');

        $this->assertNull(User::find($user->id));
    }

    public function test_the_list_rows_carry_the_avatar_url_or_null(): void
    {
        Storage::fake('public');
        $this->admin->forceFill(['avatar' => '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.jpg'])->save();
        User::factory()->create(['name' => 'Zed']);

        $this->asAdmin()->get('/admin/users')->assertInertia(fn (Assert $page) => $page
            ->where('table.rows.0.avatar', Storage::disk('public')->url('0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.jpg'))
            ->where('table.rows.1.avatar', null));
    }

    public function test_deleting_a_user_with_an_avatar_releases_the_relation_and_keeps_the_file(): void
    {
        $user = User::factory()->create();
        $uuid = File::newUuid();
        File::factory()->create(['uuid' => $uuid, 'profile' => 'avatar', 'path' => "{$uuid}.jpg", 'mime' => 'image/jpeg']);
        $user->forceFill(['avatar' => "{$uuid}.jpg"])->save();
        app(FileRelations::class)->sync(User::MEDIA_OWNER, $user->id, 'avatar', 'default', [$uuid]);

        $this->asAdmin()->delete("/admin/users/{$user->id}")->assertRedirect('/admin/users');

        $this->assertNull(User::find($user->id));
        $this->assertSame(0, DB::table('media_relations')->where('owner_type', 'user')->where('owner_id', $user->id)->count());
        $this->assertNotNull(File::findByUuid($uuid));
    }

    public function test_an_author_is_not_deleted(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        $author = User::factory()->create();
        app(ContentNodeCreator::class)->create($type, ['title' => 'T'], authorId: $author->id);

        $this->asAdmin()->delete("/admin/users/{$author->id}")->assertRedirect()->assertSessionHas('error');

        $this->assertNotNull(User::find($author->id));
    }

    // Over HTTP the guard cannot fire any more: only an active administrator manages administrators, so another
    // active one always exists. It stays as the guard against a concurrent write and is checked directly.
    public function test_the_only_active_administrator_cannot_be_deactivated_deleted_or_moved(): void
    {
        $guard = app(LastAdminGuard::class);
        $ran = false;

        $this->expectException(LastAdminException::class);

        try {
            $guard->run($this->admin, true, function () use (&$ran) {
                $ran = true;
            });
        } finally {
            $this->assertFalse($ran);
            $this->assertTrue($this->admin->fresh()->is_active);
        }
    }

    public function test_one_of_two_administrators_can_be_changed_but_not_both(): void
    {
        $second = User::factory()->create();
        $guard = app(LastAdminGuard::class);

        $this->assertSame('done', $guard->run($second, true, fn () => 'done'));
        $second->update(['is_active' => false]);

        $this->expectException(LastAdminException::class);
        $guard->run($this->admin, true, fn () => 'done');
    }

    public function test_an_inactive_administrator_does_not_count(): void
    {
        $inactive = User::factory()->inactive()->create();
        $guard = app(LastAdminGuard::class);

        // Deleting the inactive one is fine; `$this->admin` stays the only active one.
        $this->assertSame('done', $guard->run($inactive, true, fn () => 'done'));

        $this->expectException(LastAdminException::class);
        $guard->run($this->admin, true, fn () => 'done');
    }

    public function test_the_forms_carry_the_roles(): void
    {
        Role::factory()->create(['name' => 'Editor']);

        $this->asAdmin()->get('/admin/users/create')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Create')
            ->where('roles.0.name', 'admin')
            ->where('roles.1.name', 'Editor'));

        $this->get("/admin/users/{$this->admin->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Edit')
            ->where('user.email', $this->admin->email)
            ->where('user.is_self', true)
            ->missing('user.password'));
    }

    private function staff(array $permissions = ['users' => 'allow']): User
    {
        return User::factory()->withRole(Role::factory()->withPermissions($permissions)->create())->create();
    }

    private function adminRoleId(): int
    {
        return $this->admin->role_id;
    }

    public function test_a_non_administrator_cannot_create_or_promote_to_admin(): void
    {
        $this->actingAs($this->staff());
        $other = User::factory()->withRole(Role::factory()->create())->create();

        $this->post('/admin/users', $this->payload(['role_id' => $this->adminRoleId()]))->assertForbidden();
        $this->put("/admin/users/{$other->id}", ['name' => $other->name, 'email' => $other->email, 'role_id' => $this->adminRoleId()])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'anna@example.com']);
        $this->assertNotSame($this->adminRoleId(), $other->fresh()->role_id);
    }

    public function test_a_non_administrator_cannot_touch_an_administrator(): void
    {
        $this->actingAs($this->staff());
        $before = $this->admin->only(['name', 'email', 'role_id', 'is_active', 'password']);

        $this->get("/admin/users/{$this->admin->id}/edit")->assertForbidden();
        $this->put("/admin/users/{$this->admin->id}", ['name' => 'X', 'email' => $this->admin->email, 'password' => 'new-secret-pass', 'role_id' => $this->adminRoleId()])->assertForbidden();
        $this->put("/admin/users/{$this->admin->id}", ['name' => 'X', 'email' => $this->admin->email, 'role_id' => Role::factory()->create()->id, 'is_active' => '0'])->assertForbidden();
        $this->delete("/admin/users/{$this->admin->id}")->assertForbidden();

        $this->assertSame($before, $this->admin->fresh()->only(['name', 'email', 'role_id', 'is_active', 'password']));
    }

    public function test_a_non_administrator_manages_other_roles_even_with_the_users_right(): void
    {
        $this->actingAs($this->staff());
        $peer = $this->staff();

        $this->get("/admin/users/{$peer->id}/edit")->assertOk();
        $this->put("/admin/users/{$peer->id}", ['name' => 'Renamed', 'email' => $peer->email, 'role_id' => $peer->role_id, 'is_active' => '0'])->assertRedirect();
        $this->assertSame('Renamed', $peer->fresh()->name);
        $this->post('/admin/users', $this->payload())->assertRedirect('/admin/users');
        $this->delete("/admin/users/{$peer->id}")->assertRedirect('/admin/users');
    }

    public function test_an_unknown_role_is_still_a_validation_error_for_a_non_administrator(): void
    {
        $this->actingAs($this->staff());

        $this->post('/admin/users', $this->payload(['role_id' => 99999]))->assertSessionHasErrors('role_id');
        $this->post('/admin/users', $this->payload(['role_id' => 'abc']))->assertSessionHasErrors('role_id');
    }

    public function test_an_administrator_keeps_full_management_of_administrators(): void
    {
        $second = User::factory()->create();
        $editor = User::factory()->withRole(Role::factory()->create())->create();

        $this->asAdmin()->post('/admin/users', $this->payload(['email' => 'adm@example.com', 'role_id' => $this->adminRoleId()]))->assertRedirect('/admin/users');
        $this->put("/admin/users/{$editor->id}", ['name' => $editor->name, 'email' => $editor->email, 'role_id' => $this->adminRoleId()])->assertRedirect();
        $this->put("/admin/users/{$second->id}", ['name' => 'Renamed', 'email' => $second->email, 'password' => 'new-secret-pass', 'role_id' => $this->adminRoleId()])->assertRedirect();
        $this->delete("/admin/users/{$second->id}")->assertRedirect('/admin/users');
        $this->assertNull(User::find($second->id));
    }

    public function test_the_list_flags_administrators_as_not_manageable_for_a_non_administrator(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->get('/admin/users')->assertInertia(fn (Assert $page) => $page
            ->where('table.rows', fn ($rows) => collect($rows)->every(
                fn ($row) => $row['can_manage'] === ($row['role'] !== 'admin'),
            ) && collect($rows)->contains('role', 'admin')));

        $this->actingAs($this->admin)->get('/admin/users')->assertInertia(fn (Assert $page) => $page
            ->where('table.rows', fn ($rows) => collect($rows)->every(fn ($row) => $row['can_manage'] === true)));
    }

    public function test_the_role_select_omits_admin_for_a_non_administrator_only(): void
    {
        $staff = $this->staff();
        $peer = $this->staff();

        $this->actingAs($staff)->get('/admin/users/create')->assertInertia(fn (Assert $page) => $page
            ->where('roles', fn ($roles) => ! collect($roles)->contains('name', 'admin')));
        $this->get("/admin/users/{$peer->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('roles', fn ($roles) => ! collect($roles)->contains('name', 'admin')
                && collect($roles)->contains('id', $peer->role_id)));

        $this->actingAs($this->admin)->get('/admin/users/create')->assertInertia(fn (Assert $page) => $page
            ->where('roles', fn ($roles) => collect($roles)->contains('name', 'admin')));
    }
}
