<?php

namespace Webtack\Ecms\Tests\Feature\Menus;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Files\Owners\MenuItemOwner;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;
use Webtack\Ecms\Models\MenuItemTranslation;
use Webtack\Ecms\Tests\TestCase;

class MenuItemOwnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_owner_type_is_registered(): void
    {
        $this->assertSame(MenuItemOwner::class, config('files.owners.menu_item'));
    }

    public function test_an_item_is_described_by_its_default_title_with_a_link_to_its_form(): void
    {
        $menu = Menu::factory()->create(['key' => 'main']);
        $titled = MenuItem::factory()->in($menu)->create();
        MenuItemTranslation::factory()->create(['item_uuid' => $titled->uuid, 'lang' => 'default', 'title' => 'Про нас']);
        MenuItemTranslation::factory()->create(['item_uuid' => $titled->uuid, 'lang' => 'en', 'title' => 'About us']);
        $untitled = MenuItem::factory()->in($menu)->create();

        $described = (new MenuItemOwner)->describe([$titled->id, $untitled->id, 9999]);

        $this->assertSame([
            $titled->id => ['label' => 'Про нас', 'url' => "/admin/menus/main/items/{$titled->uuid}/edit"],
            $untitled->id => ['label' => $untitled->uuid, 'url' => "/admin/menus/main/items/{$untitled->uuid}/edit"],
        ], $described);
        $this->assertSame([], (new MenuItemOwner)->describe([]));
    }

    public function test_many_items_are_described_in_a_fixed_number_of_queries(): void
    {
        $menu = Menu::factory()->create();
        $ids = MenuItem::factory()->in($menu)->count(4)->create()->pluck('id')->all();

        DB::enableQueryLog();
        (new MenuItemOwner)->describe($ids);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(2, $queries);
    }
}
