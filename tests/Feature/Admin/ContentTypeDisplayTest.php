<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * The "Display" tab of a type of any kind: its props and the single save of the section values and `settings.public`.
 */
class ContentTypeDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function newsWithFields(): ContentType
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentField::create(['content_type' => 'news', 'key' => 'price', 'name' => 'Price', 'type' => 'integer', 'weight' => 10]);

        return $type;
    }

    /** @param  array<string, mixed>  $public */
    private function savePublic(array $public, string $key = 'news', string $base = 'content-types'): TestResponse
    {
        return $this->admin()->put("/admin/{$base}/{$key}/display", ['settings' => ['public' => $public]]);
    }

    /** @return array<string, mixed> */
    private function stored(ContentType $type): array
    {
        return app(ContentValueReader::class)->loadForType($type);
    }

    public function test_the_routes_have_their_names_and_guests_are_redirected(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->assertSame('/admin/content-types/news/display', route('admin.content-types.display', 'news', false));
        $this->assertSame('/admin/page-types/news/display', route('admin.page-types.display.update', 'news', false));
        $this->get('/admin/content-types/news/display')->assertRedirect('/login');
        $this->put('/admin/content-types/news/display', ['values' => ['section_title' => 'X']])->assertRedirect('/login');
        $this->assertNull($this->stored(ContentType::findByKey('news'))['section_title'] ?? null);
    }

    public function test_the_old_section_address_answers_404(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);

        $this->admin()->get('/admin/content-types/news/section')->assertNotFound();
    }

    public function test_a_content_type_tab_carries_the_section_panel_and_public_options(): void
    {
        $type = $this->newsWithFields();
        $uuid = File::newUuid();
        $cover = File::factory()->create(['uuid' => $uuid, 'profile' => 'image', 'mime' => 'image/png', 'path' => "{$uuid}.png", 'size' => 1000]);
        app(ContentValueWriter::class)->write(ValueOwner::forType($type), ['section_title' => 'Новини', 'section_cover' => $cover->path, 'meta_title' => 'SEO']);

        $this->admin()->get('/admin/content-types/news/display')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/ContentTypes/Display')
                ->where('typeFields', fn ($fields) => collect($fields)->pluck('panel', 'key')->all() === [
                    'section_title' => 'section', 'section_description' => 'section', 'section_cover' => 'section',
                ])
                ->where('typeValues.section_title', 'Новини')
                ->missing('typeValues.meta_title')
                ->where('typeFiles', fn ($files) => ($files[$cover->path]['uuid'] ?? null) === $cover->uuid)
                ->where('publicOptions.fields', fn ($fields) => collect($fields)->pluck('key')->all() === ['price'])
                ->where('publicOptions.current.all_page_fields', true)
                ->where('publicOptions.maxPerPage', 100));
    }

    public function test_page_and_block_tabs_carry_public_options_and_no_section_values(): void
    {
        ContentType::create(['key' => 'landing', 'name' => 'Landing', 'kind' => 'page']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);
        ContentField::create(['content_type' => 'landing', 'key' => 'intro', 'name' => 'Intro', 'type' => 'text', 'weight' => 10]);

        foreach (['page-types/landing', 'block-types/hero'] as $path) {
            $this->admin()->get("/admin/{$path}/display")
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Admin/ContentTypes/Display')
                    ->where('typeFields', [])
                    ->has('publicOptions.current'));
        }

        $this->admin()->get('/admin/page-types/landing/display')
            ->assertInertia(fn (Assert $page) => $page->where('publicOptions.fields', fn ($fields) => collect($fields)->pluck('key')->all() === ['intro']));
    }

    public function test_the_tab_of_another_kind_answers_404(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentType::create(['key' => 'landing', 'name' => 'Landing', 'kind' => 'page']);

        $this->admin()->get('/admin/page-types/news/display')->assertNotFound();
        $this->admin()->get('/admin/content-types/landing/display')->assertNotFound();
        $this->admin()->put('/admin/content-types/landing/display', ['values' => []])->assertNotFound();
    }

    public function test_values_and_public_settings_are_saved_together_and_the_list_survives(): void
    {
        $this->newsWithFields()->update(['settings' => ['list' => ['visible' => ['price']], 'other' => 1]]);

        $this->admin()->put('/admin/content-types/news/display', [
            'values' => ['section_title' => 'Новини'],
            'settings' => ['public' => [
                'list_fields' => ['price'], 'page_fields' => ['price'],
                'sort' => ['field' => 'price', 'direction' => 'asc'], 'per_page' => 10, 'pagination' => false,
            ]],
        ])->assertSessionHasNoErrors();

        $type = ContentType::findByKey('news');
        $this->assertSame('Новини', $this->stored($type)['section_title']);
        $this->assertSame(['visible' => ['price']], $type->settings['list']);
        $this->assertSame(1, $type->settings['other']);
        $this->assertSame([
            'page_fields' => ['price'], 'list_fields' => ['price'],
            'sort' => ['field' => 'price', 'direction' => 'asc'], 'per_page' => 10, 'pagination' => false,
        ], $type->settings['public']);
    }

    public function test_the_public_sort_can_be_views(): void
    {
        $this->newsWithFields();

        $this->savePublic(['sort' => ['field' => 'views', 'direction' => 'desc']])->assertSessionHasNoErrors();

        $this->assertSame(['field' => 'views', 'direction' => 'desc'], ContentType::findByKey('news')->settings['public']['sort']);
    }

    public function test_an_invalid_value_saves_nothing_and_is_reported_by_field(): void
    {
        $type = $this->newsWithFields();
        app(ContentValueWriter::class)->write(ValueOwner::forType($type), ['section_title' => 'Old']);

        $this->admin()->put('/admin/content-types/news/display', [
            'values' => ['section_title' => 'New', 'section_description' => 123],
            'settings' => ['public' => ['per_page' => 5]],
        ])->assertSessionHasErrors('values.section_description');

        $this->assertSame('Old', $this->stored($type)['section_title']);
        $this->assertNull(ContentType::findByKey('news')->settings);
    }

    public function test_invalid_public_settings_roll_back_the_values(): void
    {
        $type = $this->newsWithFields();
        app(ContentValueWriter::class)->write(ValueOwner::forType($type), ['section_title' => 'Old']);

        $this->admin()->put('/admin/content-types/news/display', [
            'values' => ['section_title' => 'New'],
            'settings' => ['public' => ['page_fields' => ['missing']]],
        ])->assertSessionHasErrors('settings');

        $this->assertSame('Old', $this->stored($type)['section_title']);
        $this->assertNull(ContentType::findByKey('news')->settings);
    }

    public function test_defaults_are_not_stored_and_all_fields_means_no_page_fields_key(): void
    {
        $this->newsWithFields()->update(['settings' => ['public' => ['page_fields' => ['price'], 'per_page' => 5]]]);

        $this->savePublic([
            'list_fields' => [], 'sort' => ['field' => 'published_at', 'direction' => 'desc'], 'per_page' => 0, 'pagination' => true,
        ])->assertSessionHasNoErrors();

        $this->assertNull(ContentType::findByKey('news')->settings);
    }

    public function test_bad_public_settings_are_rejected(): void
    {
        $this->newsWithFields();

        $this->savePublic(['per_page' => -1])->assertSessionHasErrors('settings.public.per_page');
        $this->savePublic(['per_page' => 1000])->assertSessionHasErrors('settings.public.per_page');
        $this->savePublic(['sort' => ['field' => 'price', 'direction' => 'up']])->assertSessionHasErrors('settings.public.sort.direction');
        $this->savePublic(['list_fields' => ['title']])->assertSessionHasErrors('settings');
        $this->savePublic(['page_fields' => ['missing']])->assertSessionHasErrors('settings');
        $this->savePublic(['unknown' => 1])->assertSessionHasErrors('settings.public');

        $this->assertNull(ContentType::findByKey('news')->settings);
    }

    public function test_a_page_rejects_list_keys_ignores_values_and_accepts_page_fields(): void
    {
        ContentType::create(['key' => 'info', 'name' => 'Info', 'kind' => 'page']);
        ContentField::create(['content_type' => 'info', 'key' => 'intro', 'name' => 'Intro', 'type' => 'text', 'weight' => 10]);

        $this->savePublic(['per_page' => 0], 'info', 'page-types')->assertSessionHasErrors('settings');
        $this->savePublic(['list_fields' => []], 'info', 'page-types')->assertSessionHasErrors('settings');
        $this->assertNull(ContentType::findByKey('info')->settings);

        $this->admin()->put('/admin/page-types/info/display', [
            'values' => ['section_title' => 'X'],
            'settings' => ['public' => ['page_fields' => ['intro']]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['page_fields' => ['intro']], ContentType::findByKey('info')->settings['public']);
        $this->assertSame([], array_filter($this->stored(ContentType::findByKey('info')), fn ($v) => $v !== null)); // page types hold only meta_* values, all empty
    }

    public function test_saving_without_public_settings_keeps_the_stored_ones(): void
    {
        $type = $this->newsWithFields();
        $type->update(['settings' => ['public' => ['per_page' => 5]]]);

        $this->admin()->put('/admin/content-types/news/display', ['values' => ['section_title' => 'Новини']])->assertSessionHasNoErrors();

        $this->assertSame(['per_page' => 5], ContentType::findByKey('news')->settings['public']);
    }

    private function newsWithMetaFields(string $key = 'news', string $kind = 'content'): ContentType
    {
        $type = ContentType::create(['key' => $key, 'name' => ucfirst($key), 'kind' => $kind]);

        foreach ([['headline', 'string'], ['summary', 'text'], ['body', 'richtext'], ['cover', 'image'], ['clip', 'youtube'], ['price', 'integer']] as $weight => [$field, $fieldType]) {
            ContentField::create(['content_type' => $key, 'key' => $field, 'name' => ucfirst($field), 'type' => $fieldType, 'weight' => $weight + 1]);
        }

        return $type;
    }

    /** @param  array<string, mixed>  $meta */
    private function saveMeta(array $meta, string $key = 'news', string $base = 'content-types'): TestResponse
    {
        return $this->admin()->put("/admin/{$base}/{$key}/values", ['values' => [], 'settings' => ['public' => ['meta' => $meta]]]);
    }

    public function test_meta_fields_are_saved_and_offered_again_on_the_seo_tab(): void
    {
        $this->newsWithMetaFields();
        $meta = ['title' => 'headline', 'description' => 'summary', 'image' => 'cover'];

        $this->saveMeta($meta)->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame($meta, ContentType::findByKey('news')->settings['public']['meta']);
        $this->admin()->get('/admin/content-types/news/seo')->assertInertia(fn (Assert $page) => $page
            ->where('metaOptions.current', $meta)
            ->where('metaOptions.metaFields', fn ($fields) => collect($fields)->map(fn ($list) => collect($list)->pluck('key')->all())->all() === [
                'title' => ['headline'], 'description' => ['summary', 'body'], 'image' => ['cover', 'clip'],
            ]));
    }

    public function test_the_node_title_cannot_be_chosen_for_any_meta(): void
    {
        $this->newsWithMetaFields();
        $this->saveMeta(['title' => 'headline'])->assertSessionHasNoErrors();

        $this->saveMeta(['title' => 'title'])->assertSessionHasErrors('settings');
        $this->saveMeta(['description' => 'title'])->assertSessionHasErrors('settings');
        $this->saveMeta(['image' => 'title'])->assertSessionHasErrors('settings');
        $this->assertSame(['title' => 'headline'], ContentType::findByKey('news')->settings['public']['meta']);
    }

    public function test_a_youtube_field_can_be_the_meta_image_and_a_richtext_the_description(): void
    {
        $this->newsWithMetaFields();

        $this->saveMeta(['description' => 'body', 'image' => 'clip'])->assertSessionHasNoErrors();

        $this->assertSame(['description' => 'body', 'image' => 'clip'], ContentType::findByKey('news')->settings['public']['meta']);
    }

    public function test_meta_fields_of_a_page_type_are_saved(): void
    {
        $this->newsWithMetaFields('info', 'page');

        $this->saveMeta(['title' => 'headline'], 'info', 'page-types')->assertSessionHasNoErrors();

        $this->assertSame(['title' => 'headline'], ContentType::findByKey('info')->settings['public']['meta']);
    }

    public function test_a_meta_field_of_a_wrong_type_or_a_missing_one_is_rejected(): void
    {
        $this->newsWithMetaFields();

        $this->saveMeta(['image' => 'headline'])->assertSessionHasErrors('settings');
        $this->saveMeta(['title' => 'summary'])->assertSessionHasErrors('settings');
        $this->saveMeta(['description' => 'missing'])->assertSessionHasErrors('settings');
        $this->saveMeta(['image' => 'price'])->assertSessionHasErrors('settings');
        $this->saveMeta(['unknown' => 'headline'])->assertSessionHasErrors('settings.public.meta');

        $this->assertNull(ContentType::findByKey('news')->settings);
    }

    public function test_empty_meta_is_not_stored_and_clears_a_stored_one(): void
    {
        $this->newsWithMetaFields();
        $this->saveMeta(['title' => 'headline'])->assertSessionHasNoErrors();

        $this->saveMeta(['title' => '', 'description' => null, 'image' => ''])->assertSessionHasNoErrors();

        $this->assertNull(ContentType::findByKey('news')->settings);
    }

    public function test_saving_meta_leaves_the_other_settings_untouched(): void
    {
        $this->newsWithMetaFields();
        $this->savePublic(['page_fields' => ['price'], 'per_page' => 5])->assertSessionHasNoErrors();
        ContentType::findByKey('news')->update(['settings' => [...ContentType::findByKey('news')->settings, 'list' => ['search' => ['title']]]]);

        $this->saveMeta(['title' => 'headline'])->assertSessionHasNoErrors();

        $settings = ContentType::findByKey('news')->settings;
        $this->assertSame(['search' => ['title']], $settings['list']);
        $this->assertSame(['page_fields' => ['price'], 'per_page' => 5, 'meta' => ['title' => 'headline']], $settings['public']);

        $this->saveMeta([])->assertSessionHasNoErrors();
        $this->assertSame(['page_fields' => ['price'], 'per_page' => 5], ContentType::findByKey('news')->settings['public']);
    }

    public function test_saving_the_display_tab_keeps_the_stored_meta_and_refuses_a_submitted_one(): void
    {
        $this->newsWithMetaFields();
        $this->saveMeta(['title' => 'headline'])->assertSessionHasNoErrors();

        $this->savePublic(['page_fields' => ['price']])->assertSessionHasNoErrors();
        $this->assertSame(['title' => 'headline'], ContentType::findByKey('news')->settings['public']['meta']);
        $this->assertSame(['price'], ContentType::findByKey('news')->settings['public']['page_fields']);

        $this->savePublic(['meta' => ['title' => 'title']])->assertSessionHasErrors('settings.public');
        $this->assertSame(['title' => 'headline'], ContentType::findByKey('news')->settings['public']['meta']);
    }

    public function test_the_display_tab_no_longer_offers_the_meta_data(): void
    {
        $this->newsWithMetaFields();
        $this->saveMeta(['title' => 'headline'])->assertSessionHasNoErrors();

        $this->admin()->get('/admin/content-types/news/display')->assertInertia(fn (Assert $page) => $page
            ->missing('publicOptions.metaFields')->missing('publicOptions.current.meta'));
    }

    public function test_a_block_type_has_no_seo_tab_and_no_meta_route(): void
    {
        $this->newsWithMetaFields('hero', 'block');

        $this->saveMeta(['title' => 'headline'], 'hero', 'block-types')->assertNotFound();
        $this->saveMeta(['title' => 'headline'], 'hero', 'content-types')->assertNotFound();
        $this->assertNull(ContentType::findByKey('hero')->settings);

        // A block cannot hold `meta` even when the model is asked to save it directly.
        $this->savePublic(['meta' => ['title' => 'headline']], 'hero', 'block-types')->assertSessionHasErrors('settings.public');
        $this->admin()->get('/admin/block-types/hero/display')->assertInertia(fn (Assert $page) => $page->missing('publicOptions.metaFields'));
    }

    public function test_saving_the_meta_needs_login_and_the_types_ability(): void
    {
        $this->newsWithMetaFields();

        $this->put('/admin/content-types/news/values', ['settings' => ['public' => ['meta' => ['title' => 'headline']]]])->assertRedirect('/login');
        $this->assertNull(ContentType::findByKey('news')->settings);
        $this->assertSame('/admin/page-types/news/values', route('admin.page-types.values.update', 'news', false));
    }

    public function test_a_stale_meta_choice_is_shown_as_not_chosen(): void
    {
        $this->newsWithMetaFields();
        $this->saveMeta(['image' => 'cover'])->assertSessionHasNoErrors();
        ContentField::where('content_type', 'news')->where('key', 'cover')->delete();

        $this->admin()->get('/admin/content-types/news/seo')->assertInertia(fn (Assert $page) => $page->where('metaOptions.current', []));
    }

    private function newsWithArchiveFields(): ContentType
    {
        $type = $this->newsWithFields();
        ContentField::create(['content_type' => 'news', 'key' => 'date', 'name' => 'Date', 'type' => 'datetime', 'weight' => 11]);
        ContentField::create(['content_type' => 'news', 'key' => 'note', 'name' => 'Note', 'type' => 'string', 'weight' => 12]);

        return $type;
    }

    public function test_the_archive_is_saved_and_offered_again(): void
    {
        $type = $this->newsWithArchiveFields();

        $this->savePublic(['archive' => ['field' => 'date', 'sort' => ['field' => 'date', 'direction' => 'desc']]])->assertSessionHasNoErrors();

        $this->assertSame(['field' => 'date', 'sort' => ['field' => 'date', 'direction' => 'desc']], $type->fresh()->settings['public']['archive']);
        $this->admin()->get('/admin/content-types/news/display')->assertInertia(fn (Assert $page) => $page
            ->where('publicOptions.current.archive.field', 'date')
            ->where('publicOptions.current.archive.sort', ['field' => 'date', 'direction' => 'desc'])
            ->where('publicOptions.archiveOptions.0.key', 'unpublished_at'));
    }

    public function test_a_cleared_archive_removes_the_key_and_keeps_the_other_settings(): void
    {
        $type = $this->newsWithArchiveFields();
        $type->update(['settings' => [
            'list' => ['search' => ['title']],
            'public' => ['per_page' => 5, 'archive' => ['field' => 'date']],
        ]]);

        $this->savePublic(['per_page' => 5])->assertSessionHasNoErrors();

        $settings = $type->fresh()->settings;
        $this->assertSame(['per_page' => 5], $settings['public']);
        $this->assertSame(['search' => ['title']], $settings['list']);
    }

    public function test_a_bad_archive_is_rejected_and_nothing_changes(): void
    {
        $this->newsWithArchiveFields();

        foreach ([
            ['field' => 'note'],
            ['field' => 'missing'],
            ['field' => 'published_at'],
            ['sort' => ['field' => 'date', 'direction' => 'asc']],
            ['field' => 'date', 'sort' => ['field' => 'date', 'direction' => 'up']],
            ['field' => 'date', 'sort' => ['field' => 'missing']],
            ['field' => 'date', 'unknown' => 1],
        ] as $archive) {
            $this->savePublic(['archive' => $archive])->assertSessionHasErrors();
        }

        $this->assertNull(ContentType::findByKey('news')->settings);
    }

    public function test_pages_and_blocks_reject_an_archive_and_offer_none(): void
    {
        foreach (['page' => 'page-types', 'block' => 'block-types'] as $kind => $base) {
            ContentType::create(['key' => $kind, 'name' => ucfirst($kind), 'kind' => $kind]);

            $this->savePublic(['archive' => ['field' => 'created_at']], $kind, $base)->assertSessionHasErrors('settings');
            $this->assertNull(ContentType::findByKey($kind)->settings);
            $this->admin()->get("/admin/{$base}/{$kind}/display")->assertInertia(fn (Assert $page) => $page
                ->where('publicOptions.archiveOptions', []));
        }
    }
}
