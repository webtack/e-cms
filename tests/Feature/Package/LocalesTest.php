<?php

namespace Webtack\Ecms\Tests\Feature\Package;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Tests\Concerns\OverridesHostFiles;
use Webtack\Ecms\Tests\TestCase;

/**
 * A host does not have `app.available_locales` (the demo application did): the interface languages come from the
 * package's `ecms.locales`, and the public site finds its interface locale in the package's `lang/` files.
 */
class LocalesTest extends TestCase
{
    use OverridesHostFiles;
    use RefreshDatabase;

    /** A multilingual site with the given base language and one additional active language. */
    private function multilingual(string $base, string $additional): void
    {
        AppLanguage::factory()->active()->create(['code' => $additional]);
        app(ContentLanguages::class)->setBaseLang($base);
        app(ContentLanguages::class)->setMultilingual(true);
    }

    public function test_a_host_without_its_own_locale_list_serves_a_web_request(): void
    {
        $this->assertNull(config('app.available_locales'));

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale.available', [['code' => 'en', 'name' => 'English'], ['code' => 'uk', 'name' => 'Українська']]));
    }

    public function test_the_locale_switch_accepts_a_package_locale(): void
    {
        $this->from('/login')->post('/locale', ['locale' => 'uk'])->assertRedirect('/login')->assertCookie('locale', 'uk');
    }

    public function test_the_locale_switch_refuses_a_locale_outside_the_list(): void
    {
        $this->post('/locale', ['locale' => 'xx'])->assertSessionHasErrors('locale');
    }

    public function test_the_host_extends_the_list_with_its_own_locale(): void
    {
        config(['ecms.locales' => ['en' => 'English', 'uk' => 'Українська', 'pl' => 'Polski']]);
        $this->hostFile('lang/pl.json', '{}');

        $this->post('/locale', ['locale' => 'pl'])->assertSessionHasNoErrors()->assertCookie('locale', 'pl');
    }

    public function test_the_public_site_takes_the_locale_en_from_the_package_files_when_the_host_has_none(): void
    {
        $this->hideHostFile('lang/en.json');
        $this->assertFileDoesNotExist(lang_path('en.json'));

        $this->multilingual('en', 'pl');

        $this->get('/')->assertOk()->assertSee('lang="en"', false);
        $this->assertSame('en', app()->getLocale());
    }

    public function test_the_public_site_keeps_the_application_locale_when_no_interface_file_exists_for_the_language(): void
    {
        config(['app.locale' => 'uk']);
        $this->multilingual('zz', 'pl');

        // The reading language is `zz` (the `lang` attribute), the interface locale stays the application's.
        $this->get('/')->assertOk()->assertSee('lang="zz"', false);
        $this->assertSame('uk', app()->getLocale());
    }
}
