<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentNodeWriteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function type(string $key = 'news'): ContentType
    {
        return ContentType::create(['key' => $key, 'name' => ucfirst($key)]);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function field(string $type, string $key, string $fieldType = 'string', bool $multiple = false, bool $required = false, array $settings = [], int $weight = 10): ContentField
    {
        return ContentField::create([
            'content_type' => $type, 'key' => $key, 'name' => ucfirst($key), 'type' => $fieldType,
            'is_multiple' => $multiple, 'is_required' => $required, 'settings' => $settings ?: null, 'weight' => $weight,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function node(ContentType $type, array $values, ?string $slug = null): ContentNode
    {
        return app(ContentNodeCreator::class)->create($type, $values, $slug, authorId: $this->authorId());
    }

    /**
     * @return array<string, mixed>
     */
    private function values(ContentNode $node, string $lang = 'default'): array
    {
        return app(ContentValueReader::class)->load([$node], null, $lang)[$node->id];
    }

    private function url(ContentNode|string $node = '', string $suffix = ''): string
    {
        $segment = $node instanceof ContentNode ? "/{$node->uuid}" : ($node === '' ? '' : "/{$node}");

        return "/admin/content-types/news/nodes{$segment}{$suffix}";
    }

    public function test_guests_cannot_write(): void
    {
        $node = $this->node($this->type(), ['title' => 'Lamp']);

        $this->post($this->url(), ['values' => ['title' => 'x']])->assertRedirect('/login');
        $this->put($this->url($node), ['published_at' => '2026-01-01T00:00', 'slug' => 'x'])->assertRedirect('/login');
        $this->delete($this->url($node))->assertRedirect('/login');
        $this->get($this->url('create'))->assertRedirect('/login');
        $this->get($this->url($node, '/edit'))->assertRedirect('/login');
    }

    public function test_the_create_form_gets_the_fields_by_weight(): void
    {
        $this->type();
        $this->field('news', 'body', 'text', weight: 20);
        $this->field('news', 'price', 'integer', settings: ['min' => 1, 'max' => 5, 'other' => 'x'], weight: 10);

        $this->admin()->get($this->url('create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentNodes/Create')
                ->where('type', ['key' => 'news', 'name' => 'News', 'slug' => null, 'children_count' => 0, 'ancestors' => []])
                ->where('fields.0', ['key' => 'title', 'name' => 'Title', 'description' => null, 'type' => 'string', 'is_multiple' => false, 'max_items' => 0, 'is_required' => true, 'settings' => []])
                ->where('fields', fn ($fields) => collect($fields)->pluck('key')->intersect(['title', 'price', 'body'])->values()->all() === ['title', 'price', 'body'])
                ->where('fields', fn ($fields) => collect($fields)->firstWhere('key', 'price')['settings'] === ['min' => 1, 'max' => 5]));
    }

    public function test_a_missing_or_non_content_type_is_not_found_on_the_create_form(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);

        $this->admin()->get('/admin/content-types/home/nodes/create')->assertNotFound();
        $this->admin()->get('/admin/block-types/home/nodes/create')->assertNotFound();
        $this->admin()->get('/admin/page-types/missing/nodes/create')->assertNotFound();
    }

    public function test_a_page_node_is_written_in_the_page_section_with_a_slug_from_the_title(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);

        $this->admin()->post('/admin/page-types/home/nodes', ['slug' => '', 'values' => ['title' => 'Про нас']])
            ->assertRedirect('/admin/page-types/home/nodes')
            ->assertSessionHas('success');

        $node = ContentNode::query()->sole();
        $this->assertSame('pro-nas', $node->slug);

        $this->admin()->from("/admin/page-types/home/nodes/{$node->uuid}")->put("/admin/page-types/home/nodes/{$node->uuid}", ['published_at' => '2026-01-01T00:00', 'slug' => 'about', 'values' => ['title' => 'Про нас']])
            ->assertRedirect("/admin/page-types/home/nodes/{$node->uuid}");
        $this->assertSame('about', $node->fresh()->slug);

        $this->admin()->delete("/admin/page-types/home/nodes/{$node->uuid}")->assertRedirect('/admin/page-types/home/nodes');
        $this->assertSame(0, ContentNode::query()->count());
    }

    public function test_a_block_node_gets_a_generated_prefixed_slug_and_rejects_an_unprefixed_one(): void
    {
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);
        $admin = $this->admin();

        $admin->post('/admin/block-types/hero/nodes', ['slug' => '', 'values' => ['title' => 'Banner']])
            ->assertRedirect('/admin/block-types/hero/nodes');
        $this->assertMatchesRegularExpression('/^block-[a-z]+$/', ContentNode::query()->sole()->slug);

        $admin->post('/admin/block-types/hero/nodes', ['slug' => 'banner', 'values' => ['title' => 'Banner']])
            ->assertSessionHasErrors('slug');
        $this->assertSame(1, ContentNode::query()->count());

        $admin->post('/admin/block-types/hero/nodes', ['slug' => 'block-banner', 'values' => ['title' => 'Banner']])
            ->assertRedirect('/admin/block-types/hero/nodes');
        $this->assertTrue(ContentNode::query()->where('slug', 'block-banner')->exists());
    }

    public function test_a_node_of_another_section_is_not_found_in_page_and_block_sections(): void
    {
        $content = $this->node($this->type(), ['title' => 'x']);
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);

        foreach (['page-types/home', 'block-types/home'] as $path) {
            $this->admin()->get("/admin/{$path}/nodes/{$content->uuid}/edit")->assertNotFound();
            $this->admin()->delete("/admin/{$path}/nodes/{$content->uuid}")->assertNotFound();
        }

        $this->assertNotNull($content->fresh());
    }

    public function test_the_forms_get_the_slug_prefix_of_the_section(): void
    {
        $this->type();
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);
        $block = $this->node(ContentType::findByKey('hero'), ['title' => 'Banner']);

        foreach (['content-types/news' => '', 'page-types/home' => '', 'block-types/hero' => 'block-'] as $path => $prefix) {
            $this->admin()->get("/admin/{$path}/nodes/create")
                ->assertInertia(fn (Assert $page) => $page->where('slugPrefix', $prefix));
        }

        $this->admin()->get("/admin/block-types/hero/nodes/{$block->uuid}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('slugPrefix', 'block-'));
    }

    public function test_the_edit_form_carries_the_public_url_of_the_node(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        $entry = $this->node(ContentType::findByKey('news'), ['title' => 'Hello'], 'hello');
        $page = $this->node(ContentType::findByKey('home'), ['title' => 'About'], 'about');
        $block = $this->node(ContentType::findByKey('hero'), ['title' => 'Banner']);

        $cases = [
            ['content-types/news', $entry, '/news/hello'],
            ['page-types/home', $page, '/about'],
            ['block-types/hero', $block, null],
        ];

        foreach ($cases as [$path, $node, $url]) {
            $this->admin()->get("/admin/{$path}/nodes/{$node->uuid}/edit")
                ->assertInertia(fn (Assert $inertia) => $inertia->where('publicUrl', $url));
        }
    }

    public function test_the_edit_form_carries_the_public_url_of_a_node_of_a_nested_type(): void
    {
        $type = ContentType::create(['key' => 'inst', 'name' => 'Inst', 'slug' => 'common/institutions']);
        $node = $this->node($type, ['title' => 'Library'], 'library');

        $this->admin()->get("/admin/content-types/inst/nodes/{$node->uuid}/edit")
            ->assertInertia(fn (Assert $inertia) => $inertia->where('publicUrl', '/common/institutions/library'));
    }

    public function test_a_slug_that_hides_a_type_list_is_rejected_next_to_the_slug_field(): void
    {
        ContentType::create(['key' => 'common', 'name' => 'Common', 'slug' => 'common']);
        ContentType::create(['key' => 'inst', 'name' => 'Inst', 'slug' => 'common/institutions']);
        $admin = $this->admin();

        $admin->from('/admin/content-types/common/nodes/create')
            ->post('/admin/content-types/common/nodes', ['slug' => 'institutions', 'values' => ['title' => 'Hello']])
            ->assertRedirect('/admin/content-types/common/nodes/create')
            ->assertSessionHasErrors(['slug' => __('This slug would hide the list of a content type.')]);
        $this->assertSame(0, ContentNode::query()->count());

        $admin->post('/admin/content-types/common/nodes', ['slug' => 'free', 'values' => ['title' => 'Hello']])
            ->assertSessionHasNoErrors();
        $this->assertSame('free', ContentNode::query()->sole()->slug);
    }

    public function test_store_generates_the_slug_from_the_title(): void
    {
        $this->type();

        $this->admin()->post($this->url(), ['slug' => '', 'values' => ['title' => 'Про нас']])
            ->assertRedirect('/admin/content-types/news/nodes')
            ->assertSessionHas('success');

        $node = ContentNode::query()->sole();
        $this->assertSame('pro-nas', $node->slug);
        $this->assertSame('news', $node->content_type);
        $this->assertSame('Про нас', $this->values($node)['title']);
    }

    public function test_the_forms_ignore_views_and_keep_the_counter(): void
    {
        $this->type();

        $this->admin()->post($this->url(), ['slug' => 'counted', 'views' => 500, 'values' => ['title' => 'Counted']])->assertRedirect();

        $node = ContentNode::query()->sole();
        $this->assertSame(1, $node->views);

        $node->forceFill(['views' => 7])->saveQuietly();

        $this->admin()->from($this->url($node))->put($this->url($node), ['published_at' => '2026-01-01T00:00', 'slug' => 'counted', 'views' => 0, 'values' => ['title' => 'Counted']])
            ->assertRedirect($this->url($node));

        $this->assertSame(7, $node->fresh()->views);
    }

    public function test_store_keeps_an_explicit_slug(): void
    {
        $this->type();

        $this->admin()->post($this->url(), ['slug' => 'about', 'values' => ['title' => 'Про нас']])->assertRedirect();

        $this->assertSame('about', ContentNode::query()->sole()->slug);
    }

    public function test_store_rejects_a_taken_slug_of_any_type(): void
    {
        $this->type();
        $this->node($this->type('product'), ['title' => 'Lamp'], 'about');

        $this->admin()->post($this->url(), ['slug' => 'about', 'values' => ['title' => 'Other']])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, ContentNode::query()->count());
    }

    public function test_store_rejects_a_bad_slug_format(): void
    {
        $this->type();

        foreach (['Про нас', 'About', 'a b', '-a'] as $slug) {
            $this->admin()->post($this->url(), ['slug' => $slug, 'values' => ['title' => 'x']])
                ->assertSessionHasErrors('slug');
        }

        $this->assertSame(0, ContentNode::query()->count());
    }

    public function test_store_requires_the_title(): void
    {
        $this->type();

        $this->admin()->post($this->url(), ['values' => ['title' => '']])
            ->assertSessionHasErrors('values.title');
        $this->admin()->post($this->url(), [])
            ->assertSessionHasErrors('values.title');

        $this->assertSame(0, ContentNode::query()->count());
    }

    public function test_store_rejects_values_that_are_not_an_array(): void
    {
        $this->type();

        $this->admin()->post($this->url(), ['values' => 'title'])->assertSessionHasErrors('values');
    }

    public function test_store_keys_the_error_of_a_multiple_field_element(): void
    {
        $this->type();
        $this->field('news', 'scores', 'integer', multiple: true);

        $this->admin()->post($this->url(), ['values' => ['title' => 'x', 'scores' => ['1', 'abc']]])
            ->assertSessionHasErrors(['values.scores.1']);

        $this->assertSame(0, ContentNode::query()->count());
    }

    public function test_store_applies_the_field_settings(): void
    {
        $this->type();
        $this->field('news', 'rating', 'integer', settings: ['max' => 5]);

        $this->admin()->post($this->url(), ['values' => ['title' => 'x', 'rating' => 7]])
            ->assertSessionHasErrors('values.rating');
        $this->admin()->post($this->url(), ['values' => ['title' => 'x', 'rating' => 5]])
            ->assertSessionHasNoErrors();
    }

    public function test_store_saves_every_field_type_and_the_order_of_a_multiple_field(): void
    {
        $this->type();
        $this->field('news', 'tags', 'string', multiple: true);
        $this->field('news', 'live', 'boolean');
        $this->field('news', 'day', 'date');
        $this->field('news', 'moment', 'datetime');

        $this->admin()->post($this->url(), ['values' => [
            'title' => 'x', 'tags' => ['c', 'a', 'b'], 'live' => true, 'day' => '2026-03-05', 'moment' => '2026-03-05T14:30',
        ]])->assertSessionHasNoErrors();

        $values = $this->values(ContentNode::query()->sole());
        $this->assertSame(['c', 'a', 'b'], $values['tags']);
        $this->assertTrue($values['live']);
        $this->assertSame('2026-03-05', $values['day']->toDateString());
        $this->assertSame('2026-03-05 14:30:00', $values['moment']->format('Y-m-d H:i:s'));
    }

    public function test_edit_form_is_filled_with_the_slug_and_html_formatted_values(): void
    {
        $type = $this->type();
        $this->field('news', 'tags', 'string', multiple: true);
        $this->field('news', 'live', 'boolean');
        $this->field('news', 'day', 'date');
        $this->field('news', 'moment', 'datetime');
        $this->field('news', 'note');
        $node = $this->node($type, [
            'title' => 'Про нас', 'tags' => ['a', 'b'], 'live' => true, 'day' => '2026-03-05', 'moment' => '2026-03-05 14:30:00',
        ], 'pro-nas');

        $this->admin()->get($this->url($node, '/edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentNodes/Edit')
                ->where('node.id', $node->id)
                ->where('node.uuid', $node->uuid)
                ->where('node.slug', 'pro-nas')
                ->where('node.is_active', true)
                ->where('node.published_at', $node->published_at->format('Y-m-d\TH:i'))
                ->where('node.author.name', $node->author->name)
                ->where('node.author.avatar', null)
                ->where('values.title', 'Про нас')
                ->where('values.tags', ['a', 'b'])
                ->where('values.live', true)
                ->where('values.day', '2026-03-05')
                ->where('values.moment', '2026-03-05T14:30')
                ->where('values.note', null));
    }

    public function test_the_edit_form_of_a_multiple_field_without_values_is_an_empty_list(): void
    {
        $type = $this->type();
        $this->field('news', 'tags', 'string', multiple: true);
        $node = $this->node($type, ['title' => 'x']);

        $this->admin()->get($this->url($node, '/edit'))
            ->assertInertia(fn (Assert $page) => $page->where('values.tags', []));
    }

    public function test_update_changes_values_and_keeps_the_slug_and_uuid(): void
    {
        $node = $this->node($this->type(), ['title' => 'Old'], 'old');

        $this->admin()->from($this->url($node))->put($this->url($node), ['published_at' => '2026-01-01T00:00', 'slug' => 'old', 'values' => ['title' => 'New']])
            ->assertRedirect($this->url($node))
            ->assertSessionHas('success');

        $fresh = $node->fresh();
        $this->assertSame('old', $fresh->slug);
        $this->assertSame($node->uuid, $fresh->uuid);
        $this->assertSame('New', $this->values($fresh)['title']);
    }

    public function test_update_clears_an_optional_field(): void
    {
        $type = $this->type();
        $this->field('news', 'summary');
        $this->field('news', 'tags', 'string', multiple: true);
        $node = $this->node($type, ['title' => 'x', 'summary' => 'Short', 'tags' => ['a']]);

        $this->admin()->put($this->url($node), ['published_at' => '2026-01-01T00:00', 'slug' => $node->slug, 'values' => ['title' => 'x', 'summary' => '', 'tags' => []]])
            ->assertSessionHasNoErrors();

        $values = $this->values($node);
        $this->assertNull($values['summary']);
        $this->assertSame([], $values['tags']);
    }

    public function test_update_cannot_clear_a_required_field(): void
    {
        $node = $this->node($this->type(), ['title' => 'x']);

        $this->admin()->put($this->url($node), ['published_at' => '2026-01-01T00:00', 'slug' => $node->slug, 'values' => ['title' => '']])
            ->assertSessionHasErrors('values.title');

        $this->assertSame('x', $this->values($node)['title']);
    }

    public function test_update_keeps_the_order_of_a_multiple_field(): void
    {
        $type = $this->type();
        $this->field('news', 'tags', 'string', multiple: true);
        $node = $this->node($type, ['title' => 'x', 'tags' => ['a', 'b', 'c']]);

        $this->admin()->put($this->url($node), ['published_at' => '2026-01-01T00:00', 'slug' => $node->slug, 'values' => ['title' => 'x', 'tags' => ['c', 'a', 'b']]]);

        $this->assertSame(['c', 'a', 'b'], $this->values($node)['tags']);
    }

    public function test_update_changes_the_slug_but_not_the_uuid_or_the_address(): void
    {
        $node = $this->node($this->type(), ['title' => 'x'], 'old');

        $this->admin()->put($this->url($node), ['published_at' => '2026-01-01T00:00', 'slug' => 'about-us', 'values' => ['title' => 'x']])->assertSessionHasNoErrors();

        $this->assertSame('about-us', $node->fresh()->slug);
        $this->assertSame($node->uuid, $node->fresh()->uuid);
        // The same address still works after the slug changed.
        $this->admin()->get($this->url($node, '/edit'))->assertOk();
        $this->admin()->put($this->url($node), ['published_at' => '2026-01-01T00:00', 'slug' => 'again', 'values' => ['title' => 'x']])->assertSessionHasNoErrors();
    }

    public function test_update_with_an_unchanged_slug_is_not_a_duplicate(): void
    {
        $node = $this->node($this->type(), ['title' => 'x'], 'same');

        $this->admin()->put($this->url($node), ['published_at' => '2026-01-01T00:00', 'slug' => 'same', 'values' => ['title' => 'y']])->assertSessionHasNoErrors();
    }

    public function test_update_saves_an_unchanged_legacy_slug(): void
    {
        $node = app(ContentNodeCreator::class)->create($this->type(), ['title' => 'x'], 'jam-session---1-', authorId: $this->authorId(), legacySlug: true);

        $this->admin()->put($this->url($node), ['published_at' => '2026-01-01T00:00', 'slug' => 'jam-session---1-', 'values' => ['title' => 'y']])
            ->assertSessionHasNoErrors();

        $this->assertSame('jam-session---1-', $node->fresh()->slug);
        $this->assertSame('y', $this->values($node)['title']);
    }

    public function test_update_rejects_a_changed_slug_in_the_legacy_format(): void
    {
        $node = app(ContentNodeCreator::class)->create($this->type(), ['title' => 'x'], 'jam-session---1-', authorId: $this->authorId(), legacySlug: true);

        $this->admin()->put($this->url($node), ['published_at' => '2026-01-01T00:00', 'slug' => 'a---b', 'values' => ['title' => 'y']])
            ->assertSessionHasErrors('slug');

        $this->assertSame('jam-session---1-', $node->fresh()->slug);
    }

    public function test_store_rejects_a_slug_in_the_legacy_format(): void
    {
        $this->type();

        $this->admin()->post($this->url(), ['slug' => 'a---b', 'values' => ['title' => 'x']])->assertSessionHasErrors('slug');

        $this->assertSame(0, ContentNode::query()->count());
    }

    public function test_update_rejects_the_slug_of_another_node_and_changes_nothing(): void
    {
        $type = $this->type();
        $this->node($type, ['title' => 'a'], 'taken');
        $node = $this->node($type, ['title' => 'b'], 'mine');

        $this->admin()->put($this->url($node), ['published_at' => '2026-01-01T00:00', 'slug' => 'taken', 'values' => ['title' => 'changed']])
            ->assertSessionHasErrors('slug');

        $this->assertSame('mine', $node->fresh()->slug);
        $this->assertSame('b', $this->values($node)['title']);
    }

    public function test_a_failed_value_does_not_keep_the_new_slug(): void
    {
        $node = $this->node($this->type(), ['title' => 'x'], 'mine');

        $this->admin()->put($this->url($node), ['published_at' => '2026-01-01T00:00', 'slug' => 'fresh', 'values' => ['title' => '']])
            ->assertSessionHasErrors('values.title');

        $this->assertSame('mine', $node->fresh()->slug);
    }

    public function test_update_does_not_touch_other_languages(): void
    {
        $node = $this->node($this->type(), ['title' => 'Привіт']);
        DB::table('content_values_string')->insert([
            'content_node_id' => $node->id, 'content_node_uuid' => $node->uuid, 'content_type' => 'news',
            'field_key' => 'title', 'lang' => 'en', 'weight' => 0, 'value' => 'Hello',
        ]);

        $this->admin()->put($this->url($node), ['published_at' => '2026-01-01T00:00', 'slug' => $node->slug, 'values' => ['title' => 'Змінено']]);

        $this->assertSame('Змінено', $this->values($node)['title']);
        $this->assertSame('Hello', DB::table('content_values_string')->where('lang', 'en')->value('value'));
    }

    public function test_destroy_removes_the_node_with_its_values_in_all_languages(): void
    {
        $type = $this->type();
        $this->field('news', 'visits', 'integer');
        $node = $this->node($type, ['title' => 'x', 'visits' => 3]);
        $keep = $this->node($type, ['title' => 'keep']);
        DB::table('content_values_string')->insert([
            'content_node_id' => $node->id, 'content_node_uuid' => $node->uuid, 'content_type' => 'news',
            'field_key' => 'title', 'lang' => 'en', 'weight' => 0, 'value' => 'Hello',
        ]);

        $this->admin()->delete($this->url($node))
            ->assertRedirect('/admin/content-types/news/nodes')
            ->assertSessionHas('success');

        $this->assertNull(ContentNode::find($node->id));
        $this->assertSame(0, DB::table('content_values_string')->where('content_node_id', $node->id)->count());
        $this->assertSame(0, DB::table('content_values_integer')->where('content_node_id', $node->id)->count());
        $this->assertNotNull(ContentNode::find($keep->id));
        $this->assertSame(1, DB::table('content_values_string')->where('content_node_id', $keep->id)->count());
    }

    public function test_a_slug_in_the_address_is_not_found(): void
    {
        $node = $this->node($this->type(), ['title' => 'x'], 'about');

        $this->admin()->get('/admin/content-types/news/nodes/about/edit')->assertNotFound();
        $this->admin()->put('/admin/content-types/news/nodes/about', ['published_at' => '2026-01-01T00:00', 'slug' => 'x', 'values' => ['title' => 'y']])->assertNotFound();
        $this->admin()->delete('/admin/content-types/news/nodes/about')->assertNotFound();

        $this->assertNotNull($node->fresh());
    }

    public function test_a_node_of_another_type_or_an_unknown_uuid_is_not_found(): void
    {
        $this->type();
        $foreign = $this->node($this->type('product'), ['title' => 'Lamp']);
        $unknown = '0190a1b2-0000-7000-8000-000000000000';

        foreach ([$foreign->uuid, $unknown] as $uuid) {
            $this->admin()->get($this->url($uuid, '/edit'))->assertNotFound();
            $this->admin()->put($this->url($uuid), ['published_at' => '2026-01-01T00:00', 'slug' => 'x', 'values' => ['title' => 'y']])->assertNotFound();
            $this->admin()->delete($this->url($uuid))->assertNotFound();
        }

        $this->assertNotNull($foreign->fresh());
        $this->assertSame('Lamp', $this->values($foreign)['title']);
    }

    public function test_form_fields_carry_max_items(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentField::create(['content_type' => 'news', 'key' => 'tags', 'name' => 'Tags', 'type' => 'string', 'is_multiple' => true, 'max_items' => 2, 'weight' => 5]);

        $this->admin()->get('/admin/content-types/news/nodes/create')
            ->assertInertia(fn (Assert $page) => $page->where('fields', fn ($fields) => collect($fields)->firstWhere('key', 'tags')['max_items'] === 2));
    }

    public function test_the_create_form_gets_the_service_attributes(): void
    {
        $this->type();

        $this->admin()->get($this->url('create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('node.slug', '')
                ->missing('node.id')
                ->where('node.is_active', true)
                ->where('node.updated_at', null)
                ->has('node.published_at')
                ->has('node.author.name'));
    }

    public function test_a_node_is_created_with_a_scheduled_date_and_the_current_user_as_author(): void
    {
        $this->type();
        $other = User::factory()->create();

        $this->admin()->post($this->url(), [
            'slug' => 'later', 'is_active' => true, 'published_at' => '2030-05-06T07:08', 'user_id' => $other->id,
            'values' => ['title' => 'Later'],
        ])->assertSessionHasNoErrors();

        $node = ContentNode::where('slug', 'later')->firstOrFail();
        $this->assertSame('2030-05-06 07:08', $node->published_at->format('Y-m-d H:i'));
        $this->assertNotSame($other->id, $node->user_id);
        $this->assertSame(auth()->id(), $node->user_id);
    }

    public function test_a_node_is_created_with_an_unpublish_date(): void
    {
        $this->type();

        $this->admin()->post($this->url(), [
            'slug' => 'promo', 'published_at' => '2030-05-06T07:08', 'unpublished_at' => '2030-05-07T09:00', 'values' => ['title' => 'Promo'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('2030-05-07 09:00', ContentNode::where('slug', 'promo')->firstOrFail()->unpublished_at->format('Y-m-d H:i'));
    }

    public function test_an_equal_or_earlier_unpublish_date_or_a_past_one_without_publish_date_is_rejected(): void
    {
        $this->type();

        foreach ([
            ['published_at' => '2030-05-06T07:08', 'unpublished_at' => '2030-05-06T07:08'],
            ['published_at' => '2030-05-06T07:08', 'unpublished_at' => '2030-05-05T07:08'],
            ['published_at' => null, 'unpublished_at' => '2020-01-01T00:00'],
            ['published_at' => '2030-05-06T07:08', 'unpublished_at' => 'tomorrow'],
        ] as $dates) {
            $this->admin()->post($this->url(), ['slug' => 'bad', 'values' => ['title' => 'x'], ...$dates])
                ->assertSessionHasErrors('unpublished_at');
        }

        $this->assertSame(0, ContentNode::where('slug', 'bad')->count());
    }

    public function test_the_unpublish_date_is_returned_changed_cleared_and_kept_when_absent(): void
    {
        $node = $this->node($this->type(), ['title' => 'x'], 'x');
        $url = $this->url($node);
        $base = ['published_at' => '2030-01-01T00:00', 'slug' => $node->slug, 'values' => ['title' => 'x']];

        $this->admin()->put($url, [...$base, 'unpublished_at' => '2030-02-01T10:30'])->assertSessionHasNoErrors();
        $this->assertSame('2030-02-01 10:30', $node->fresh()->unpublished_at->format('Y-m-d H:i'));

        $this->admin()->get($this->url($node, '/edit'))->assertInertia(fn (Assert $page) => $page->where('node.unpublished_at', '2030-02-01T10:30'));

        $this->admin()->put($url, $base)->assertSessionHasNoErrors();
        $this->assertNotNull($node->fresh()->unpublished_at);

        $this->admin()->put($url, [...$base, 'is_active' => false, 'unpublished_at' => ''])->assertSessionHasNoErrors();
        $this->assertNull($node->fresh()->unpublished_at);

        $this->admin()->put($url, [...$base, 'unpublished_at' => '2029-12-31T00:00'])->assertSessionHasErrors('unpublished_at');
    }

    public function test_an_empty_publication_date_on_create_means_now(): void
    {
        $this->type();

        $this->admin()->post($this->url(), ['slug' => 'now', 'published_at' => null, 'values' => ['title' => 'Now']]);

        $node = ContentNode::where('slug', 'now')->firstOrFail();
        $this->assertTrue($node->is_active);
        $this->assertTrue($node->published_at->diffInSeconds(now(), true) < 120);
    }

    public function test_a_bad_publication_date_or_flag_is_rejected(): void
    {
        $this->type();

        $this->admin()->post($this->url(), ['published_at' => '06.05.2030', 'is_active' => 'maybe', 'values' => ['title' => 'x']])
            ->assertSessionHasErrors(['published_at', 'is_active']);
        $this->assertSame(0, ContentNode::count());
    }

    public function test_update_changes_the_service_attributes_but_not_the_author(): void
    {
        $node = $this->node($this->type(), ['title' => 'Lamp'], 'lamp');
        $author = $node->user_id;
        $other = User::factory()->create();

        $this->admin()->put($this->url($node), [
            'slug' => 'lamp', 'is_active' => false, 'published_at' => '2031-01-02T03:04', 'user_id' => $other->id,
            'values' => ['title' => 'Lamp'],
        ])->assertSessionHasNoErrors();

        $node->refresh();
        $this->assertFalse($node->is_active);
        $this->assertSame('2031-01-02 03:04', $node->published_at->format('Y-m-d H:i'));
        $this->assertSame($author, $node->user_id);
    }

    public function test_disabling_keeps_the_date_and_a_cleared_date_is_an_error(): void
    {
        $node = $this->node($this->type(), ['title' => 'Lamp'], 'lamp');
        $node->update(['published_at' => '2026-02-03 04:05:00']);

        $this->admin()->put($this->url($node), ['slug' => 'lamp', 'is_active' => false, 'published_at' => '2026-02-03T04:05', 'values' => ['title' => 'Lamp']]);
        $this->assertSame('2026-02-03 04:05', $node->fresh()->published_at->format('Y-m-d H:i'));

        $this->admin()->put($this->url($node), ['slug' => 'lamp', 'published_at' => '', 'values' => ['title' => 'Lamp']])
            ->assertSessionHasErrors('published_at');
    }

    public function test_updating_only_a_value_moves_updated_at(): void
    {
        $node = $this->node($this->type(), ['title' => 'Lamp'], 'lamp');
        ContentNode::whereKey($node->id)->update(['updated_at' => '2020-01-01 00:00:00']);

        $this->admin()->put($this->url($node), [
            'slug' => 'lamp', 'published_at' => $node->published_at->format('Y-m-d\TH:i'), 'values' => ['title' => 'Changed'],
        ])->assertSessionHasNoErrors();

        $this->assertTrue($node->fresh()->updated_at->isAfter('2020-01-02'));
    }

    public function test_a_page_slug_that_equals_a_type_slug_or_a_reserved_path_is_rejected_next_to_the_slug_field(): void
    {
        ContentType::create(['key' => 'catalog', 'name' => 'Catalog', 'slug' => 'catalog']);
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        $admin = $this->admin();

        $admin->post('/admin/page-types/home/nodes', ['slug' => 'catalog', 'values' => ['title' => 'Catalog']])
            ->assertSessionHasErrors(['slug' => 'This slug is already used by a content type.']);
        $admin->post('/admin/page-types/home/nodes', ['slug' => 'login', 'values' => ['title' => 'Login']])
            ->assertSessionHasErrors(['slug' => 'This slug is reserved.']);
        $this->assertSame(0, ContentNode::query()->count());

        $admin->post('/admin/page-types/home/nodes', ['slug' => 'about', 'values' => ['title' => 'About']])->assertSessionHasNoErrors();
        $node = ContentNode::query()->sole();

        $admin->put("/admin/page-types/home/nodes/{$node->uuid}", ['published_at' => '2026-01-01T00:00', 'slug' => 'admin', 'values' => ['title' => 'About']])
            ->assertSessionHasErrors(['slug' => 'This slug is reserved.']);
        $this->assertSame('about', $node->fresh()->slug);
    }

    public function test_saving_a_page_without_changing_its_slug_passes_even_if_the_slug_collides(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        $node = $this->node(ContentType::findByKey('home'), ['title' => 'About'], 'about');
        // A collision that predates the rule: the type got the slug around the model.
        DB::table('content_types')->insert(['key' => 'catalog', 'name' => 'Catalog', 'slug' => 'about', 'kind' => 'content', 'created_at' => now(), 'updated_at' => now()]);

        $this->admin()->put("/admin/page-types/home/nodes/{$node->uuid}", ['published_at' => '2026-01-01T00:00', 'slug' => 'about', 'values' => ['title' => 'About us']])
            ->assertSessionHasNoErrors();
    }

    public function test_a_content_node_may_use_the_slug_of_a_type(): void
    {
        $this->type('news');
        ContentType::create(['key' => 'catalog', 'name' => 'Catalog', 'slug' => 'catalog']);

        $this->admin()->post($this->url(), ['slug' => 'catalog', 'values' => ['title' => 'Catalog']])
            ->assertSessionHasNoErrors();
        $this->assertSame('catalog', ContentNode::query()->sole()->slug);
    }

    public function test_an_automatic_page_slug_avoids_reserved_paths(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);

        $this->admin()->post('/admin/page-types/home/nodes', ['slug' => '', 'values' => ['title' => 'Admin']])
            ->assertSessionHasNoErrors();

        $this->assertSame('admin-2', ContentNode::query()->sole()->slug);
    }

    public function test_set_active_changes_only_the_flag_and_moves_updated_at(): void
    {
        $node = $this->node($this->type(), ['title' => 'Lamp']);
        $node->forceFill(['published_at' => now()->addDay()->startOfSecond(), 'unpublished_at' => now()->addWeek()->startOfSecond()])->save();
        $node->refresh();
        ContentNode::query()->whereKey($node->id)->update(['updated_at' => now()->subDay()]);
        $updated = $node->fresh()->updated_at;

        $this->admin()->patch($this->url($node, '/active'), ['is_active' => false])
            ->assertRedirect()
            ->assertSessionHas('success', 'Node disabled.');

        $fresh = $node->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertTrue($fresh->published_at->equalTo($node->published_at));
        $this->assertTrue($fresh->unpublished_at->equalTo($node->unpublished_at));
        $this->assertTrue($fresh->updated_at->greaterThan($updated));
        $this->assertSame('Lamp', $this->values($node)['title']);

        $this->admin()->patch($this->url($node, '/active'), ['is_active' => true])->assertSessionHas('success', 'Node enabled.');
        $this->assertTrue($node->fresh()->is_active);
        $this->assertSame('scheduled', $node->fresh()->status());
    }

    public function test_set_active_rejects_a_missing_or_non_boolean_value(): void
    {
        $node = $this->node($this->type(), ['title' => 'Lamp']);

        $this->admin()->patch($this->url($node, '/active'), [])->assertSessionHasErrors('is_active');
        $this->admin()->patch($this->url($node, '/active'), ['is_active' => 'maybe'])->assertSessionHasErrors('is_active');

        $this->assertTrue($node->fresh()->is_active);
    }

    public function test_set_active_works_in_the_page_and_block_sections_and_is_closed_for_guests(): void
    {
        $node = $this->node($this->type(), ['title' => 'Lamp']);

        $this->patch($this->url($node, '/active'), ['is_active' => false])->assertRedirect('/login');

        foreach (['page' => 'page-types', 'block' => 'block-types'] as $kind => $path) {
            $type = ContentType::create(['key' => "{$kind}x", 'name' => $kind, 'kind' => $kind]);
            $own = $this->node($type, ['title' => 'T']);

            $this->admin()->patch("/admin/{$path}/{$type->key}/nodes/{$own->uuid}/active", ['is_active' => false])->assertSessionHas('success');
            $this->assertFalse($own->fresh()->is_active);
        }
    }
}
