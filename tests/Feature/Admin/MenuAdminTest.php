<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class MenuAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/menus')->assertRedirect('/login');
        $this->get('/admin/menus/create')->assertRedirect('/login');
        $this->get('/admin/menus/main/items')->assertRedirect('/login');
    }

    public function test_index_lists_menus_with_item_counts_ordered_by_name(): void
    {
        $second = Menu::factory()->create(['key' => 'footer', 'name' => 'Footer', 'settings' => ['max_depth' => 2]]);
        $first = Menu::factory()->create(['key' => 'main', 'name' => 'Головне']);
        MenuItem::factory()->in($first)->count(5)->create();

        $this->admin()->get('/admin/menus')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Menus/Index')
                ->has('table.rows', 2)
                ->where('table.rows.0.key', $second->key)
                ->where('table.rows.0.max_depth', 2)
                ->where('table.rows.0.items_count', 0)
                ->where('table.rows.1.key', 'main')
                ->where('table.rows.1.items_count', 5)
                ->where('table.rows.1.max_depth', null));
    }

    public function test_index_searches_name_key_and_description(): void
    {
        Menu::factory()->create(['key' => 'main', 'name' => 'Header']);
        Menu::factory()->create(['key' => 'footer', 'name' => 'Bottom', 'description' => 'links at the end']);

        $this->admin()->get('/admin/menus?q=links')->assertInertia(fn (Assert $page) => $page
            ->has('table.rows', 1)->where('table.rows.0.key', 'footer'));
        $this->admin()->get('/admin/menus?q=mai')->assertInertia(fn (Assert $page) => $page
            ->has('table.rows', 1)->where('table.rows.0.key', 'main'));
    }

    public function test_create_form_renders(): void
    {
        $this->admin()->get('/admin/menus/create')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Menus/Create'));
    }

    public function test_store_creates_a_menu_and_opens_its_items(): void
    {
        $this->admin()->post('/admin/menus', ['key' => 'main', 'name' => 'Головне меню', 'description' => 'Шапка', 'max_depth' => '3'])
            ->assertRedirect('/admin/menus/main/items')
            ->assertSessionHas('success');

        $menu = Menu::findByKey('main');
        $this->assertSame('Головне меню', $menu->name);
        $this->assertSame(3, $menu->maxDepth());
    }

    public function test_store_without_depth_means_no_limit(): void
    {
        $this->admin()->post('/admin/menus', ['key' => 'main', 'name' => 'Main', 'max_depth' => ''])->assertSessionHasNoErrors();

        $this->assertNull(Menu::findByKey('main')->maxDepth());
    }

    public function test_store_rejects_a_taken_key_a_bad_key_and_a_bad_depth(): void
    {
        Menu::factory()->create(['key' => 'main']);

        $this->admin()->post('/admin/menus', ['key' => 'main', 'name' => 'X'])->assertSessionHasErrors('key');
        $this->admin()->post('/admin/menus', ['key' => 'Bad Key', 'name' => 'X'])->assertSessionHasErrors('key');
        $this->admin()->post('/admin/menus', ['key' => 'footer', 'name' => 'X', 'max_depth' => '0'])->assertSessionHasErrors('max_depth');
        $this->admin()->post('/admin/menus', ['key' => 'footer', 'name' => ''])->assertSessionHasErrors('name');
        $this->assertNull(Menu::findByKey('footer'));
    }

    public function test_edit_shows_the_menu(): void
    {
        Menu::factory()->create(['key' => 'main', 'name' => 'Main', 'settings' => ['max_depth' => 4]]);

        $this->admin()->get('/admin/menus/main/edit')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Menus/Edit')
            ->where('menu.key', 'main')
            ->where('menu.max_depth', 4));
    }

    public function test_unknown_key_answers_404(): void
    {
        $this->admin()->get('/admin/menus/missing/edit')->assertNotFound();
        $this->admin()->put('/admin/menus/missing', ['name' => 'X'])->assertNotFound();
        $this->admin()->delete('/admin/menus/missing')->assertNotFound();
        $this->admin()->get('/admin/menus/missing/items')->assertNotFound();
    }

    public function test_update_changes_name_but_never_the_key(): void
    {
        Menu::factory()->create(['key' => 'main', 'name' => 'Old']);

        $this->admin()->from('/admin/menus/main')->put('/admin/menus/main', ['key' => 'other', 'name' => 'New', 'description' => 'D'])
            ->assertRedirect('/admin/menus/main')->assertSessionHas('success');

        $menu = Menu::findByKey('main');
        $this->assertSame('New', $menu->name);
        $this->assertSame('D', $menu->description);
        $this->assertNull(Menu::findByKey('other'));
    }

    public function test_update_clears_the_depth(): void
    {
        Menu::factory()->create(['key' => 'main', 'settings' => ['max_depth' => 3]]);

        $this->admin()->put('/admin/menus/main', ['name' => 'X', 'max_depth' => ''])->assertSessionHasNoErrors();

        $this->assertNull(Menu::findByKey('main')->maxDepth());
    }

    public function test_update_rejects_a_depth_below_the_actual_one(): void
    {
        $menu = Menu::factory()->create(['key' => 'main']);
        $a = MenuItem::factory()->in($menu)->create();
        $b = MenuItem::factory()->under($a)->create();
        MenuItem::factory()->under($b)->create();

        $this->admin()->put('/admin/menus/main', ['name' => 'X', 'max_depth' => '2'])->assertSessionHasErrors('max_depth');
        $this->assertNull(Menu::findByKey('main')->maxDepth());

        $this->admin()->put('/admin/menus/main', ['name' => 'X', 'max_depth' => '3'])->assertSessionHasNoErrors();
        $this->assertSame(3, Menu::findByKey('main')->maxDepth());
    }

    public function test_destroy_deletes_an_empty_menu(): void
    {
        Menu::factory()->create(['key' => 'main']);

        $this->admin()->delete('/admin/menus/main')->assertRedirect('/admin/menus')->assertSessionHas('success');

        $this->assertNull(Menu::findByKey('main'));
    }

    public function test_destroy_keeps_a_menu_with_items(): void
    {
        $menu = Menu::factory()->create(['key' => 'main']);
        MenuItem::factory()->in($menu)->create();

        $this->admin()->from('/admin/menus')->delete('/admin/menus/main')
            ->assertRedirect('/admin/menus')
            ->assertSessionHas('error', 'Cannot delete a menu that has items.');

        $this->assertNotNull(Menu::findByKey('main'));
    }
}
