<?php

namespace Webtack\Ecms\Tests\Feature\Menus;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;
use Webtack\Ecms\Tests\TestCase;

class MenuModelTest extends TestCase
{
    use RefreshDatabase;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->menu = Menu::factory()->create(['key' => 'main', 'name' => 'Main menu']);
    }

    private function item(?MenuItem $parent = null, int $weight = 0, array $extra = [], ?Menu $menu = null): MenuItem
    {
        return MenuItem::create([
            'menu_key' => ($menu ?? $this->menu)->key,
            'parent_uuid' => $parent?->uuid,
            'weight' => $weight,
            ...$extra,
        ]);
    }

    /** @return array<string, array<int, string>> */
    private function errorsOf(callable $action): array
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('No validation error was thrown.');
    }

    public function test_a_menu_is_created_and_its_key_is_immutable(): void
    {
        $menu = Menu::create(['key' => 'footer', 'name' => 'Footer']);

        $this->assertSame($menu->id, Menu::findByKey('footer')->id);

        $menu->name = 'Bottom';
        $menu->save();
        $this->assertSame('Bottom', $menu->fresh()->name);

        $menu->key = 'bottom';
        $this->assertArrayHasKey('key', $this->errorsOf(fn () => $menu->save()));
    }

    public function test_an_invalid_or_taken_key_is_rejected(): void
    {
        $this->assertArrayHasKey('key', $this->errorsOf(fn () => Menu::create(['key' => 'Bad Key', 'name' => 'x'])));
        $this->assertArrayHasKey('key', $this->errorsOf(fn () => Menu::create(['key' => 'main', 'name' => 'x'])));
    }

    public function test_an_invalid_max_depth_is_rejected(): void
    {
        foreach ([0, -1, '2', 1.5] as $depth) {
            $this->assertArrayHasKey('settings.max_depth', $this->errorsOf(
                fn () => Menu::create(['key' => 'm'.strtolower(Str::random(5)), 'name' => 'x', 'settings' => ['max_depth' => $depth]]),
            ));
        }
    }

    public function test_a_root_and_a_child_are_related_by_uuid(): void
    {
        $catalog = $this->item();
        $jackets = $this->item($catalog);

        $this->assertTrue(Str::isUuid($catalog->uuid));
        $this->assertNull($catalog->parent);
        $this->assertSame($catalog->uuid, $jackets->parent->uuid);
        $this->assertSame([$jackets->uuid], $catalog->children->pluck('uuid')->all());
        $this->assertSame('main', $jackets->menu->key);
        $this->assertSame(2, $this->menu->items()->count());
    }

    public function test_a_parent_from_another_menu_is_rejected(): void
    {
        $other = $this->item(menu: Menu::factory()->create());

        $this->assertArrayHasKey('parent_uuid', $this->errorsOf(fn () => $this->item($other)));
    }

    public function test_cycles_are_rejected(): void
    {
        $catalog = $this->item();
        $jackets = $this->item($catalog);

        $catalog->parent_uuid = $jackets->uuid;
        $this->assertArrayHasKey('parent_uuid', $this->errorsOf(fn () => $catalog->save()));

        $jackets->parent_uuid = $jackets->uuid;
        $this->assertArrayHasKey('parent_uuid', $this->errorsOf(fn () => $jackets->save()));
    }

    public function test_the_menu_and_uuid_of_an_item_are_immutable(): void
    {
        $item = $this->item();

        $item->menu_key = Menu::factory()->create()->key;
        $this->assertArrayHasKey('menu_key', $this->errorsOf(fn () => $item->save()));

        $item = $item->fresh();
        $item->uuid = (string) Str::uuid7();
        $this->assertArrayHasKey('uuid', $this->errorsOf(fn () => $item->save()));
    }

    public function test_siblings_are_ordered_by_weight_then_id(): void
    {
        $root = $this->item();
        $c2 = $this->item($root, 2);
        $c0 = $this->item($root, 0);
        $c1 = $this->item($root, 1);
        $c1b = $this->item($root, 1);

        $this->assertSame([$c0->uuid, $c1->uuid, $c1b->uuid, $c2->uuid], $root->children->pluck('uuid')->all());
    }

    public function test_the_depth_limit_applies_when_creating(): void
    {
        $menu = Menu::factory()->maxDepth(2)->create();
        $root = $this->item(menu: $menu);
        $child = $this->item($root, menu: $menu);

        $this->assertArrayHasKey('parent_uuid', $this->errorsOf(fn () => $this->item($child, menu: $menu)));
    }

    public function test_moving_a_subtree_checks_its_height(): void
    {
        $menu = Menu::factory()->maxDepth(3)->create();
        $a = $this->item(menu: $menu);
        $b = $this->item($a, menu: $menu);
        $c = $this->item($b, menu: $menu);
        $x = $this->item(menu: $menu);
        $y = $this->item($x, menu: $menu);

        // a (depth 1 → 3 levels) moved under y (depth 2) would reach depth 5.
        $a->parent_uuid = $y->uuid;
        $this->assertArrayHasKey('parent_uuid', $this->errorsOf(fn () => $a->save()));

        // a leaf fits under y: depth 3.
        $c->parent_uuid = $y->uuid;
        $c->save();
        $this->assertSame($y->uuid, $c->fresh()->parent_uuid);
    }

    public function test_the_max_depth_cannot_drop_below_the_tree_height(): void
    {
        $root = $this->item();
        $child = $this->item($root);
        $this->item($child);

        $this->menu->settings = ['max_depth' => 2];
        $this->assertArrayHasKey('settings.max_depth', $this->errorsOf(fn () => $this->menu->save()));

        $this->menu->settings = ['max_depth' => 3];
        $this->menu->save();
        $this->assertSame(3, $this->menu->fresh()->maxDepth());
    }

    public function test_target_css_class_and_url_are_validated(): void
    {
        $this->assertArrayHasKey('target', $this->errorsOf(fn () => $this->item(extra: ['target' => '_top'])));
        $this->assertArrayHasKey('css_class', $this->errorsOf(fn () => $this->item(extra: ['css_class' => 'a<b'])));
        $this->assertArrayHasKey('url', $this->errorsOf(fn () => $this->item(extra: ['url' => 'javascript:alert(1)'])));
        $this->assertArrayHasKey('url', $this->errorsOf(fn () => $this->item(extra: ['url' => '//example.com'])));

        $item = $this->item(extra: ['url' => '/about', 'css_class' => 'btn btn-primary', 'target' => '_blank']);
        $this->assertSame('/about', $item->fresh()->url);
    }

    public function test_blank_url_and_css_class_are_stored_as_null(): void
    {
        $item = $this->item(extra: ['url' => '', 'css_class' => '  ']);

        $this->assertNull($item->fresh()->url);
        $this->assertNull($item->fresh()->css_class);
    }

    public function test_the_schedule_must_be_consistent(): void
    {
        $this->assertArrayHasKey('unpublished_at', $this->errorsOf(fn () => $this->item(extra: [
            'published_at' => '2026-01-02 10:00:00', 'unpublished_at' => '2026-01-02 10:00:00',
        ])));
        $this->assertArrayHasKey('unpublished_at', $this->errorsOf(fn () => $this->item(extra: [
            'published_at' => '2026-01-02 10:00:00', 'unpublished_at' => '2026-01-01 10:00:00',
        ])));

        $this->item(extra: ['published_at' => '2026-01-01 10:00:00', 'unpublished_at' => '2026-01-02 10:00:00']);
        $this->item(extra: ['unpublished_at' => '2026-01-02 10:00:00']);
    }

    public function test_an_item_with_children_cannot_be_deleted_and_a_leaf_can(): void
    {
        $root = $this->item();
        $leaf = $this->item($root);

        $this->assertArrayHasKey('uuid', $this->errorsOf(fn () => $root->delete()));
        $this->assertNotNull(MenuItem::findByUuid($root->uuid));
        $this->assertNotNull(MenuItem::findByUuid($leaf->uuid));

        $leaf->delete();
        $root->delete();
        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_a_menu_with_items_cannot_be_deleted_and_an_empty_one_can(): void
    {
        $item = $this->item();

        $this->assertArrayHasKey('key', $this->errorsOf(fn () => $this->menu->delete()));

        $item->delete();
        $this->menu->delete();
        $this->assertNull(Menu::findByKey('main'));
    }

    public function test_the_database_refuses_to_delete_a_parent_with_children(): void
    {
        $root = $this->item();
        $this->item($root);

        $this->expectException(QueryException::class);
        DB::table('menu_items')->where('uuid', $root->uuid)->delete();
    }
}
