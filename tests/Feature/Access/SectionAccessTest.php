<?php

namespace Webtack\Ecms\Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Models\Vocabulary;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * System permissions close menus, taxonomy and the types (all three kinds) completely.
 */
class SectionAccessTest extends TestCase
{
    use RefreshDatabase;

    private function as(array $permissions): static
    {
        $role = Role::factory()->withPermissions($permissions)->create();

        return $this->actingAs(User::factory()->withRole($role)->create());
    }

    public function test_menu_is_closed_without_the_permission_and_open_with_it(): void
    {
        $menu = Menu::factory()->create();

        $this->as([])->get('/admin/menus')->assertForbidden();
        $this->as([])->get("/admin/menus/{$menu->key}/items")->assertForbidden();
        $this->as([])->post('/admin/menus', ['key' => 'x', 'name' => 'X'])->assertForbidden();
        $this->as([])->delete("/admin/menus/{$menu->key}")->assertForbidden();
        $this->assertNotNull(Menu::find($menu->id));

        $this->as(['menus' => 'allow'])->get('/admin/menus')->assertOk();
        $this->as(['menus' => 'allow'])->get("/admin/menus/{$menu->key}/items")->assertOk();
    }

    public function test_taxonomy_is_closed_without_the_permission_and_open_with_it(): void
    {
        $vocabulary = Vocabulary::factory()->create();

        $this->as(['menus' => 'allow'])->get('/admin/taxonomy')->assertForbidden();
        $this->as([])->get("/admin/taxonomy/{$vocabulary->key}/terms")->assertForbidden();
        $this->as([])->delete("/admin/taxonomy/{$vocabulary->key}")->assertForbidden();
        $this->assertNotNull(Vocabulary::find($vocabulary->id));

        $this->as(['taxonomy' => 'allow'])->get('/admin/taxonomy')->assertOk();
        $this->as(['taxonomy' => 'allow'])->get("/admin/taxonomy/{$vocabulary->key}/terms")->assertOk();
    }

    public function test_types_of_every_kind_need_the_types_permission(): void
    {
        foreach (['content' => 'content-types', 'page' => 'page-types', 'block' => 'block-types'] as $kind => $section) {
            ContentType::create(['key' => "t_{$kind}", 'name' => 'T', 'kind' => $kind]);

            $this->as(['menus' => 'allow'])->get("/admin/{$section}")->assertForbidden();
            $this->as([])->get("/admin/{$section}/t_{$kind}/edit")->assertForbidden();
            $this->as([])->get("/admin/{$section}/t_{$kind}/fields")->assertForbidden();
            $this->as([])->put("/admin/{$section}/t_{$kind}/display", [])->assertForbidden();
            $this->as([])->delete("/admin/{$section}/t_{$kind}")->assertForbidden();
            $this->assertNotNull(ContentType::findByKey("t_{$kind}"));

            $this->as(['types' => 'allow'])->get("/admin/{$section}")->assertOk();
            $this->as(['types' => 'allow'])->get("/admin/{$section}/t_{$kind}/fields")->assertOk();
        }
    }

    public function test_the_seo_tab_and_values_of_a_content_type_need_the_types_permission(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->as([])->get('/admin/content-types/news/seo')->assertForbidden();
        $this->as([])->put('/admin/content-types/news/values', [])->assertForbidden();
        $this->as(['types' => 'allow'])->get('/admin/content-types/news/seo')->assertOk();
    }

    public function test_dashboard_and_profile_are_open_to_every_active_user(): void
    {
        $this->as([])->get('/admin/dashboard')->assertOk();
        $this->as([])->get('/admin/profile')->assertOk();
    }

    public function test_the_type_list_lets_in_a_node_writer_and_shows_only_the_types_they_may_write(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'faq', 'name' => 'FAQ']);
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);

        $this->as(['node:news' => 'own'])->get('/admin/content-types')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canManage', false)
                ->has('table.rows', 1)
                ->where('table.rows.0.key', 'news'));
        // No type of the kind to write: closed; the management pages stay closed for a node writer.
        $this->as(['node:news' => 'own'])->get('/admin/page-types')->assertForbidden();
        $this->as(['node:news' => 'own'])->get('/admin/content-types/news/edit')->assertForbidden();
        $this->as(['node:news' => 'own'])->get('/admin/content-types/create')->assertForbidden();

        $this->as(['types' => 'allow'])->get('/admin/content-types')
            ->assertInertia(fn ($page) => $page->where('canManage', true)->has('table.rows', 2));
    }
}
