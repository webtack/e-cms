<?php

namespace Webtack\Ecms\Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Webtack\Ecms\Access\AccessResolver;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class AccessResolverTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $permissions): User
    {
        return User::factory()->withRole(Role::factory()->withPermissions($permissions)->create())->create();
    }

    public function test_admin_is_allowed_everything_including_types_created_later(): void
    {
        $admin = User::factory()->create();
        ContentType::create(['key' => 'late', 'name' => 'Late']);
        $access = app(AccessResolver::class);

        foreach (AccessResolver::SYSTEM as $permission) {
            $this->assertTrue($access->allows($admin, $permission));
        }
        $this->assertSame('any', $access->nodeLevel($admin, 'late'));
        $this->assertSame('any', $access->mediaLevel($admin));
        $this->assertTrue($access->canEditAnyContent($admin));
    }

    public function test_only_the_system_role_is_an_administrator(): void
    {
        $access = app(AccessResolver::class);

        $this->assertTrue($access->isAdministrator(User::factory()->create()));
        $this->assertFalse($access->isAdministrator($this->userWith(['users' => 'allow', 'roles' => 'allow'])));
    }

    public function test_a_role_without_rows_has_nothing(): void
    {
        $user = $this->userWith([]);
        $access = app(AccessResolver::class);

        $this->assertFalse($access->allows($user, 'menus'));
        $this->assertSame('none', $access->nodeLevel($user, 'news'));
        $this->assertSame('none', $access->mediaLevel($user));
        $this->assertFalse($access->canEditAnyContent($user));
    }

    public function test_system_permissions_and_gates(): void
    {
        $user = $this->userWith(['menus' => 'allow']);

        $this->assertTrue(Gate::forUser($user)->allows('menus'));
        $this->assertFalse(Gate::forUser($user)->allows('taxonomy'));
    }

    public function test_levels_of_types_and_media(): void
    {
        $user = $this->userWith(['node:news' => 'own', 'node:page' => 'any', 'media' => 'own']);
        $access = app(AccessResolver::class);

        $this->assertSame('own', $access->nodeLevel($user, 'news'));
        $this->assertSame('any', $access->nodeLevel($user, 'page'));
        $this->assertSame('none', $access->nodeLevel($user, 'other'));
        $this->assertSame('own', $access->mediaLevel($user));
    }

    public function test_a_junk_level_is_none(): void
    {
        $user = $this->userWith(['node:news' => 'allow']);

        $this->assertSame('none', app(AccessResolver::class)->nodeLevel($user, 'news'));
    }

    public function test_can_edit_any_content_needs_own_or_any_on_an_existing_type_or_media(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $access = app(AccessResolver::class);

        $this->assertTrue($access->canEditAnyContent($this->userWith(['node:news' => 'own'])));
        $this->assertTrue($access->canEditAnyContent($this->userWith(['media' => 'any'])));
        // A row of a type that no longer exists is ignored.
        $this->assertFalse($access->canEditAnyContent($this->userWith(['node:gone' => 'any'])));
        $this->assertFalse($access->canEditAnyContent($this->userWith(['menus' => 'allow'])));
    }

    public function test_flush_picks_up_changed_permissions(): void
    {
        $role = Role::factory()->withPermissions(['menus' => 'allow'])->create();
        $user = User::factory()->withRole($role)->create();
        $access = app(AccessResolver::class);

        $this->assertTrue($access->allows($user, 'menus'));

        $role->permissions()->delete();
        $this->assertTrue($access->allows($user, 'menus'));

        $access->flush();
        $this->assertFalse($access->allows($user, 'menus'));
    }

    public function test_for_client_lists_every_existing_type(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'faq', 'name' => 'FAQ']);
        $user = $this->userWith(['users' => 'allow', 'node:news' => 'own']);

        $this->assertEquals([
            'system' => ['users' => true, 'roles' => false, 'menus' => false, 'settings' => false, 'taxonomy' => false, 'types' => false],
            'nodes' => ['news' => 'own', 'faq' => 'none'],
            'kinds' => ['content' => true, 'page' => false, 'block' => false],
            'media' => 'none',
            'edit' => true,
        ], app(AccessResolver::class)->forClient($user));
    }
}
