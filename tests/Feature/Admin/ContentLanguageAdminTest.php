<?php

namespace Webtack\Ecms\Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Role;
use Webtack\Ecms\Models\TermTranslation;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentLanguageAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): static
    {
        return $this->actingAs(User::factory()->create());
    }

    private function languages(): ContentLanguages
    {
        return app(ContentLanguages::class);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/settings/languages')->assertRedirect('/login');
        $this->put('/admin/settings/languages', ['multilingual' => '1'])->assertRedirect('/login');
    }

    public function test_a_role_without_the_permission_gets_403_and_nothing_changes(): void
    {
        $role = Role::factory()->withPermissions(['users' => 'allow'])->create();
        $this->actingAs(User::factory()->withRole($role)->create());
        AppLanguage::factory()->create(['code' => 'en', 'name' => 'English']);

        $this->get('/admin/settings/languages')->assertForbidden();
        $this->put('/admin/settings/languages', ['multilingual' => '1', 'base_lang' => 'uk'])->assertForbidden();
        $this->post('/admin/settings/languages', ['code' => 'pl', 'name' => 'Polski'])->assertForbidden();
        $this->put('/admin/settings/languages/en', ['name' => 'X', 'weight' => 1])->assertForbidden();
        $this->delete('/admin/settings/languages/en')->assertForbidden();

        $this->assertSame(0, DB::table('app_settings')->count());
        $this->assertSame(1, AppLanguage::count());
        $this->assertSame('English', AppLanguage::where('code', 'en')->firstOrFail()->name);
    }

    public function test_an_empty_installation_shows_the_configured_default_as_an_unsaved_base_language(): void
    {
        // The configured default is independent of the interface locale that `SetLocale` sets on the request.
        config(['content.base_lang_default' => 'en']);
        $this->admin()->get('/admin/settings/languages')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/Languages')
            ->where('multilingual', false)
            ->where('baseLang', 'en')
            ->where('savedBaseLang', null)
            ->where('languages', []));
    }

    public function test_the_unsaved_base_language_does_not_depend_on_the_interface_locale(): void
    {
        foreach ([['uk', 'en'], ['en', 'uk']] as [$interface, $default]) {
            config(['content.base_lang_default' => $default]);

            $this->actingAs(User::factory()->create(['locale' => $interface]))
                ->get('/admin/settings/languages')
                ->assertInertia(fn (Assert $page) => $page->where('baseLang', $default)->where('savedBaseLang', null));
        }
    }

    public function test_languages_are_ordered_by_weight(): void
    {
        AppLanguage::factory()->create(['code' => 'pl', 'name' => 'Polski', 'weight' => 2]);
        AppLanguage::factory()->active()->create(['code' => 'en', 'name' => 'English', 'weight' => 1]);

        $this->admin()->get('/admin/settings/languages')->assertInertia(fn (Assert $page) => $page
            ->where('languages.0.code', 'en')
            ->where('languages.0.is_active', true)
            ->where('languages.1.code', 'pl')
            ->where('languages.1.is_active', false));
    }

    public function test_enabling_without_a_base_code_is_rejected_next_to_the_switch(): void
    {
        $this->admin()->put('/admin/settings/languages', ['multilingual' => '1'])
            ->assertSessionHasErrors('multilingual');

        $this->assertFalse($this->languages()->isMultilingual());
    }

    public function test_the_base_code_and_the_switch_are_saved_together(): void
    {
        $this->admin()->put('/admin/settings/languages', ['multilingual' => '1', 'base_lang' => 'UK'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('uk', $this->languages()->savedBaseLang());
        $this->assertTrue($this->languages()->isMultilingual());
    }

    public function test_a_rejected_switch_keeps_the_previous_base_code(): void
    {
        $this->languages()->setBaseLang('uk');

        // The base code collides with an existing language, so nothing of the form is saved.
        AppLanguage::factory()->create(['code' => 'en']);
        $this->admin()->put('/admin/settings/languages', ['multilingual' => '1', 'base_lang' => 'en'])
            ->assertSessionHasErrors('base_lang');

        $this->assertSame('uk', app(ContentLanguages::class)->savedBaseLang());
        $this->assertFalse($this->languages()->isMultilingual());
    }

    public function test_invalid_base_codes_are_rejected(): void
    {
        foreach (['default', '!!'] as $code) {
            $this->admin()->put('/admin/settings/languages', ['multilingual' => '0', 'base_lang' => $code])
                ->assertSessionHasErrors('base_lang');
        }

        $this->assertNull($this->languages()->savedBaseLang());
    }

    public function test_disabling_keeps_translations(): void
    {
        $this->languages()->setBaseLang('uk');
        $this->languages()->setMultilingual(true);
        TermTranslation::factory()->create(['lang' => 'en']);

        $this->admin()->put('/admin/settings/languages', ['multilingual' => '0'])->assertSessionHasNoErrors();

        $this->assertFalse($this->languages()->isMultilingual());
        $this->assertSame(1, TermTranslation::count());
    }

    public function test_a_language_is_added_inactive_by_default_with_a_normalised_code(): void
    {
        $this->admin()->post('/admin/settings/languages', ['code' => 'PT-br', 'name' => 'Português'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $language = AppLanguage::where('code', 'pt-BR')->firstOrFail();
        $this->assertFalse($language->is_active);
    }

    public function test_a_language_can_be_added_active(): void
    {
        $this->admin()->post('/admin/settings/languages', ['code' => 'pl', 'name' => 'Polski', 'is_active' => '1']);

        $this->assertTrue(AppLanguage::where('code', 'pl')->firstOrFail()->is_active);
    }

    public function test_adding_rejects_duplicate_reserved_invalid_and_base_codes(): void
    {
        AppLanguage::factory()->create(['code' => 'en']);
        $this->languages()->setBaseLang('uk');

        foreach (['en', 'EN', 'default', '!!', 'uk'] as $code) {
            $this->admin()->post('/admin/settings/languages', ['code' => $code, 'name' => 'X'])
                ->assertSessionHasErrors('code');
        }

        $this->admin()->post('/admin/settings/languages', ['code' => 'de', 'name' => ''])->assertSessionHasErrors('name');
        $this->assertSame(1, AppLanguage::count());
    }

    public function test_a_language_is_edited_without_changing_its_code(): void
    {
        AppLanguage::factory()->create(['code' => 'en', 'name' => 'English', 'weight' => 5]);

        $this->admin()->put('/admin/settings/languages/en', ['code' => 'de', 'name' => 'Eng', 'is_active' => '1', 'weight' => '1'])
            ->assertSessionHasNoErrors();

        $language = AppLanguage::where('code', 'en')->firstOrFail();
        $this->assertSame('Eng', $language->name);
        $this->assertTrue($language->is_active);
        $this->assertSame(1, $language->weight);
        $this->assertFalse(AppLanguage::where('code', 'de')->exists());
    }

    public function test_editing_validates_the_weight_and_name(): void
    {
        AppLanguage::factory()->create(['code' => 'en']);

        $this->admin()->put('/admin/settings/languages/en', ['name' => 'English', 'is_active' => '1', 'weight' => '-1'])->assertSessionHasErrors('weight');
        $this->admin()->put('/admin/settings/languages/en', ['name' => '', 'is_active' => '1', 'weight' => '1'])->assertSessionHasErrors('name');
    }

    public function test_deactivating_keeps_translations(): void
    {
        AppLanguage::factory()->active()->create(['code' => 'en']);
        TermTranslation::factory()->create(['lang' => 'en']);

        $this->admin()->put('/admin/settings/languages/en', ['name' => 'English', 'is_active' => '0', 'weight' => '0'])->assertSessionHasNoErrors();

        $this->assertFalse(AppLanguage::where('code', 'en')->firstOrFail()->is_active);
        $this->assertSame(1, TermTranslation::count());
    }

    public function test_an_unknown_language_answers_404(): void
    {
        $this->admin()->put('/admin/settings/languages/xx', ['name' => 'X', 'is_active' => '1', 'weight' => 1])->assertNotFound();
        $this->admin()->delete('/admin/settings/languages/xx')->assertNotFound();
    }

    public function test_a_language_without_translations_is_deleted(): void
    {
        AppLanguage::factory()->create(['code' => 'en']);

        $this->admin()->delete('/admin/settings/languages/en')->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame(0, AppLanguage::count());
    }

    public function test_a_language_with_a_term_translation_is_not_deleted(): void
    {
        AppLanguage::factory()->create(['code' => 'en']);
        TermTranslation::factory()->create(['lang' => 'en']);

        $this->admin()->delete('/admin/settings/languages/en')->assertSessionHasErrors('language');

        $this->assertSame(1, AppLanguage::count());
    }

    public function test_adding_a_language_named_like_a_page_a_type_or_a_service_path_is_rejected(): void
    {
        $page = ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);
        ContentNode::factory()->create(['content_type' => $page->key, 'slug' => 'faq']);
        ContentType::create(['key' => 'catalog', 'name' => 'Catalog', 'slug' => 'shop/catalog']);

        $this->admin()->post('/admin/settings/languages', ['code' => 'faq', 'name' => 'Faq'])->assertSessionHasErrors('code');
        $this->post('/admin/settings/languages', ['code' => 'shop', 'name' => 'Shop'])->assertSessionHasErrors('code');
        $this->post('/admin/settings/languages', ['code' => 'search', 'name' => 'Search'])->assertSessionHasErrors('code');

        $this->assertSame(0, AppLanguage::count());
    }

    public function test_a_base_code_named_like_a_page_is_rejected(): void
    {
        $page = ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);
        ContentNode::factory()->create(['content_type' => $page->key, 'slug' => 'uk']);

        $this->admin()->put('/admin/settings/languages', ['base_lang' => 'uk'])->assertSessionHasErrors('base_lang');
        $this->assertNull($this->languages()->savedBaseLang());
    }
}
