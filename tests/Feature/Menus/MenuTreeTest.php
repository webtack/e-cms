<?php

namespace Webtack\Ecms\Tests\Feature\Menus;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Menus\MenuTree;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;
use Webtack\Ecms\Models\MenuItemTranslation;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class MenuTreeTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->menu = Menu::factory()->create(['key' => 'main']);
    }

    private function item(string $title, ?MenuItem $parent = null, int $weight = 0, array $extra = []): MenuItem
    {
        $item = MenuItem::factory()->in($this->menu)->create(['parent_uuid' => $parent?->uuid, 'weight' => $weight, ...$extra]);
        MenuItemTranslation::factory()->create(['item_uuid' => $item->uuid, 'lang' => 'default', 'title' => $title]);

        return $item;
    }

    private function tree(?string $lang = 'default'): array
    {
        return app(MenuTree::class)->tree('main', $lang);
    }

    public function test_only_visible_items_are_returned(): void
    {
        $this->item('Published');
        $this->item('Scheduled', extra: ['published_at' => now()->addDay()]);
        $this->item('Disabled', extra: ['is_active' => false]);
        $this->item('Expired', extra: ['unpublished_at' => now()->subDay()]);

        $this->assertSame(['Published'], array_column($this->tree(), 'title'));
    }

    public function test_a_hidden_parent_hides_visible_children(): void
    {
        $hidden = $this->item('Hidden', extra: ['is_active' => false]);
        $child = $this->item('Child', $hidden);
        $this->item('Grandchild', $child);
        $this->item('Shown');

        $this->assertSame(['Shown'], array_column($this->tree(), 'title'));
    }

    public function test_order_and_nesting(): void
    {
        $second = $this->item('B', weight: 1);
        $first = $this->item('A', weight: 0);
        $this->item('B1', $second, 1);
        $this->item('B0', $second, 0);

        $tree = $this->tree();

        $this->assertSame(['A', 'B'], array_column($tree, 'title'));
        $this->assertSame([], $tree[0]['children']);
        $this->assertSame(['B0', 'B1'], array_column($tree[1]['children'], 'title'));
        $this->assertSame($first->uuid, $tree[0]['uuid']);
    }

    public function test_is_external_follows_the_address(): void
    {
        $this->item('Relative', weight: 0, extra: ['url' => '/about']);
        $this->item('External', weight: 1, extra: ['url' => 'https://example.com']);
        $this->item('Heading', weight: 2);

        $this->assertSame([false, true, null], array_column($this->tree(), 'is_external'));
    }

    public function test_the_node_carries_the_presentation_fields(): void
    {
        $item = $this->item('Docs', extra: ['url' => '/docs', 'target' => '_blank', 'css_class' => 'btn', 'image' => 'x.png']);
        MenuItemTranslation::query()->where('item_uuid', $item->uuid)->update(['description' => 'Read']);

        $this->assertSame([[
            'uuid' => $item->uuid, 'title' => 'Docs', 'description' => 'Read', 'url' => '/docs', 'target' => '_blank',
            'css_class' => 'btn', 'image' => 'x.png', 'is_external' => false, 'children' => [],
        ]], $this->tree());
    }

    public function test_a_missing_translation_gives_null_without_fallback(): void
    {
        $this->enableLanguages('en');
        $this->item('Only default');

        $this->assertNull($this->tree('en')[0]['title']);
        $this->assertNull($this->tree('en')[0]['description']);
    }

    public function test_the_language_is_checked(): void
    {
        $this->expectException(ValidationException::class);

        $this->tree('de');
    }

    public function test_an_unknown_menu_is_an_error(): void
    {
        $this->expectException(ModelNotFoundException::class);

        app(MenuTree::class)->tree('missing');
    }

    public function test_the_schedule_is_judged_at_the_given_moment(): void
    {
        $this->item('Later', extra: ['published_at' => '2026-12-01 00:00:00']);

        $this->assertSame([], app(MenuTree::class)->tree('main', 'default', Carbon::parse('2026-11-01')));
        $this->assertSame(['Later'], array_column(app(MenuTree::class)->tree('main', 'default', Carbon::parse('2026-12-02')), 'title'));
    }

    public function test_the_query_count_does_not_depend_on_depth(): void
    {
        $parent = null;
        foreach (range(1, 6) as $i) {
            $parent = $this->item("L{$i}", $parent);
        }

        DB::enableQueryLog();
        $this->tree();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // The menu, the items and the translations.
        $this->assertSame(3, $queries);
    }

    public function test_the_admin_tree_lists_all_items_with_depth_and_status(): void
    {
        $root = $this->item('Catalog');
        $jackets = $this->item('Jackets', $root, extra: ['is_active' => false]);
        $this->item('Winter', $jackets, extra: ['published_at' => now()->addDay()]);
        $this->item('About', weight: 1, extra: ['url' => '/about']);

        $rows = app(MenuTree::class)->adminTree($this->menu);

        $this->assertSame(['Catalog', 'Jackets', 'Winter', 'About'], array_column($rows, 'title'));
        $this->assertSame([1, 2, 3, 1], array_column($rows, 'depth'));
        $this->assertSame(['published', 'disabled', 'scheduled', 'published'], array_column($rows, 'status'));
        $this->assertSame([1, 1, 0, 0], array_column($rows, 'children_count'));
        $this->assertSame('/about', $rows[3]['url']);
    }

    public function test_the_fallback_mode_replaces_a_missing_title_by_the_default_one(): void
    {
        $this->enableLanguages('en');
        $item = $this->item('Каталог');

        $tree = app(MenuTree::class)->tree('main', 'en', fallback: true);

        $this->assertSame('Каталог', $tree[0]['title']);
        $this->assertSame($item->url, $tree[0]['url']);
        $this->assertNull($this->tree('en')[0]['title']);
    }

    public function test_title_and_description_fall_back_independently(): void
    {
        $this->enableLanguages('en');
        $item = $this->item('Каталог');
        MenuItemTranslation::factory()->create(['item_uuid' => $item->uuid, 'lang' => 'en', 'title' => null, 'description' => 'Our goods']);

        $tree = app(MenuTree::class)->tree('main', 'en', fallback: true);

        $this->assertSame('Каталог', $tree[0]['title']);
        $this->assertSame('Our goods', $tree[0]['description']);
    }
}
