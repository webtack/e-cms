<?php

namespace Webtack\Ecms\Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class SharedPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_carries_the_permissions_of_the_user(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        $role = Role::factory()->withPermissions(['menus' => 'allow', 'node:news' => 'own', 'media' => 'any'])->create();

        $this->actingAs(User::factory()->withRole($role)->create())->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.can.system.menus', true)
                ->where('auth.user.can.system.taxonomy', false)
                ->where('auth.user.can.system.users', false)
                ->where('auth.user.can.nodes.news', 'own')
                ->where('auth.user.can.kinds.content', true)
                ->where('auth.user.can.kinds.page', false)
                ->where('auth.user.can.media', 'any')
                ->where('auth.user.can.edit', true));
    }

    public function test_admin_has_everything(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->actingAs(User::factory()->create())->get('/admin/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.can.system.roles', true)
                ->where('auth.user.can.nodes.news', 'any')
                ->where('auth.user.can.media', 'any'));
    }

    public function test_a_guest_has_no_user(): void
    {
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
    }
}
