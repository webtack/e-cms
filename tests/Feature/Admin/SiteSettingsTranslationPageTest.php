<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Settings\SiteSettings;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

/**
 * The translation mode of the site settings page (`/admin/settings?lang=`).
 */
class SiteSettingsTranslationPageTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private const URL = '/admin/settings';

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableLanguages('en', 'pl');
        app(SiteSettings::class)->save([
            'name' => 'Мій сайт',
            'phones' => [['value' => '+380 44 000', 'label' => 'Продажі']],
            'socials' => [['network' => 'facebook', 'url' => 'https://f.example']],
            'schedule' => [['Пн', '9', '18', '']],
            'indexable' => true,
        ]);
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function snapshot(): array
    {
        return DB::table('app_settings')->orderBy('key')->get(['key', 'value'])->map(fn ($row) => (array) $row)->all();
    }

    public function test_routes_have_names(): void
    {
        $this->assertSame('/admin/settings/translations/en', route('admin.settings.translations.save', ['en'], false));
        $this->assertSame('/admin/settings/translations/en', route('admin.settings.translations.clear', ['en'], false));
    }

    public function test_the_base_mode_has_the_switcher_and_no_values(): void
    {
        $this->admin()->get(self::URL)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('translation.lang', null)
            ->where('translation.values', null)
            ->where('translation.languages.0.code', 'en')
            ->where('translation.languages.0.translated', false)
            ->where('values.name', 'Мій сайт'));
    }

    public function test_the_translation_mode_prefills_from_the_base_and_keeps_the_base_values(): void
    {
        $this->admin()->get(self::URL.'?lang=en')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('translation.lang', 'en')
            ->where('translation.exists', false)
            ->where('translation.values.name', 'Мій сайт')
            ->where('translation.values.phones', [['value' => '+380 44 000', 'label' => 'Продажі']])
            ->where('translation.values.socials', [['network' => 'facebook', 'url' => 'https://f.example', 'label' => '']])
            ->where('translation.values.schedule', [['Пн', '9', '18', '']])
            ->where('values.indexable', true)
            ->where('values.name', 'Мій сайт'));
    }

    public function test_saving_a_translation_stays_on_the_language_form(): void
    {
        $this->admin()->put(self::URL.'/translations/en', [
            'name' => 'My site',
            'phones' => [['value' => '+380 44 000', 'label' => 'Sales']],
            'schedule' => [['Mon', '9', '18', '']],
        ])->assertRedirect(self::URL.'?lang=en')->assertSessionHas('success');

        $site = app(SiteSettings::class);
        $this->assertSame('My site', $site->name('en'));
        $this->assertSame('Мій сайт', $site->name('default'));
        $this->assertSame('Sales', $site->phones('en')[0]['label']);
        $this->assertSame('Продажі', $site->phones('default')[0]['label']);
        $this->assertSame([['Mon', '9', '18', '']], $site->schedule('en'));
        $this->assertSame([['Пн', '9', '18', '']], $site->schedule('default'));

        $this->admin()->get(self::URL.'?lang=en')->assertInertia(fn (Assert $page) => $page
            ->where('translation.exists', true)
            ->where('translation.languages.0.translated', true)
            ->where('translation.values.name', 'My site'));
    }

    public function test_a_translation_cannot_change_base_data(): void
    {
        $this->admin()->put(self::URL.'/translations/en', [
            'indexable' => false,
            'logo_large' => 'x.png',
            'phones' => [['value' => '+1 000', 'label' => 'Ghost']],
        ])->assertRedirect();

        $site = app(SiteSettings::class);
        $this->assertTrue($site->indexable());
        $this->assertNull($site->logoLarge());
        $this->assertCount(1, $site->phones());
        $this->assertFalse($site->hasTranslation('en'));
    }

    public function test_validation_errors_change_nothing(): void
    {
        $before = $this->snapshot();

        $this->admin()->put(self::URL.'/translations/en', ['name' => str_repeat('x', 256)])->assertSessionHasErrors('name');
        $this->admin()->put(self::URL.'/translations/en', ['schedule' => [['a', 'b']]])->assertSessionHasErrors('schedule.0');

        $this->assertSame($before, $this->snapshot());
    }

    public function test_clearing_removes_only_that_language(): void
    {
        $site = app(SiteSettings::class);
        $site->saveTranslation('en', ['name' => 'My site']);
        $site->saveTranslation('pl', ['name' => 'Moja strona']);

        $this->admin()->delete(self::URL.'/translations/en')->assertRedirect(self::URL.'?lang=en')->assertSessionHas('success');

        $this->assertFalse($site->hasTranslation('en'));
        $this->assertSame('Moja strona', $site->name('pl'));
    }

    public function test_unknown_base_and_inactive_languages_answer_404(): void
    {
        $this->admin()->get(self::URL.'?lang=de')->assertNotFound();
        $this->admin()->put(self::URL.'/translations/de', ['name' => 'X'])->assertNotFound();
        $this->admin()->delete(self::URL.'/translations/de')->assertNotFound();
        $this->admin()->put(self::URL.'/translations/xx', ['name' => 'X'])->assertNotFound();
        $this->admin()->get(self::URL.'?lang=xx')->assertOk()->assertInertia(fn (Assert $page) => $page->where('translation.lang', null));
    }

    public function test_without_the_right_every_translation_route_is_403(): void
    {
        $role = Role::factory()->withPermissions(['users' => 'allow'])->create();
        $this->actingAs(User::factory()->withRole($role)->create());
        $before = $this->snapshot();

        $this->get(self::URL.'?lang=en')->assertForbidden();
        $this->put(self::URL.'/translations/en', ['name' => 'X'])->assertForbidden();
        $this->delete(self::URL.'/translations/en')->assertForbidden();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_the_page_without_additional_languages_has_no_switcher(): void
    {
        DB::table('app_languages')->update(['is_active' => false]);

        $this->admin()->get(self::URL)->assertInertia(fn (Assert $page) => $page->where('translation', null));
        $this->admin()->get(self::URL.'?lang=en')->assertNotFound();
    }
}
