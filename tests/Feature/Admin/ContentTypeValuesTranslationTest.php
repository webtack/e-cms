<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * The translation mode of the "Display" and "SEO" tabs of a type.
 */
class ContentTypeValuesTranslationTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private ContentType $news;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableLanguages('en', 'pl');
        $this->news = ContentType::create(['key' => 'news', 'name' => 'News']);
        app(ContentValueWriter::class)->write(ValueOwner::forType($this->news), [
            'section_title' => 'Новини', 'section_description' => '<p>Опис</p>', 'meta_title' => 'SEO base',
        ]);
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function stored(string $lang, ?ContentType $type = null): array
    {
        return app(ContentValueReader::class)->loadForType($type ?? $this->news, null, $lang);
    }

    public function test_the_display_tab_shows_section_values_prefilled_from_the_base(): void
    {
        $this->admin()->get('/admin/content-types/news/display?lang=en')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('translation.lang', 'en')
            ->where('translation.exists', false)
            ->where('translation.values', fn ($values) => collect($values)->all() === ['section_title' => 'Новини', 'section_description' => '<p>Опис</p>', 'section_cover' => null])
            ->where('typeValues.section_title', 'Новини')
            ->has('publicOptions'));
    }

    public function test_the_seo_tab_shows_meta_values_only(): void
    {
        $this->admin()->get('/admin/content-types/news/seo?lang=en')->assertInertia(fn (Assert $page) => $page
            ->where('translation.values', fn ($values) => array_keys(collect($values)->all()) === ['meta_title', 'meta_description', 'meta_image'])
            ->where('translation.values.meta_title', 'SEO base'));
    }

    public function test_saving_a_section_title_changes_only_that_translation(): void
    {
        $this->admin()->put('/admin/content-types/news/values/translations/en', ['panel' => 'section', 'values' => ['section_title' => 'News', 'section_description' => '<p>Опис</p>']])
            ->assertRedirect('/admin/content-types/news/display?lang=en')
            ->assertSessionHas('success');

        $this->assertSame('News', $this->stored('en')['section_title']);
        $this->assertNull($this->stored('en')['section_description']);
        $this->assertSame('Новини', $this->stored('default')['section_title']);
        $this->assertNull($this->news->fresh()->settings);
    }

    public function test_the_other_tab_is_refused_and_nothing_is_stored(): void
    {
        $this->admin()->put('/admin/content-types/news/values/translations/en', ['panel' => 'section', 'values' => ['section_title' => 'News', 'meta_title' => 'SEO']])
            ->assertSessionHasErrors('values.meta_title');

        $this->assertNull($this->stored('en')['section_title']);
    }

    public function test_clearing_one_tab_keeps_the_other_and_the_marker_covers_both(): void
    {
        $this->admin()->put('/admin/content-types/news/values/translations/en', ['panel' => 'section', 'values' => ['section_title' => 'News']]);
        $this->admin()->put('/admin/content-types/news/values/translations/en', ['panel' => 'seo', 'values' => ['meta_title' => 'SEO en']]);

        $this->admin()->get('/admin/content-types/news/display')->assertInertia(fn (Assert $page) => $page
            ->where('translation.languages', fn ($languages) => collect($languages)->pluck('translated', 'code')->all() === ['en' => true, 'pl' => false]));

        $this->admin()->delete('/admin/content-types/news/values/translations/en', ['panel' => 'section'])
            ->assertRedirect('/admin/content-types/news/display?lang=en');

        $stored = $this->stored('en');
        $this->assertNull($stored['section_title']);
        $this->assertSame('SEO en', $stored['meta_title']);
    }

    public function test_the_marker_shows_on_the_seo_tab_for_a_translation_made_on_display(): void
    {
        $this->admin()->put('/admin/content-types/news/values/translations/en', ['panel' => 'section', 'values' => ['section_title' => 'News']]);

        $this->admin()->get('/admin/content-types/news/seo')->assertInertia(fn (Assert $page) => $page
            ->where('translation.languages', fn ($languages) => collect($languages)->pluck('translated', 'code')->all() === ['en' => true, 'pl' => false])
            ->where('translation.exists', false));
    }

    public function test_page_types_translate_on_seo_only(): void
    {
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);

        $this->admin()->put('/admin/page-types/home/values/translations/en', ['panel' => 'seo', 'values' => ['meta_title' => 'Home']])
            ->assertRedirect('/admin/page-types/home/seo?lang=en');
        $this->assertSame('Home', $this->stored('en', ContentType::findByKey('home'))['meta_title']);

        $this->admin()->get('/admin/page-types/home/display?lang=en')->assertNotFound();
        $this->admin()->put('/admin/page-types/home/values/translations/en', ['panel' => 'section', 'values' => []])->assertNotFound();
        $this->admin()->get('/admin/page-types/home/seo?lang=en')->assertOk();
    }

    public function test_block_types_have_no_switcher_and_no_translation(): void
    {
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);

        $this->admin()->get('/admin/block-types/hero/display')->assertInertia(fn (Assert $page) => $page->where('translation', null));
        $this->admin()->get('/admin/block-types/hero/display?lang=en')->assertNotFound();
        $this->admin()->put('/admin/block-types/hero/values/translations/en', ['panel' => 'seo', 'values' => []])->assertNotFound();
        $this->admin()->delete('/admin/block-types/hero/values/translations/en', ['panel' => 'seo'])->assertNotFound();
    }

    public function test_an_unknown_language_is_404_and_a_single_language_site_has_no_switcher(): void
    {
        $this->admin()->get('/admin/content-types/news/display?lang=de')->assertNotFound();
        $this->admin()->put('/admin/content-types/news/values/translations/de', ['panel' => 'section', 'values' => []])->assertNotFound();

        app(ContentLanguages::class)->setMultilingual(false);

        $this->admin()->get('/admin/content-types/news/display')->assertInertia(fn (Assert $page) => $page->where('translation', null));
        $this->admin()->get('/admin/content-types/news/display?lang=en')->assertNotFound();
    }

    public function test_it_needs_the_type_configuration_right(): void
    {
        $user = User::factory()->withRole(Role::factory()->withPermissions(['node:news' => 'any'])->create())->create();
        $this->actingAs($user);

        $this->get('/admin/content-types/news/display?lang=en')->assertForbidden();
        $this->put('/admin/content-types/news/values/translations/en', ['panel' => 'section', 'values' => ['section_title' => 'x']])->assertForbidden();
        $this->delete('/admin/content-types/news/values/translations/en', ['panel' => 'section'])->assertForbidden();
    }
}
