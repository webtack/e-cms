<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * The "Child types" tab: the types table of a section restricted to the direct children of one type.
 */
class ContentTypeChildrenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function tree(string $kind = 'content'): void
    {
        ContentType::create(['key' => 'common', 'name' => 'Common', 'kind' => $kind]);
        ContentType::create(['key' => 'institutions', 'name' => 'Institutions', 'kind' => $kind, 'parent_type' => 'common']);
        ContentType::create(['key' => 'deep', 'name' => 'Deep', 'kind' => $kind, 'parent_type' => 'institutions']);
        ContentType::create(['key' => 'sibling', 'name' => 'Sibling', 'kind' => $kind]);
    }

    public function test_the_route_has_its_name(): void
    {
        $this->assertSame('/admin/content-types/common/children', route('admin.content-types.children', 'common', false));
        $this->assertSame('/admin/page-types/home/children', route('admin.page-types.children', 'home', false));
        $this->assertSame('/admin/block-types/hero/children', route('admin.block-types.children', 'hero', false));
    }

    public function test_only_direct_children_are_listed(): void
    {
        $this->tree();

        $this->admin()->get('/admin/content-types/common/children')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentTypes/Index')
                ->where('canManage', true)
                ->where('parent.key', 'common')
                ->where('type.key', 'common')
                ->where('type.children_count', 1)
                ->where('table.meta.total', 1)
                ->where('table.rows.0.key', 'institutions')
                ->where('table.rows.0.parent_name', 'Common')
                ->where('table.rows.0.children_count', 1));
    }

    public function test_a_type_without_children_gives_an_empty_table(): void
    {
        $this->tree();

        $this->admin()->get('/admin/content-types/sibling/children')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('table.rows', 0)
                ->where('type.children_count', 0)
                ->where('type.ancestors', []));
    }

    public function test_search_works_inside_the_tab(): void
    {
        $this->tree();
        ContentType::create(['key' => 'museums', 'name' => 'Museums', 'parent_type' => 'common']);

        $this->admin()->get('/admin/content-types/common/children?q=muse')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 1)
                ->where('table.rows.0.key', 'museums'));
    }

    public function test_the_page_and_block_kinds_work(): void
    {
        foreach (['page' => 'page-types', 'block' => 'block-types'] as $kind => $section) {
            $this->tree($kind);

            $this->admin()->get("/admin/{$section}/common/children")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('section.kind', $kind)
                    ->where('table.rows.0.key', 'institutions'));

            ContentType::query()->where('kind', $kind)->orderByDesc('id')->get()->each->delete();
        }
    }

    public function test_a_key_of_another_kind_is_not_found(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);

        $this->admin()->get('/admin/content-types/home/children')->assertNotFound();
    }

    public function test_the_tab_needs_the_types_right(): void
    {
        $this->tree();
        $user = User::factory()->withRole(Role::factory()->withPermissions(['node:common' => 'any'])->create())->create();

        $this->actingAs($user)->get('/admin/content-types/common/children')->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->tree();

        $this->get('/admin/content-types/common/children')->assertRedirect('/login');
    }

    public function test_the_nav_prop_carries_the_ancestors_root_first(): void
    {
        $this->tree();

        $this->admin()->get('/admin/content-types/deep/children')
            ->assertInertia(fn (Assert $page) => $page
                ->where('type.ancestors', [['key' => 'common', 'name' => 'Common'], ['key' => 'institutions', 'name' => 'Institutions']]));
    }
}
