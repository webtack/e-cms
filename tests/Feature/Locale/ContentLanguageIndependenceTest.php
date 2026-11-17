<?php

namespace Webtack\Ecms\Tests\Feature\Locale;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Tests\Fixtures\User;
use Webtack\Ecms\Tests\TestCase;

class ContentLanguageIndependenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_language_settings_do_not_touch_the_admin_interface_locale(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['locale' => 'uk'])->save();

        AppLanguage::factory()->active()->create(['code' => 'de', 'name' => 'Deutsch']);
        app(ContentLanguages::class)->setBaseLang('xx');
        app(ContentLanguages::class)->setMultilingual(true);

        $this->assertSame('uk', $user->fresh()->locale);
        $this->get('/login', ['Accept-Language' => 'en'])
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale.current', 'en')
                ->where('locale.available', [
                    ['code' => 'en', 'name' => 'English'],
                    ['code' => 'uk', 'name' => 'Українська'],
                ]));
    }

    public function test_the_admin_locale_does_not_change_content_languages(): void
    {
        AppLanguage::factory()->active()->create(['code' => 'en']);
        $languages = app(ContentLanguages::class);
        $languages->setBaseLang('xx');
        $languages->setMultilingual(true);

        $this->get('/login', ['Accept-Language' => 'uk'])->assertOk();
        $this->from('/login')->post('/locale', ['locale' => 'uk']);

        $this->assertSame(['en'], $languages->active());
        $this->assertTrue($languages->isMultilingual());
    }
}
