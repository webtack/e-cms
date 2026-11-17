<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Menus\MenuItemWriter;
use Webtack\Ecms\Menus\MenuTree;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;
use Webtack\Ecms\Models\MenuItemTranslation;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class MenuItemAdminTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->menu = Menu::factory()->create(['key' => 'main', 'name' => 'Main']);
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    /** @param array<string, mixed> $extra */
    private function item(string $title, ?MenuItem $parent = null, int $weight = 0, array $extra = [], ?Menu $menu = null): MenuItem
    {
        return app(MenuItemWriter::class)->create($menu ?? ($parent ? $parent->menu : $this->menu), [
            'parent_uuid' => $parent?->uuid,
            'weight' => $weight,
            'translations' => ['default' => ['title' => $title]],
            ...$extra,
        ]);
    }

    private function image(string $profile = 'image'): File
    {
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => $profile, 'mime' => 'image/png', 'path' => "{$uuid}.png", 'size' => 1000]);
    }

    private function url(string $suffix = ''): string
    {
        return '/admin/menus/main/items'.$suffix;
    }

    public function test_index_lists_the_tree_with_depth_status_and_order(): void
    {
        $b = $this->item('B', weight: 0);
        $a = $this->item('A', weight: 1, extra: ['url' => '/a', 'is_active' => false]);
        $child = $this->item('Child', $b, extra: ['published_at' => now()->addDay()]);
        $this->item('Grand', $child, extra: ['unpublished_at' => now()->subDay(), 'published_at' => now()->subDays(2)]);

        $this->admin()->get($this->url())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Menus/Items/Index')
                ->where('menu.key', 'main')
                ->has('items', 4)
                ->where('items.0.title', 'B')
                ->where('items.0.depth', 1)
                ->where('items.0.children_count', 1)
                ->where('items.0.status', 'published')
                ->where('items.1.title', 'Child')
                ->where('items.1.depth', 2)
                ->where('items.1.status', 'scheduled')
                ->where('items.2.title', 'Grand')
                ->where('items.2.depth', 3)
                ->where('items.2.status', 'expired')
                ->where('items.3.uuid', $a->uuid)
                ->where('items.3.url', '/a')
                ->where('items.3.status', 'disabled'));
    }

    public function test_an_item_without_an_address_has_a_null_url(): void
    {
        $this->item('Heading');

        $this->admin()->get($this->url())->assertInertia(fn (Assert $page) => $page->where('items.0.url', null));
    }

    public function test_foreign_menu_or_item_answers_404(): void
    {
        $other = Menu::factory()->create(['key' => 'footer']);
        $foreign = $this->item('Foreign', menu: $other);

        $this->admin()->get('/admin/menus/missing/items')->assertNotFound();
        $this->admin()->get($this->url("/{$foreign->uuid}/edit"))->assertNotFound();
        $this->admin()->put($this->url("/{$foreign->uuid}"), ['translations' => ['default' => ['title' => 'X']]])->assertNotFound();
        $this->admin()->delete($this->url("/{$foreign->uuid}"))->assertNotFound();
        $this->assertNotNull(MenuItem::findByUuid($foreign->uuid));
    }

    public function test_create_form_prefills_the_weight_and_offers_parents(): void
    {
        $root = $this->item('Root', weight: 4);
        $this->item('Child', $root);

        $this->admin()->get($this->url('/create'))->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Menus/Items/Create')
            ->where('defaultWeight', 5)
            ->has('parentOptions', 2)
            ->where('parentOptions.0', ['uuid' => $root->uuid, 'name' => 'Root', 'depth' => 1])
            ->where('parentOptions.1.depth', 2));
    }

    public function test_store_creates_a_root_item(): void
    {
        $this->admin()->post($this->url(), [
            'translations' => ['default' => ['title' => 'Про нас', 'description' => 'Опис']],
            'url' => '/about', 'target' => '_blank', 'css_class' => 'btn', 'parent_uuid' => '', 'weight' => '', 'image' => '',
            'is_active' => true, 'published_at' => '', 'unpublished_at' => '',
        ])->assertRedirect($this->url())->assertSessionHas('success');

        $item = MenuItem::query()->firstOrFail();
        $this->assertSame('/about', $item->url);
        $this->assertSame('_blank', $item->target);
        $this->assertSame('btn', $item->css_class);
        $this->assertNull($item->parent_uuid);
        $this->assertSame(0, $item->weight);
        $this->assertTrue($item->is_active);
        $this->assertSame('Опис', MenuItemTranslation::query()->where('item_uuid', $item->uuid)->value('description'));
    }

    public function test_store_creates_a_subitem_after_its_siblings(): void
    {
        $root = $this->item('Catalog', weight: 3);
        $this->item('First', $root, weight: 7);

        $this->admin()->post($this->url(), ['translations' => ['default' => ['title' => 'Jackets']], 'parent_uuid' => $root->uuid])
            ->assertSessionHasNoErrors();

        $jackets = MenuItem::query()->where('parent_uuid', $root->uuid)->orderByDesc('weight')->firstOrFail();
        $this->assertSame(8, $jackets->weight);
        $this->assertSame('Jackets', $jackets->translations()->value('title'));
    }

    public function test_store_creates_a_heading_without_an_address(): void
    {
        $this->admin()->post($this->url(), ['translations' => ['default' => ['title' => 'Group']], 'url' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull(MenuItem::query()->firstOrFail()->url);
    }

    public function test_store_rejects_an_invalid_address_a_missing_title_and_a_foreign_parent(): void
    {
        $foreign = $this->item('Foreign', menu: Menu::factory()->create(['key' => 'footer']));

        $this->admin()->post($this->url(), ['translations' => ['default' => ['title' => 'X']], 'url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('url');
        $this->admin()->post($this->url(), ['translations' => ['default' => ['title' => 'X']], 'url' => '//example.com'])
            ->assertSessionHasErrors('url');
        $this->admin()->post($this->url(), ['translations' => ['default' => ['title' => 'X']], 'url' => '/\\evil.example'])
            ->assertSessionHasErrors('url');
        $this->admin()->post($this->url(), ['translations' => ['default' => ['title' => '']]])
            ->assertSessionHasErrors('translations.default.title');
        $this->admin()->post($this->url(), ['translations' => ['default' => ['title' => 'X']], 'parent_uuid' => $foreign->uuid])
            ->assertSessionHasErrors('parent_uuid');
        $this->admin()->post($this->url(), ['translations' => ['default' => ['title' => 'X']], 'target' => '_top'])
            ->assertSessionHasErrors('target');
        $this->admin()->post($this->url(), ['translations' => ['default' => ['title' => 'X']], 'css_class' => 'a<b'])
            ->assertSessionHasErrors('css_class');
        $this->assertSame(1, MenuItem::query()->count());
    }

    public function test_a_contradictory_schedule_is_reported_at_the_end_date(): void
    {
        $this->admin()->post($this->url(), [
            'translations' => ['default' => ['title' => 'X']],
            'published_at' => '2026-03-02T10:00', 'unpublished_at' => '2026-03-02T10:00',
        ])->assertSessionHasErrors('unpublished_at');

        $this->admin()->post($this->url(), [
            'translations' => ['default' => ['title' => 'X']],
            'published_at' => '2026-03-02T10:00', 'unpublished_at' => '2026-03-03T10:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-03-03 10:00', MenuItem::query()->firstOrFail()->unpublished_at->format('Y-m-d H:i'));
    }

    public function test_the_depth_limit_hides_parents_and_rejects_a_forced_one(): void
    {
        $limited = Menu::factory()->maxDepth(2)->create(['key' => 'limited']);
        $root = $this->item('Root', menu: $limited);
        $child = $this->item('Child', $root);

        $this->admin()->get('/admin/menus/limited/items/create')
            ->assertInertia(fn (Assert $page) => $page->has('parentOptions', 1)->where('parentOptions.0.uuid', $root->uuid));

        $this->admin()->post('/admin/menus/limited/items', ['translations' => ['default' => ['title' => 'Deep']], 'parent_uuid' => $child->uuid])
            ->assertSessionHasErrors('parent_uuid');
    }

    public function test_create_form_has_no_translations_or_switcher(): void
    {
        $this->enableLanguages('en');

        $this->admin()->get($this->url('/create'))
            ->assertInertia(fn (Assert $page) => $page->missing('languages')->missing('translation'));
    }

    public function test_base_form_keeps_translations_of_other_languages(): void
    {
        $this->enableLanguages('en');
        $item = $this->item('Base');
        $this->admin()->put($this->url("/{$item->uuid}/translations/en"), ['title' => 'English'])->assertSessionHasNoErrors();

        $this->admin()->put($this->url("/{$item->uuid}"), ['translations' => ['default' => ['title' => 'Changed'], 'en' => ['title' => 'Hacked']]])
            ->assertSessionHasNoErrors();

        $this->assertSame('English', MenuItemTranslation::class::query()->where('item_uuid', $item->uuid)->where('lang', 'en')->value('title'));
        $this->assertSame('Changed', MenuItemTranslation::class::query()->where('item_uuid', $item->uuid)->where('lang', 'default')->value('title'));
    }

    public function test_translation_mode_is_prefilled_with_base_values_and_marks_languages(): void
    {
        $this->enableLanguages('en', 'pl');
        $item = $this->item('Base');
        $this->admin()->put($this->url("/{$item->uuid}/translations/en"), ['title' => 'English'])->assertSessionHasNoErrors();

        $this->admin()->get($this->url("/{$item->uuid}/edit"))->assertInertia(fn (Assert $page) => $page
            ->where('translation.lang', null)
            ->where('translation.baseLang', 'xx')
            ->where('translation.languages.0.translated', true)
            ->where('translation.languages.1.translated', false)
            ->where('translation.values', null));

        $this->admin()->get($this->url("/{$item->uuid}/edit?lang=en"))->assertInertia(fn (Assert $page) => $page
            ->where('translation.lang', 'en')
            ->where('translation.exists', true)
            ->where('translation.values.title', 'English'));

        $this->admin()->get($this->url("/{$item->uuid}/edit?lang=pl"))->assertInertia(fn (Assert $page) => $page
            ->where('translation.exists', false)
            ->where('translation.values.title', 'Base'));
    }

    public function test_translation_mode_answers_404_for_unavailable_languages(): void
    {
        $item = $this->item('Base');
        $this->admin()->get($this->url("/{$item->uuid}/edit?lang=en"))->assertNotFound();

        $this->enableLanguages('en');
        AppLanguage::factory()->create(['code' => 'de', 'weight' => 5]);

        $this->admin()->get($this->url("/{$item->uuid}/edit?lang=de"))->assertNotFound();
        $this->admin()->get($this->url("/{$item->uuid}/edit?lang=zz"))->assertNotFound();
        $this->admin()->get($this->url("/{$item->uuid}/edit?lang=xx"))->assertOk();
        $this->admin()->put($this->url("/{$item->uuid}/translations/de"), ['title' => 'X'])->assertNotFound();
        $this->admin()->delete($this->url("/{$item->uuid}/translations/zz"))->assertNotFound();
    }

    public function test_translation_is_saved_for_one_language_only_and_equal_values_are_dropped(): void
    {
        $this->enableLanguages('en', 'pl');
        $item = $this->item('Base');
        $this->admin()->put($this->url("/{$item->uuid}/translations/pl"), ['title' => 'Polski'])->assertSessionHasNoErrors();

        $this->admin()->put($this->url("/{$item->uuid}/translations/en"), ['title' => 'Base', 'description' => 'Only a description'])
            ->assertRedirect($this->url("/{$item->uuid}/edit?lang=en"));

        $en = MenuItemTranslation::class::query()->where('item_uuid', $item->uuid)->where('lang', 'en')->firstOrFail();
        $this->assertNull($en->title);
        $this->assertSame('Only a description', $en->description);
        $this->assertSame('Polski', MenuItemTranslation::class::query()->where('item_uuid', $item->uuid)->where('lang', 'pl')->value('title'));

        $this->admin()->put($this->url("/{$item->uuid}/translations/en"), ['title' => '', 'description' => ''])->assertSessionHasNoErrors();
        $this->assertNull(MenuItemTranslation::class::query()->where('item_uuid', $item->uuid)->where('lang', 'en')->first());
    }

    public function test_translation_is_cleared(): void
    {
        $this->enableLanguages('en');
        $item = $this->item('Base');
        $this->admin()->put($this->url("/{$item->uuid}/translations/en"), ['title' => 'English']);

        $this->admin()->delete($this->url("/{$item->uuid}/translations/en"))->assertRedirect($this->url("/{$item->uuid}/edit?lang=en"));

        $this->assertNull(MenuItemTranslation::class::query()->where('item_uuid', $item->uuid)->where('lang', 'en')->first());
        $this->assertSame('Base', MenuItemTranslation::class::query()->where('item_uuid', $item->uuid)->where('lang', 'default')->value('title'));
    }

    public function test_translation_routes_require_the_menus_right(): void
    {
        $this->enableLanguages('en');
        $item = $this->item('Base');
        $user = User::factory()->withRole(Role::factory()->withPermissions([])->create())->create();

        $this->actingAs($user)->get($this->url("/{$item->uuid}/edit?lang=en"))->assertForbidden();
        $this->actingAs($user)->put($this->url("/{$item->uuid}/translations/en"), ['title' => 'X'])->assertForbidden();
        $this->actingAs($user)->delete($this->url("/{$item->uuid}/translations/en"))->assertForbidden();
        $this->assertSame(0, MenuItemTranslation::query()->where('lang', 'en')->count());
    }

    public function test_menu_tree_reads_a_partial_translation(): void
    {
        $this->enableLanguages('en');
        $item = $this->item('Base');
        $this->admin()->put($this->url("/{$item->uuid}/translations/en"), ['title' => '', 'description' => 'Only text'])->assertSessionHasNoErrors();

        $tree = app(MenuTree::class)->tree('main', 'en');

        $this->assertNull($tree[0]['title']);
        $this->assertSame('Only text', $tree[0]['description']);
    }

    public function test_translations_of_inactive_languages_are_not_written(): void
    {
        $this->admin()->post($this->url(), ['translations' => ['default' => ['title' => 'A'], 'en' => ['title' => 'Ignored']]])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, MenuItemTranslation::query()->where('lang', 'en')->count());
    }

    public function test_edit_offers_no_own_subtree_as_parent_and_shows_the_item(): void
    {
        $catalog = $this->item('Catalog', extra: ['published_at' => '2026-03-01 10:15:00']);
        $jackets = $this->item('Jackets', $catalog);
        $this->item('Winter', $jackets);
        $sale = $this->item('Sale');

        $this->admin()->get($this->url("/{$catalog->uuid}/edit"))->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Menus/Items/Edit')
            ->where('item.uuid', $catalog->uuid)
            ->where('item.title', 'Catalog')
            ->where('item.published_at', '2026-03-01T10:15')
            ->where('item.unpublished_at', null)
            ->where('item.is_active', true)
            ->where('imageFile', null)
            ->has('parentOptions', 1)
            ->where('parentOptions.0.uuid', $sale->uuid));
    }

    public function test_the_parent_options_respect_the_subtree_height(): void
    {
        $limited = Menu::factory()->maxDepth(3)->create(['key' => 'limited']);
        $a = $this->item('A', menu: $limited);
        $this->item('B', $a);
        $c = $this->item('C', menu: $limited);
        $d = $this->item('D', $c);
        $this->item('E', $d);

        // "A" has a subtree of height 2: it fits under a root (1 + 2 <= 3) but not under a depth-2 item.
        $this->admin()->get("/admin/menus/limited/items/{$a->uuid}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('parentOptions', fn ($options) => collect($options)->pluck('uuid')->all() === [$c->uuid]));
    }

    public function test_update_moves_an_item_under_another_parent(): void
    {
        $catalog = $this->item('Catalog');
        $sale = $this->item('Sale');
        $jackets = $this->item('Jackets', $catalog);

        $this->admin()->from($this->url("/{$jackets->uuid}"))->put($this->url("/{$jackets->uuid}"), [
            'translations' => ['default' => ['title' => 'Jackets']], 'parent_uuid' => $sale->uuid, 'weight' => '2',
        ])->assertRedirect($this->url("/{$jackets->uuid}"))->assertSessionHas('success');

        $this->assertSame($sale->uuid, $jackets->fresh()->parent_uuid);
        $this->assertSame(2, $jackets->fresh()->weight);
    }

    public function test_update_rejects_a_descendant_as_parent(): void
    {
        $catalog = $this->item('Catalog');
        $jackets = $this->item('Jackets', $catalog);

        $this->admin()->put($this->url("/{$catalog->uuid}"), ['translations' => ['default' => ['title' => 'Catalog']], 'parent_uuid' => $jackets->uuid])
            ->assertSessionHasErrors('parent_uuid');
        $this->assertNull($catalog->fresh()->parent_uuid);
    }

    public function test_update_keeps_the_weight_when_blank_and_moves_to_root(): void
    {
        $catalog = $this->item('Catalog', weight: 5);
        $jackets = $this->item('Jackets', $catalog, weight: 3);

        $this->admin()->put($this->url("/{$jackets->uuid}"), ['translations' => ['default' => ['title' => 'Jackets']], 'parent_uuid' => '', 'weight' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($jackets->fresh()->parent_uuid);
        $this->assertSame(3, $jackets->fresh()->weight);
    }

    public function test_disabling_an_item_keeps_its_dates(): void
    {
        $item = $this->item('A', extra: ['published_at' => '2026-03-01 10:00:00', 'unpublished_at' => '2026-03-05 10:00:00']);

        $this->admin()->put($this->url("/{$item->uuid}"), [
            'translations' => ['default' => ['title' => 'A']], 'is_active' => false,
            'published_at' => '2026-03-01T10:00', 'unpublished_at' => '2026-03-05T10:00',
        ])->assertSessionHasNoErrors();

        $fresh = $item->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertSame('disabled', $fresh->status());
        $this->assertSame('2026-03-01 10:00', $fresh->published_at->format('Y-m-d H:i'));
        $this->assertSame('2026-03-05 10:00', $fresh->unpublished_at->format('Y-m-d H:i'));
    }

    public function test_blank_dates_and_address_clear_them(): void
    {
        $item = $this->item('A', extra: ['url' => '/a', 'published_at' => '2026-03-01 10:00:00']);

        $this->admin()->put($this->url("/{$item->uuid}"), [
            'translations' => ['default' => ['title' => 'A']], 'url' => '', 'published_at' => '', 'unpublished_at' => '',
        ])->assertSessionHasNoErrors();

        $this->assertNull($item->fresh()->url);
        $this->assertNull($item->fresh()->published_at);
    }

    public function test_the_image_is_bound_replaced_cleared_and_checked(): void
    {
        $first = $this->image();
        $second = $this->image();
        $document = $this->image('document');
        $item = $this->item('About');
        $data = fn (string $image) => ['translations' => ['default' => ['title' => 'About']], 'image' => $image];

        $this->admin()->put($this->url("/{$item->uuid}"), $data($first->path))->assertSessionHasNoErrors();
        $this->assertSame($first->path, $item->fresh()->image);

        $this->admin()->get($this->url("/{$item->uuid}/edit"))
            ->assertInertia(fn (Assert $page) => $page->where('imageFile.uuid', $first->uuid)->where('imageFile.preview', Storage::disk('public')->url("styles/thumb/{$first->uuid}.webp"))->where('item.image', $first->path));

        $this->admin()->put($this->url("/{$item->uuid}"), $data($second->path))->assertSessionHasNoErrors();
        $this->assertSame($second->path, $item->fresh()->image);

        $this->admin()->put($this->url("/{$item->uuid}"), $data($document->path))->assertSessionHasErrors('image');
        $this->assertSame($second->path, $item->fresh()->image);

        $this->admin()->put($this->url("/{$item->uuid}"), $data(''))->assertSessionHasNoErrors();
        $this->assertNull($item->fresh()->image);
    }

    public function test_the_media_library_shows_the_item_with_a_link_to_its_form(): void
    {
        $image = $this->image();
        $item = $this->item('About');
        $this->admin()->put($this->url("/{$item->uuid}"), ['translations' => ['default' => ['title' => 'About']], 'image' => $image->path]);

        $this->admin()->getJson("/admin/media/{$image->uuid}")
            ->assertOk()
            ->assertJsonPath('usages.0.owner_type', 'menu_item')
            ->assertJsonPath('usages.0.label', 'About')
            ->assertJsonPath('usages.0.url', "/admin/menus/main/items/{$item->uuid}/edit")
            ->assertJsonPath('usages.0.role', 'image');
    }

    public function test_destroy_deletes_a_leaf_with_its_translations(): void
    {
        $item = $this->item('Jackets');

        $this->admin()->delete($this->url("/{$item->uuid}"))->assertRedirect($this->url())->assertSessionHas('success');

        $this->assertNull(MenuItem::findByUuid($item->uuid));
        $this->assertSame(0, MenuItemTranslation::query()->where('item_uuid', $item->uuid)->count());
    }

    public function test_destroy_keeps_an_item_with_children(): void
    {
        $catalog = $this->item('Catalog');
        $this->item('Jackets', $catalog);

        $this->admin()->from($this->url())->delete($this->url("/{$catalog->uuid}"))
            ->assertRedirect($this->url())
            ->assertSessionHas('error', 'Cannot delete a menu item that has child items. Move or delete them first.');

        $this->assertNotNull(MenuItem::findByUuid($catalog->uuid));
    }

    public function test_reorder_rewrites_the_weights_by_position(): void
    {
        $a = $this->item('A', weight: 0);
        $b = $this->item('B', weight: 1);
        $child = $this->item('Child', $a, weight: 9);

        $this->admin()->put($this->url('/order'), ['order' => [$b->uuid, $a->uuid, $child->uuid]])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(1, $a->fresh()->weight);
        $this->assertSame(0, $b->fresh()->weight);
        $this->assertSame(2, $child->fresh()->weight);
        $this->assertSame($a->uuid, $child->fresh()->parent_uuid);
    }

    public function test_reorder_rejects_an_incomplete_foreign_or_repeated_list(): void
    {
        $a = $this->item('A', weight: 0);
        $b = $this->item('B', weight: 1);
        $foreign = $this->item('Foreign', menu: Menu::factory()->create(['key' => 'footer']));

        $this->admin()->put($this->url('/order'), ['order' => [$b->uuid]])->assertSessionHasErrors('order');
        $this->admin()->put($this->url('/order'), ['order' => [$b->uuid, $a->uuid, $foreign->uuid]])->assertSessionHasErrors('order');
        $this->admin()->put($this->url('/order'), ['order' => [$b->uuid, $b->uuid]])->assertSessionHasErrors('order.1');
        $this->assertSame(0, $a->fresh()->weight);
        $this->assertSame(1, $b->fresh()->weight);
    }

    public function test_set_active_changes_only_the_flag(): void
    {
        $item = $this->item('A', extra: ['published_at' => now()->subDays(2)->startOfSecond(), 'unpublished_at' => now()->subDay()->startOfSecond()]);

        $this->admin()->patch($this->url("/{$item->uuid}/active"), ['is_active' => false])
            ->assertRedirect()
            ->assertSessionHas('success', 'Menu item disabled.');

        $fresh = $item->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertTrue($fresh->published_at->equalTo($item->published_at));
        $this->assertTrue($fresh->unpublished_at->equalTo($item->unpublished_at));
        $this->assertSame('A', $fresh->translations->firstWhere('lang', 'default')->title);

        $this->admin()->patch($this->url("/{$item->uuid}/active"), ['is_active' => true])->assertSessionHas('success', 'Menu item enabled.');
        $this->assertSame('expired', $item->fresh()->status());
    }

    public function test_set_active_rejects_an_invalid_value_and_a_foreign_item(): void
    {
        $item = $this->item('A');
        $other = Menu::factory()->create(['key' => 'footer', 'name' => 'Footer']);

        $this->admin()->patch($this->url("/{$item->uuid}/active"), ['is_active' => 'maybe'])->assertSessionHasErrors('is_active');
        $this->admin()->patch("/admin/menus/footer/items/{$item->uuid}/active", ['is_active' => false])->assertNotFound();

        $this->assertTrue($item->fresh()->is_active);
        $this->assertSame('footer', $other->key);
    }

    public function test_set_active_needs_the_menus_permission(): void
    {
        $item = $this->item('A');
        $user = User::factory()->withRole(Role::factory()->withPermissions([])->create())->create();

        $this->actingAs($user)->patch($this->url("/{$item->uuid}/active"), ['is_active' => false])->assertForbidden();

        $this->assertTrue($item->fresh()->is_active);
    }
}
