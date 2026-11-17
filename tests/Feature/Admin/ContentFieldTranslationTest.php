<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * The translation mode of the field edit page: the label of a field in one language.
 */
class ContentFieldTranslationTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private const URL = '/admin/content-types/article/fields/note';

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableLanguages('en', 'pl');
        $this->articleType();
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function field(string $key = 'note'): ContentField
    {
        return ContentField::query()->where('content_type', 'article')->where('key', $key)->firstOrFail();
    }

    public function test_routes_have_names(): void
    {
        $this->assertSame('/admin/page-types/home/fields/hours/translations/en', route('admin.page-types.fields.translations.save', ['home', 'hours', 'en'], false));
        $this->assertSame('/admin/block-types/hero/fields/hours/translations/en', route('admin.block-types.fields.translations.clear', ['hero', 'hours', 'en'], false));
    }

    public function test_the_translation_mode_prefills_the_label_from_the_name(): void
    {
        $this->admin()->get(self::URL.'/edit?lang=en')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('translation.lang', 'en')
            ->where('translation.values', ['name' => 'Note'])
            ->where('translation.exists', false)
            ->where('field.name', 'Note'));

        $this->field()->setLabel('en', 'Remark');

        $this->admin()->get(self::URL.'/edit?lang=en')->assertInertia(fn (Assert $page) => $page
            ->where('translation.values', ['name' => 'Remark'])
            ->where('translation.exists', true)
            ->where('translation.languages', fn ($languages) => collect($languages)->pluck('translated', 'code')->all() === ['en' => true, 'pl' => false]));
    }

    public function test_translating_a_label_keeps_the_name(): void
    {
        $this->admin()->put(self::URL.'/translations/en', ['name' => 'Opening hours'])
            ->assertRedirect(self::URL.'/edit?lang=en')
            ->assertSessionHas('success');

        $this->assertSame('Opening hours', $this->field()->label('en'));
        $this->assertSame('Note', $this->field()->name);
    }

    public function test_a_label_equal_to_the_name_is_not_stored(): void
    {
        $this->admin()->put(self::URL.'/translations/en', ['name' => 'Note'])->assertSessionHasNoErrors();

        $this->assertNull($this->field()->label('en'));
        $this->assertNull($this->field()->settings);
    }

    public function test_an_emptied_label_is_removed(): void
    {
        $this->field()->setLabel('en', 'Remark');

        $this->admin()->put(self::URL.'/translations/en', ['name' => ''])->assertSessionHasNoErrors();

        $this->assertNull($this->field()->label('en'));
    }

    public function test_a_too_long_label_is_a_form_error(): void
    {
        $this->admin()->put(self::URL.'/translations/en', ['name' => str_repeat('a', 256)])->assertSessionHasErrors('name');
    }

    public function test_only_the_label_changes(): void
    {
        ContentField::query()->where('key', 'note')->update(['description' => 'Hint', 'weight' => 7]);

        $this->admin()->put(self::URL.'/translations/en', ['name' => 'Remark', 'description' => 'Changed', 'weight' => 99, 'is_required' => true, 'settings' => ['x' => 1]]);

        $field = $this->field();
        $this->assertSame(['Hint', 7, false, ['labels' => ['en' => 'Remark']]], [$field->description, $field->weight, $field->is_required, $field->settings]);
    }

    public function test_clearing_removes_one_language(): void
    {
        $this->field()->setLabel('en', 'Remark');
        $this->field()->setLabel('pl', 'Uwaga');

        $this->admin()->delete(self::URL.'/translations/en')->assertRedirect(self::URL.'/edit?lang=en');

        $this->assertNull($this->field()->label('en'));
        $this->assertSame('Uwaga', $this->field()->label('pl'));
    }

    public function test_a_system_field_and_any_value_type_can_be_translated(): void
    {
        $this->admin()->put('/admin/content-types/article/fields/title/translations/en', ['name' => 'Heading'])->assertSessionHasNoErrors();
        $this->admin()->put('/admin/content-types/article/fields/visits/translations/en', ['name' => 'Views'])->assertSessionHasNoErrors();

        $this->assertSame('Heading', $this->field('title')->label('en'));
        $this->assertSame('Views', $this->field('visits')->label('en'));
    }

    public function test_the_base_form_keeps_translated_labels(): void
    {
        $this->field()->setLabel('en', 'Remark');

        $this->admin()->put(self::URL, ['name' => 'Renamed', 'type' => 'string', 'weight' => 3])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $this->field()->name);
        $this->assertSame('Remark', $this->field()->label('en'));
    }

    public function test_an_unknown_language_and_a_single_language_site_answer_404(): void
    {
        $this->admin()->get(self::URL.'/edit?lang=de')->assertNotFound();
        $this->admin()->put(self::URL.'/translations/de', ['name' => 'x'])->assertNotFound();
        $this->admin()->delete(self::URL.'/translations/de')->assertNotFound();

        app(ContentLanguages::class)->setMultilingual(false);

        $this->admin()->get(self::URL.'/edit')->assertInertia(fn (Assert $page) => $page->where('translation', null));
        $this->admin()->get(self::URL.'/edit?lang=en')->assertNotFound();
    }

    public function test_pages_and_blocks_have_the_mode_too(): void
    {
        foreach (['page' => 'page-types', 'block' => 'block-types'] as $kind => $section) {
            ContentType::create(['key' => "t{$kind}", 'name' => 'T', 'kind' => $kind]);
            ContentField::create(['content_type' => "t{$kind}", 'key' => 'lead', 'name' => 'Lead', 'type' => 'string']);

            $this->admin()->get("/admin/{$section}/t{$kind}/fields/lead/edit?lang=en")->assertOk();
            $this->admin()->put("/admin/{$section}/t{$kind}/fields/lead/translations/en", ['name' => 'Intro'])->assertRedirect("/admin/{$section}/t{$kind}/fields/lead/edit?lang=en");

            $this->assertSame('Intro', ContentField::query()->where('content_type', "t{$kind}")->firstWhere('key', 'lead')->label('en'));
        }
    }

    public function test_it_needs_the_type_configuration_right(): void
    {
        $this->actingAs(User::factory()->withRole(Role::factory()->withPermissions(['node:article' => 'any'])->create())->create());

        $this->get(self::URL.'/edit?lang=en')->assertForbidden();
        $this->put(self::URL.'/translations/en', ['name' => 'x'])->assertForbidden();
        $this->delete(self::URL.'/translations/en')->assertForbidden();
    }

    public function test_a_panel_field_is_not_translatable_here(): void
    {
        $this->admin()->put('/admin/content-types/article/fields/meta_title/translations/en', ['name' => 'x'])->assertNotFound();
    }
}
