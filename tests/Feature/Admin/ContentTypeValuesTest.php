<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Models\MediaRelation;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * The "SEO" panel of a content type: its tab props and the values route (the "Section" values live on the "Display" tab).
 */
class ContentTypeValuesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function png(): File
    {
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => 'image', 'mime' => 'image/png', 'path' => "{$uuid}.png", 'size' => 1000]);
    }

    /** @return array<string, mixed> */
    private function stored(ContentType $type): array
    {
        return app(ContentValueReader::class)->loadForType($type);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->put('/admin/content-types/news/values', ['values' => ['section_title' => 'X']])->assertRedirect('/login');
        $this->assertSame(0, DB::table('content_values_string')->count());
    }

    public function test_values_are_saved_for_the_type(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        $cover = $this->png();

        $this->admin()->put('/admin/content-types/news/values', ['values' => [
            'section_title' => 'Новини', 'section_description' => '<p>Text</p>', 'section_cover' => $cover->path, 'meta_title' => 'SEO',
        ]])->assertSessionHasNoErrors()->assertSessionHas('success');

        $stored = $this->stored($type);
        $this->assertSame('Новини', $stored['section_title']);
        $this->assertSame('<p>Text</p>', $stored['section_description']);
        $this->assertSame($cover->path, $stored['section_cover']);
        $this->assertSame('SEO', $stored['meta_title']);
        $this->assertSame(1, MediaRelation::query()->where('owner_type', 'content_type')->count());
    }

    public function test_an_empty_value_clears_the_stored_one(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        app(ContentValueWriter::class)->write(ValueOwner::forType($type), ['meta_description' => 'About']);

        $this->admin()->put('/admin/content-types/news/values', ['values' => ['meta_description' => '']])->assertSessionHasNoErrors();

        $this->assertNull($this->stored($type)['meta_description']);
    }

    public function test_an_invalid_value_is_reported_by_field_and_nothing_is_saved(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        app(ContentValueWriter::class)->write(ValueOwner::forType($type), ['section_title' => 'Old']);

        $this->admin()->put('/admin/content-types/news/values', ['values' => ['meta_title' => str_repeat('a', 600), 'section_title' => 'New']])
            ->assertSessionHasErrors('values.meta_title');

        $this->assertSame('Old', $this->stored($type)['section_title']);
    }

    public function test_fields_the_type_does_not_hold_are_refused(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->put('/admin/content-types/news/values', ['values' => ['title' => 'X']])->assertSessionHasErrors('values.title');
        $this->admin()->put('/admin/content-types/news/values', ['values' => ['nope' => 'X']])->assertSessionHasErrors('values.nope');
    }

    public function test_a_page_type_saves_its_meta_values_and_refuses_section_values(): void
    {
        $page = ContentType::create(['key' => 'landing', 'name' => 'Landing', 'kind' => 'page']);
        $this->admin()->put('/admin/page-types/landing/values', ['values' => ['meta_description' => 'About us']])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('About us', $this->stored($page)['meta_description']);

        $this->admin()->put('/admin/page-types/landing/values', ['values' => ['section_title' => 'X']])
            ->assertSessionHasErrors('values.section_title');
    }

    public function test_type_values_of_a_page_type_do_not_touch_node_values(): void
    {
        $page = ContentType::create(['key' => 'landing', 'name' => 'Landing', 'kind' => 'page']);
        $node = ContentNode::factory()->create(['content_type' => 'landing', 'slug' => 'about']);
        app(ContentValueWriter::class)->write(ValueOwner::forNode($node), ['title' => 'About', 'meta_description' => 'Node text']);

        $this->admin()->put('/admin/page-types/landing/values', ['values' => ['meta_description' => 'Type text']])->assertSessionHasNoErrors();

        $this->assertSame('Type text', $this->stored($page)['meta_description']);
        $this->assertSame('Node text', app(ContentValueReader::class)->load([$node], ['meta_description'])[$node->id]['meta_description'] ?? null);
    }

    public function test_values_and_the_meta_choice_are_saved_together_and_keep_other_settings(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentField::create(['content_type' => 'news', 'key' => 'headline', 'name' => 'Headline', 'type' => 'string', 'weight' => 1]);
        $type->update(['settings' => ['list' => ['search' => ['title']], 'public' => ['per_page' => 5]]]);

        $this->admin()->put('/admin/content-types/news/values', [
            'values' => ['meta_description' => 'About news'],
            'settings' => ['public' => ['meta' => ['title' => 'headline', 'description' => '']]],
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('About news', $this->stored($type)['meta_description']);
        $settings = ContentType::findByKey('news')->settings;
        $this->assertSame(['search' => ['title']], $settings['list']);
        $this->assertSame(['per_page' => 5, 'meta' => ['title' => 'headline']], $settings['public']);

        $this->admin()->put('/admin/content-types/news/values', ['values' => [], 'settings' => ['public' => ['meta' => []]]])->assertSessionHasNoErrors();
        $this->assertSame(['per_page' => 5], ContentType::findByKey('news')->settings['public']);
    }

    public function test_a_values_request_without_meta_leaves_the_stored_choice(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentField::create(['content_type' => 'news', 'key' => 'headline', 'name' => 'Headline', 'type' => 'string', 'weight' => 1]);
        $type->update(['settings' => ['public' => ['meta' => ['title' => 'headline']]]]);

        $this->admin()->put('/admin/content-types/news/values', ['values' => ['meta_title' => 'X']])->assertSessionHasNoErrors();

        $this->assertSame(['title' => 'headline'], ContentType::findByKey('news')->settings['public']['meta']);
    }

    public function test_an_invalid_meta_choice_saves_nothing_including_the_values(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->put('/admin/content-types/news/values', [
            'values' => ['meta_description' => 'Typed'],
            'settings' => ['public' => ['meta' => ['image' => 'title']]],
        ])->assertSessionHasErrors('settings');

        $this->assertArrayNotHasKey('meta_description', array_filter($this->stored($type)));
        $this->assertNull(ContentType::findByKey('news')->settings);
    }

    public function test_the_values_route_answers_404_for_wrong_kind_or_block(): void
    {
        ContentType::create(['key' => 'landing', 'name' => 'Landing', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        $this->admin()->put('/admin/content-types/landing/values', ['values' => ['meta_title' => 'X']])->assertNotFound();
        $this->admin()->put('/admin/content-types/hero/values', ['values' => []])->assertNotFound();
        $this->admin()->put('/admin/block-types/hero/values', ['values' => []])->assertNotFound();
        $this->admin()->put('/admin/content-types/missing/values', ['values' => []])->assertNotFound();
    }

    public function test_the_seo_tab_of_a_page_type_carries_the_meta_panel(): void
    {
        $page = ContentType::create(['key' => 'landing', 'name' => 'Landing', 'kind' => 'page']);
        app(ContentValueWriter::class)->write(ValueOwner::forType($page), ['meta_title' => 'Page SEO']);

        $this->admin()->get('/admin/page-types/landing/seo')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Admin/ContentTypes/Seo')
                ->where('typeFields', fn ($fields) => collect($fields)->pluck('key')->all() === ['meta_title', 'meta_description', 'meta_image'])
                ->where('typeValues.meta_title', 'Page SEO'));
    }

    public function test_the_seo_tab_carries_only_its_panel_and_values(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        app(ContentValueWriter::class)->write(ValueOwner::forType($type), ['section_title' => 'Новини', 'meta_title' => 'SEO']);

        $this->admin()->get('/admin/content-types/news/seo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentTypes/Seo')
                ->where('typeFields', fn ($fields) => collect($fields)->pluck('panel', 'key')->all() === [
                    'meta_title' => 'seo', 'meta_description' => 'seo', 'meta_image' => 'seo',
                ])
                ->where('typeValues.meta_title', 'SEO')
                ->missing('typeValues.section_title'));
    }

    public function test_the_settings_page_has_no_panel_props(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->get('/admin/content-types/news/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->missing('typeFields')->missing('typeValues')->missing('typeFiles'));
    }

    public function test_the_tabs_require_login(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->get('/admin/content-types/news/seo')->assertRedirect('/login');
    }

    public function test_the_seo_tab_answers_404_for_block_types_and_the_wrong_section(): void
    {
        ContentType::create(['key' => 'landing', 'name' => 'Landing', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        foreach (['content-types/landing', 'content-types/hero', 'page-types/hero', 'block-types/hero'] as $path) {
            $this->admin()->get("/admin/{$path}/seo")->assertNotFound();
        }
    }

    public function test_saving_one_panel_leaves_the_other_untouched(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        app(ContentValueWriter::class)->write(ValueOwner::forType($type), ['section_title' => 'Old', 'meta_title' => 'Old SEO']);

        $this->admin()->put('/admin/content-types/news/values', ['values' => ['meta_title' => 'New SEO']])->assertSessionHasNoErrors();

        $stored = $this->stored($type);
        $this->assertSame('New SEO', $stored['meta_title']);
        $this->assertSame('Old', $stored['section_title']);
    }
}
