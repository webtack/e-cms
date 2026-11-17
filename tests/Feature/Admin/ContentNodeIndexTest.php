<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentNodeIndexTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function type(string $key = 'news', array $settings = []): ContentType
    {
        $type = ContentType::create(['key' => $key, 'name' => ucfirst($key)]);

        // Written past the model: it rejects references to fields that are created only after the type,
        // and a stale reference (a deleted field) is exactly what some tests need.
        if ($settings !== []) {
            DB::table('content_types')->where('key', $key)->update(['settings' => json_encode($settings)]);
        }

        return $type;
    }

    private function field(string $type, string $key, string $fieldType = 'string', bool $multiple = false): ContentField
    {
        $attributes = [
            'content_type' => $type, 'key' => $key, 'name' => ucfirst($key), 'type' => $fieldType,
            'is_multiple' => $multiple, 'is_required' => false,
        ];

        // A field with a reserved key predates the reservation, so it is written past the model.
        if (in_array($key, ContentField::RESERVED_KEYS, true)) {
            DB::table('content_fields')->insert($attributes);

            return ContentField::where('content_type', $type)->where('key', $key)->firstOrFail();
        }

        return ContentField::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function node(ContentType $type, array $values, ?string $slug = null): ContentNode
    {
        return app(ContentNodeCreator::class)->create($type, $values, $slug, authorId: $this->authorId());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->type();

        $this->get('/admin/content-types/news/nodes')->assertRedirect('/login');
    }

    public function test_unverified_user_is_redirected_to_the_notice(): void
    {
        $this->type();

        $this->actingAs(User::factory()->unverified()->create())
            ->get('/admin/content-types/news/nodes')
            ->assertRedirect('/email/verify');
    }

    public function test_the_routes_have_their_names(): void
    {
        $uuid = '0190a1b2-0000-7000-8000-000000000000';

        $this->assertSame('/admin/content-types/news/nodes', route('admin.content-types.nodes.index', 'news', false));
        $this->assertSame('/admin/content-types/news/nodes/create', route('admin.content-types.nodes.create', 'news', false));
        $this->assertSame('/admin/content-types/news/nodes', route('admin.content-types.nodes.store', 'news', false));
        $this->assertSame("/admin/content-types/news/nodes/{$uuid}/edit", route('admin.content-types.nodes.edit', ['news', $uuid], false));
        $this->assertSame("/admin/content-types/news/nodes/{$uuid}", route('admin.content-types.nodes.update', ['news', $uuid], false));
        $this->assertSame("/admin/content-types/news/nodes/{$uuid}", route('admin.content-types.nodes.destroy', ['news', $uuid], false));
    }

    public function test_page_and_block_sections_serve_their_own_nodes(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        foreach (['page-types/home' => 'page', 'block-types/hero' => 'block'] as $path => $kind) {
            $this->admin()->get("/admin/{$path}/nodes")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Admin/ContentNodes/Index')
                    ->where('section.kind', $kind)
                    ->where('section.basePath', '/admin/'.explode('/', $path)[0]));
            $this->admin()->get("/admin/{$path}/nodes/create")->assertOk();
        }
    }

    public function test_a_section_answers_404_for_a_type_of_another_kind(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        foreach (['page-types/hero', 'block-types/home'] as $path) {
            $this->admin()->get("/admin/{$path}/nodes")->assertNotFound();
            $this->admin()->get("/admin/{$path}/nodes/create")->assertNotFound();
        }
    }

    public function test_a_missing_or_non_content_type_is_not_found(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        foreach (['missing', 'home', 'hero'] as $key) {
            $this->admin()->get("/admin/content-types/{$key}/nodes")->assertNotFound();
        }
    }

    public function test_an_empty_type_opens_with_the_default_columns(): void
    {
        $this->type();

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentNodes/Index')
                ->where('type', ['key' => 'news', 'name' => 'News', 'slug' => null, 'children_count' => 0, 'ancestors' => []])
                ->where('section', ['kind' => 'content', 'basePath' => '/admin/content-types'])
                ->where('columns', [
                    ['key' => 'title', 'name' => 'Title', 'type' => 'text', 'sortable' => true],
                    ['key' => 'slug', 'name' => 'Slug', 'type' => 'text', 'sortable' => false, 'service' => true],
                    ['key' => 'status', 'name' => 'State', 'type' => 'text', 'sortable' => true, 'service' => true],
                    ['key' => 'published_at', 'name' => 'Publication date', 'type' => 'date', 'sortable' => true, 'service' => true],
                    ['key' => 'author', 'name' => 'Author', 'type' => 'text', 'sortable' => false, 'service' => true],
                ])
                ->where('table.rows', [])
                ->where('table.meta.total', 0)
                ->where('table.ignored', []));
    }

    public function test_only_nodes_of_this_type_are_listed_with_their_uuid_slug_and_title(): void
    {
        $news = $this->type();
        $other = $this->type('product');
        $node = $this->node($news, ['title' => 'Про нас']);
        $this->node($other, ['title' => 'Lamp']);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 1)
                ->where('table.rows.0.uuid', $node->uuid)
                ->where('table.rows.0.slug', 'pro-nas')
                ->where('table.rows.0.title', 'Про нас')
                ->where('table.rows.0.status', 'published')
                ->where('table.rows.0.published_at', $node->published_at->format('Y-m-d H:i')));
    }

    public function test_visible_fields_come_from_the_list_settings_in_their_order(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'price', 'tags']]]);
        $this->field('news', 'price', 'decimal');
        $this->field('news', 'tags', 'string', true);
        $this->node($type, ['title' => 'Lamp', 'price' => '12.5', 'tags' => ['a', 'b']]);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('columns', [
                    ['key' => 'title', 'name' => 'Title', 'type' => 'text', 'sortable' => true],
                    ['key' => 'price', 'name' => 'Price', 'type' => 'number', 'sortable' => true],
                    ['key' => 'tags', 'name' => 'Tags', 'type' => 'text', 'sortable' => true],
                ])
                ->where('table.rows.0.price', '12.5')
                ->where('table.rows.0.tags', ['a', 'b']));
    }

    public function test_title_is_always_the_first_column_even_when_the_settings_omit_it(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['price']]]);
        $this->field('news', 'price', 'integer');
        $this->node($type, ['title' => 'Lamp', 'price' => 3]);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('columns.0.key', 'title')
                ->where('columns.1.key', 'price')
                ->where('table.rows.0.title', 'Lamp'));
    }

    public function test_a_deleted_field_in_the_list_settings_is_ignored(): void
    {
        $type = $this->type('news', ['list' => [
            'visible' => ['title', 'gone'],
            'search' => ['gone'],
            'sort' => ['field' => 'gone', 'direction' => 'desc'],
        ]]);
        $this->node($type, ['title' => 'Lamp']);

        $this->admin()->get('/admin/content-types/news/nodes?q=lamp')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('columns', 1)
                ->where('columns.0.key', 'title')
                ->where('table.meta.total', 1));
    }

    public function test_a_field_named_like_a_table_column_is_the_service_column(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'slug']]]);
        $this->field('news', 'slug');
        $this->node($type, ['title' => 'Lamp', 'slug' => 'ignored'], 'lamp');

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->has('columns', 2)
                ->where('columns.1.service', true)
                ->where('table.rows.0.slug', 'lamp'));
    }

    public function test_booleans_stay_boolean_and_dates_become_text(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'live', 'day', 'moment', 'when', 'empty']]]);
        $this->field('news', 'live', 'boolean');
        $this->field('news', 'day', 'date');
        $this->field('news', 'moment', 'datetime');
        $this->field('news', 'when', 'date', true);
        $this->field('news', 'empty', 'integer');
        $this->node($type, [
            'title' => 'Lamp', 'live' => true, 'day' => '2026-03-05', 'moment' => '2026-03-05 14:30:59',
            'when' => ['2026-01-02', '2026-02-03'],
        ]);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.live', true)
                ->where('table.rows.0.day', '2026-03-05')
                ->where('table.rows.0.moment', '2026-03-05 14:30')
                ->where('table.rows.0.when', ['2026-01-02', '2026-02-03'])
                ->where('table.rows.0.empty', null));
    }

    public function test_richtext_value_is_shown_as_plain_shortened_text(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'body', 'notes']]]);
        $this->field('news', 'body', 'richtext');
        $this->field('news', 'notes', 'richtext', true);
        $this->node($type, [
            'title' => 'Lamp', 'body' => '<p>Привіт, <strong>світе</strong></p>',
            'notes' => ['<p>a</p>', '<ul><li>b</li></ul>'],
        ]);
        $this->node($type, ['title' => 'Long', 'body' => '<p>'.str_repeat('x', 300).'</p>']);

        $this->admin()->get('/admin/content-types/news/nodes?sort=title')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.body', 'Привіт, світе')
                ->where('table.rows.0.notes', ['a', 'b'])
                ->where('table.rows.1.body', str_repeat('x', 200).'...'));
    }

    public function test_search_finds_text_values_case_insensitively(): void
    {
        $type = $this->type();
        $this->node($type, ['title' => 'Desk Lamp']);
        $this->node($type, ['title' => 'Chair']);

        $this->admin()->get('/admin/content-types/news/nodes?q=LAMP')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 1)
                ->where('table.rows.0.title', 'Desk Lamp')
                ->where('table.query.search', 'LAMP'));
    }

    public function test_search_finds_a_node_by_its_slug(): void
    {
        $type = $this->type();
        $this->node($type, ['title' => 'Unrelated'], 'summer-sale-2026');
        $this->node($type, ['title' => 'Chair'], 'chair');

        $this->admin()->get('/admin/content-types/news/nodes?q=SUMMER-sale')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 1)
                ->where('table.rows.0.title', 'Unrelated'));
    }

    public function test_search_finds_the_slug_even_with_search_fields_in_the_settings(): void
    {
        $type = $this->type('news', ['list' => ['search' => ['title']]]);
        $this->node($type, ['title' => 'Unrelated'], 'summer-sale-2026');

        $this->admin()->get('/admin/content-types/news/nodes?q=summer-sale')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 1)
                ->where('table.rows.0.title', 'Unrelated'));
    }

    public function test_search_wildcards_are_literal_in_the_slug(): void
    {
        $type = $this->type();
        $this->node($type, ['title' => 'One'], 'a-b');
        $this->node($type, ['title' => 'Two'], 'axb');

        // An unescaped `_` would match both slugs.
        $this->admin()->get('/admin/content-types/news/nodes?q=a_b')
            ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 0));
    }

    public function test_sorting_by_a_field_puts_nodes_without_a_value_last(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'price']]]);
        $this->field('news', 'price', 'integer');
        $this->node($type, ['title' => 'Cheap', 'price' => 1]);
        $this->node($type, ['title' => 'None']);
        $this->node($type, ['title' => 'Dear', 'price' => 9]);

        $this->admin()->get('/admin/content-types/news/nodes?sort=price&dir=desc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.title', 'Dear')
                ->where('table.rows.1.title', 'Cheap')
                ->where('table.rows.2.title', 'None')
                ->where('table.query.sort', 'price'));
    }

    public function test_sorting_by_a_multiple_field_uses_its_first_value(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'tags']]]);
        $this->field('news', 'tags', 'string', true);
        $this->node($type, ['title' => 'Second', 'tags' => ['b', 'a']]);
        $this->node($type, ['title' => 'First', 'tags' => ['a', 'z']]);

        $this->admin()->get('/admin/content-types/news/nodes?sort=tags&dir=asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.query.sort', 'tags')
                ->where('table.rows.0.title', 'First')
                ->where('table.rows.1.title', 'Second'));
    }

    public function test_sorting_by_the_slug_or_a_file_field_is_ignored(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'cover', 'file', 'clip']]]);
        $this->field('news', 'cover', 'image');
        $this->field('news', 'file', 'file');
        $this->field('news', 'clip', 'youtube');
        $this->node($type, ['title' => 'Lamp']);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('columns.1.sortable', false)
                ->where('columns.2.sortable', false)
                ->where('columns.3.sortable', false));

        foreach (['cover', 'file', 'clip', 'slug'] as $column) {
            $this->admin()->get("/admin/content-types/news/nodes?sort={$column}")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('table.query.sort', null)
                    ->where('table.meta.total', 1));
        }
    }

    public function test_the_default_order_may_follow_a_multiple_field_but_not_a_file_field(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'tags'], 'sort' => ['field' => 'tags', 'direction' => 'desc']]]);
        $this->field('news', 'tags', 'string', true);
        $this->node($type, ['title' => 'Low', 'tags' => ['a']]);
        $this->node($type, ['title' => 'High', 'tags' => ['z']]);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.title', 'High')
                ->where('table.rows.1.title', 'Low'));

        $this->field('news', 'cover', 'image');
        DB::table('content_types')->where('key', 'news')->update(['settings' => json_encode(['list' => ['sort' => ['field' => 'cover', 'direction' => 'desc']]])]);

        // The dropped file-field sort leaves the creation order.
        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.title', 'Low')
                ->where('table.rows.1.title', 'High'));
    }

    public function test_a_url_column_shows_the_link_object_and_is_not_sortable(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'site'], 'sort' => ['field' => 'site', 'direction' => 'desc']]]);
        $this->field('news', 'site', 'url');
        $this->field('news', 'extra', 'url', true);
        $this->node($type, ['title' => 'Titled', 'site' => ['url' => '/about', 'title' => 'About us']]);
        $this->node($type, ['title' => 'Bare', 'site' => '/team', 'extra' => [['url' => '/a'], ['url' => '/b', 'title' => 'B']]]);

        // The configured default order by a `url` field is dropped (creation order stays); an explicit one is ignored.
        foreach (['/admin/content-types/news/nodes', '/admin/content-types/news/nodes?sort=site'] as $address) {
            $this->admin()->get($address)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('columns.1.sortable', false)
                    ->where('table.query.sort', null)
                    ->where('table.rows.0.title', 'Titled')
                    ->where('table.rows.0.site', ['url' => '/about', 'title' => 'About us'])
                    ->where('table.rows.1.site', ['url' => '/team', 'title' => null]));
        }
    }

    public function test_the_default_order_follows_the_list_settings(): void
    {
        $type = $this->type('news', ['list' => ['sort' => ['field' => 'title', 'direction' => 'desc']]]);
        $this->node($type, ['title' => 'Alpha']);
        $this->node($type, ['title' => 'Omega']);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.title', 'Omega')
                ->where('table.rows.1.title', 'Alpha'));
    }

    public function test_a_page_past_the_end_shows_the_last_page(): void
    {
        $type = $this->type();

        foreach (range(1, 31) as $i) {
            $this->node($type, ['title' => "Node {$i}"]);
        }

        $this->admin()->get('/admin/content-types/news/nodes?page=999')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.page', 3)
                ->where('table.meta.lastPage', 3)
                ->where('table.meta.total', 31)
                ->has('table.rows', 1)
                ->where('table.query.page', 3));
    }

    public function test_the_page_size_follows_per_page(): void
    {
        $type = $this->type();

        foreach (range(1, 20) as $i) {
            $this->node($type, ['title' => "Node {$i}"]);
        }

        $this->admin()->get('/admin/content-types/news/nodes?per_page=30')
            ->assertInertia(fn (Assert $page) => $page->has('table.rows', 20)->where('table.meta.perPage', 30));
    }

    public function test_bad_address_parameters_are_ignored_without_an_error(): void
    {
        $this->node($this->type(), ['title' => 'Lamp']);

        $this->admin()->get('/admin/content-types/news/nodes?sort=nope&dir=up&page=-1&per_page=7&filters[0][c]=title')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.meta.total', 1)
                ->where('table.query.page', 1)
                ->has('table.ignored'));
    }

    public function test_rows_carry_the_derived_status(): void
    {
        $type = $this->type();
        $this->node($type, ['title' => 'Live'], 'live');
        $this->node($type, ['title' => 'Later'], 'later')->update(['published_at' => now()->addDay()]);
        $this->node($type, ['title' => 'Off'], 'off')->update(['is_active' => false, 'published_at' => now()->addDay()]);

        $this->admin()->get('/admin/content-types/news/nodes?sort=published_at&dir=desc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.status', 'scheduled')
                ->where('table.rows.1.status', 'disabled')
                ->where('table.rows.2.status', 'published'));
    }

    public function test_an_expired_node_has_the_expired_status_and_disabled_wins(): void
    {
        $type = $this->type();
        $this->node($type, ['title' => 'Gone'], 'gone')->update(['published_at' => now()->subDays(3), 'unpublished_at' => now()->subDay()]);
        $this->node($type, ['title' => 'Off'], 'off')->update(['is_active' => false, 'published_at' => now()->subDays(2), 'unpublished_at' => now()->subHour()]);

        $this->admin()->get('/admin/content-types/news/nodes?sort=published_at&dir=asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.status', 'expired')
                ->where('table.rows.1.status', 'disabled'));
    }

    public function test_sorting_by_the_publication_date_is_secondary_ordered_by_title_then_id(): void
    {
        $type = $this->type();
        $same = now()->subDay();
        $a = $this->node($type, ['title' => 'A'], 'aaa');
        $b = $this->node($type, ['title' => 'B'], 'bbb');
        $c = $this->node($type, ['title' => 'C'], 'ccc');
        $a->update(['published_at' => $same]);
        $b->update(['published_at' => $same]);
        $c->update(['published_at' => now()->subDays(5)]);

        $this->admin()->get('/admin/content-types/news/nodes?sort=published_at&dir=asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.slug', 'ccc')
                ->where('table.rows.1.slug', 'aaa')
                ->where('table.rows.2.slug', 'bbb'));
    }

    public function test_a_legacy_field_named_status_does_not_duplicate_the_service_column(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'status', 'published_at']]]);
        $this->field('news', 'status');
        $this->field('news', 'published_at', 'datetime');
        $this->node($type, ['title' => 'Lamp'], 'lamp');

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->has('columns', 3)
                ->where('table.rows.0.status', 'published')
                ->where('table.rows.0.published_at', fn ($value) => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value) === 1));
    }

    public function test_service_columns_follow_the_order_of_visible(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['status', 'price', 'published_at']]]);
        $this->field('news', 'price', 'integer');
        $this->node($type, ['title' => 'Lamp', 'price' => 3], 'lamp');

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('columns', fn ($columns) => collect($columns)->pluck('key')->all() === ['title', 'status', 'price', 'published_at']));
    }

    public function test_all_seven_service_columns_with_their_types_and_values(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['is_active', 'created_at', 'updated_at', 'published_at', 'unpublished_at', 'slug', 'status']]]);
        $node = $this->node($type, ['title' => 'Lamp'], 'lamp');
        $node->forceFill([
            'created_at' => '2026-03-01 08:05:09', 'updated_at' => '2026-03-02 09:06:10',
            'published_at' => '2026-03-03 10:07:11', 'unpublished_at' => '2026-12-31 23:59:59',
        ])->saveQuietly();

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('columns', fn ($columns) => collect($columns)->map(fn ($c) => [$c['key'], $c['type'], $c['sortable']])->all() === [
                    ['title', 'text', true], ['is_active', 'boolean', true], ['created_at', 'date', true], ['updated_at', 'date', true],
                    ['published_at', 'date', true], ['unpublished_at', 'date', true], ['slug', 'text', false], ['status', 'text', true],
                ])
                ->where('table.rows.0.is_active', true)
                ->where('table.rows.0.created_at', '2026-03-01 08:05')
                ->where('table.rows.0.updated_at', '2026-03-02 09:06')
                ->where('table.rows.0.published_at', '2026-03-03 10:07')
                ->where('table.rows.0.unpublished_at', '2026-12-31 23:59')
                ->where('table.rows.0.slug', 'lamp'));
    }

    public function test_an_empty_unpublished_at_is_null_and_a_disabled_node_is_not_active(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['is_active', 'unpublished_at']]]);
        $this->node($type, ['title' => 'Lamp'])->update(['is_active' => false]);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.unpublished_at', null)
                ->where('table.rows.0.is_active', false));
    }

    public function test_service_columns_are_absent_when_visible_lists_only_fields(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['price']]]);
        $this->field('news', 'price', 'integer');
        $this->node($type, ['title' => 'Lamp', 'price' => 3]);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page->has('columns', 2));
    }

    public function test_title_takes_the_position_of_its_key_in_visible_once(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['price', 'title', 'status', 'title']]]);
        $this->field('news', 'price', 'integer');
        $this->node($type, ['title' => 'Lamp', 'price' => 3]);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('columns', fn ($columns) => collect($columns)->pluck('key')->all() === ['price', 'title', 'status'])
                ->where('table.rows.0.title', 'Lamp'));
    }

    public function test_title_is_first_when_visible_has_no_title_key(): void
    {
        $this->type('news', ['list' => ['visible' => ['status', 'slug']]]);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('columns', fn ($columns) => collect($columns)->pluck('key')->all() === ['title', 'status', 'slug']));
    }

    public function test_multiple_booleans_and_empty_lists_are_lists_or_null(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'flags', 'tags']]]);
        $this->field('news', 'flags', 'boolean', true);
        $this->field('news', 'tags', 'string', true);
        $this->node($type, ['title' => 'Lamp', 'flags' => [true, false]]);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.flags', [true, false])
                ->where('table.rows.0.tags', null));
    }

    public function test_the_default_sort_can_be_any_sortable_service_key(): void
    {
        foreach (['created_at', 'updated_at', 'published_at', 'unpublished_at', 'is_active', 'status'] as $key) {
            $type = $this->type('news', ['list' => ['sort' => ['field' => $key, 'direction' => 'desc']]]);
            $this->node($type, ['title' => 'Lamp']);

            $this->admin()->get('/admin/content-types/news/nodes')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('table.meta.total', 1));

            ContentType::where('key', 'news')->delete();
        }
    }

    public function test_the_default_sort_by_created_at_descending_lists_the_newest_first(): void
    {
        $type = $this->type('news', ['list' => ['sort' => ['field' => 'created_at', 'direction' => 'desc']]]);
        $this->node($type, ['title' => 'Old']);
        $this->node($type, ['title' => 'New']);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.title', 'New')
                ->where('table.rows.1.title', 'Old'));
    }

    public function test_a_slug_default_sort_is_dropped_and_sorting_by_slug_is_ignored(): void
    {
        $type = $this->type('news');
        $this->node($type, ['title' => 'B'], 'bbb');
        $this->node($type, ['title' => 'A'], 'aaa');
        // Bypass the model validation, as a stale value written before this rule would be.
        DB::table('content_types')->where('key', 'news')->update(['settings' => json_encode(['list' => ['sort' => ['field' => 'slug', 'direction' => 'desc']]])]);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('table.rows.0.title', 'B'));

        $this->admin()->get('/admin/content-types/news/nodes?sort=slug&dir=desc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('table.query.sort', null));
    }

    public function test_sorting_by_visible_service_columns_from_the_address(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['is_active', 'status']]]);
        $this->node($type, ['title' => 'Off'])->update(['is_active' => false]);
        $this->node($type, ['title' => 'On']);

        $this->admin()->get('/admin/content-types/news/nodes?sort=is_active&dir=asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.query.sort', 'is_active')
                ->where('table.rows.0.title', 'Off'));

        $this->admin()->get('/admin/content-types/news/nodes?sort=status&dir=desc')
            ->assertInertia(fn (Assert $page) => $page->where('table.rows.0.title', 'Off'));
    }

    public function test_the_author_column_shows_the_name_and_is_not_sortable(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'author']]]);
        User::query()->whereKey($this->authorId())->update(['name' => 'Anna Petrenko']);
        $this->node($type, ['title' => 'Written']);

        $this->admin()->get('/admin/content-types/news/nodes?sort=author&dir=asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('columns.1', ['key' => 'author', 'name' => 'Author', 'type' => 'text', 'sortable' => false, 'service' => true])
                ->where('table.query.sort', null)
                ->where('table.rows.0.author', ['name' => 'Anna Petrenko', 'avatar' => null]));
    }

    public function test_the_author_cell_carries_the_avatar_url(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'author']]]);
        User::query()->whereKey($this->authorId())->update(['avatar' => '0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.jpg']);
        $this->node($type, ['title' => 'With avatar']);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows.0.author.avatar', Storage::disk('public')->url('0190a1b27c3e7f0a8b1c2d3e4f5a6b7c.jpg')));
    }

    public function test_the_author_cell_is_empty_for_a_missing_author(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'author']]]);
        $node = $this->node($type, ['title' => 'Orphan']);
        // The check is deferred to the commit, which a test transaction never reaches.
        DB::statement('PRAGMA defer_foreign_keys = ON');
        DB::table('content_nodes')->where('id', $node->id)->update(['user_id' => 999999]);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page->where('table.rows.0.author', null));
    }

    public function test_authors_of_a_page_are_loaded_with_one_query(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'author']]]);
        foreach (range(1, 5) as $i) {
            app(ContentNodeCreator::class)->create($type, ['title' => "N{$i}"], null, authorId: User::factory()->create()->id);
        }
        $admin = User::factory()->create();

        DB::enableQueryLog();
        $this->actingAs($admin)->get('/admin/content-types/news/nodes')->assertOk();
        $userQueries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "users"') && str_contains($q['query'], ' in ('));

        $this->assertCount(1, $userQueries);
    }

    public function test_the_views_column_is_a_sortable_integer(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'views']]]);
        $this->node($type, ['title' => 'Popular'])->incrementViews(1250);
        $this->node($type, ['title' => 'Fresh']);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('columns', fn ($columns) => collect($columns)->map(fn ($c) => [$c['key'], $c['type'], $c['sortable']])->all() === [
                    ['title', 'text', true], ['views', 'number', true],
                ])
                ->where('table.rows', fn ($rows) => collect($rows)->pluck('views', 'title')->all() === ['Popular' => 1251, 'Fresh' => 1]));
    }

    public function test_sorting_by_views_from_the_address_is_numeric(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'views']]]);
        foreach (['V9' => 9, 'V10' => 10, 'V100' => 100] as $title => $views) {
            $this->node($type, ['title' => $title])->incrementViews($views);
        }

        $this->admin()->get('/admin/content-types/news/nodes?sort=views&dir=desc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.query.sort', 'views')
                ->where('table.rows', fn ($rows) => collect($rows)->pluck('title')->all() === ['V100', 'V10', 'V9']));

        $this->admin()->get('/admin/content-types/news/nodes?sort=views&dir=asc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('table.rows', fn ($rows) => collect($rows)->pluck('title')->all() === ['V9', 'V10', 'V100']));
    }

    public function test_the_default_sort_of_a_type_can_be_views(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'views'], 'sort' => ['field' => 'views', 'direction' => 'desc']]]);
        $this->node($type, ['title' => 'Low'])->incrementViews(1);
        $this->node($type, ['title' => 'High'])->incrementViews(9);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page->where('table.rows.0.title', 'High'));
    }

    public function test_a_legacy_field_named_views_does_not_replace_the_service_column(): void
    {
        $type = $this->type('news', ['list' => ['visible' => ['title', 'views']]]);
        $this->field('news', 'views', 'integer');
        $this->node($type, ['title' => 'Lamp', 'views' => 77])->incrementViews(3);

        $this->admin()->get('/admin/content-types/news/nodes')
            ->assertInertia(fn (Assert $page) => $page
                ->where('columns', fn ($columns) => collect($columns)->pluck('key')->all() === ['title', 'views'])
                ->where('table.rows.0.views', 4));
    }

    public function test_each_row_carries_the_public_url_of_the_node(): void
    {
        $news = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        $plain = ContentType::create(['key' => 'plain', 'name' => 'Plain']);
        $home = ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        $hero = ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        $this->node($news, ['title' => 'Hello'], 'hello');
        $this->node($plain, ['title' => 'Loose'], 'loose');
        $this->node($home, ['title' => 'About'], 'about');
        $this->node($hero, ['title' => 'Banner']);

        $cases = [
            ['content-types/news', '/news/hello'],
            ['content-types/plain', null],
            ['page-types/home', '/about'],
            ['block-types/hero', null],
        ];

        foreach ($cases as [$path, $url]) {
            $this->admin()->get("/admin/{$path}/nodes")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('table.rows.0.public_url', $url));
        }
    }
}
