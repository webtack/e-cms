<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Content\LanguageUsages\ContentValuesUsage;
use Webtack\Ecms\Content\LanguageUsages\FieldLabelsUsage;
use Webtack\Ecms\Content\LanguageUsages\FileDescriptionsUsage;
use Webtack\Ecms\Content\LanguageUsages\MenuItemTranslationsUsage;
use Webtack\Ecms\Content\LanguageUsages\SiteSettingsUsage;
use Webtack\Ecms\Content\LanguageUsages\TermTranslationsUsage;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\MenuItemTranslation;
use Webtack\Ecms\Models\TermTranslation;
use Webtack\Ecms\Support\AppSettings;
use Webtack\Ecms\Tests\TestCase;

class ContentLanguagesTest extends TestCase
{
    use RefreshDatabase;

    private ContentLanguages $languages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->languages = app(ContentLanguages::class);
    }

    public function test_multilingual_is_off_by_default_and_stored_in_app_settings(): void
    {
        $this->assertFalse($this->languages->isMultilingual());

        $this->languages->setBaseLang('xx');
        $this->languages->setMultilingual(true);

        $this->assertTrue($this->languages->isMultilingual());
        $this->assertTrue(app(AppSettings::class)->get('content.multilingual'));
    }

    public function test_default_is_always_allowed(): void
    {
        $this->assertSame('default', $this->languages->assertReadable('default'));
        $this->assertSame('default', $this->languages->assertWritable('default'));
    }

    public function test_other_languages_are_rejected_while_multilingual_is_off(): void
    {
        AppLanguage::factory()->active()->create(['code' => 'en']);

        $this->assertRejected(fn () => $this->languages->assertReadable('en'));
        $this->assertRejected(fn () => $this->languages->assertWritable('en'));
        $this->assertSame([], $this->languages->active());
    }

    public function test_an_active_language_is_writable_when_multilingual_is_on(): void
    {
        AppLanguage::factory()->active()->create(['code' => 'en']);
        $this->languages->setBaseLang('xx');
        $this->languages->setMultilingual(true);

        $this->assertSame('en', $this->languages->assertWritable('en'));
    }

    public function test_an_inactive_language_is_readable_but_not_writable(): void
    {
        AppLanguage::factory()->create(['code' => 'de']);
        $this->languages->setBaseLang('xx');
        $this->languages->setMultilingual(true);

        $this->assertSame('de', $this->languages->assertReadable('de'));
        $this->assertRejected(fn () => $this->languages->assertWritable('de'));
    }

    public function test_an_unknown_language_is_rejected(): void
    {
        $this->languages->setBaseLang('xx');
        $this->languages->setMultilingual(true);

        $this->assertRejected(fn () => $this->languages->assertReadable('fr'));
        $this->assertRejected(fn () => $this->languages->assertWritable('fr'));
    }

    public function test_input_is_normalised_before_checking(): void
    {
        AppLanguage::factory()->active()->create(['code' => 'pt-BR']);
        $this->languages->setBaseLang('xx');
        $this->languages->setMultilingual(true);

        $this->assertSame('pt-BR', $this->languages->assertWritable('pt-br'));
        $this->assertSame('pt-BR', $this->languages->assertReadable('PT-BR'));
    }

    public function test_active_languages_follow_weight_order(): void
    {
        AppLanguage::factory()->active()->create(['code' => 'en', 'weight' => 2]);
        AppLanguage::factory()->active()->create(['code' => 'uk', 'weight' => 1]);
        AppLanguage::factory()->create(['code' => 'de', 'weight' => 0]);
        $this->languages->setBaseLang('xx');
        $this->languages->setMultilingual(true);

        $this->assertSame(['uk', 'en'], $this->languages->active());
    }

    public function test_toggling_the_switch_does_not_change_languages(): void
    {
        AppLanguage::factory()->active()->create(['code' => 'en']);

        $this->languages->setBaseLang('xx');
        $this->languages->setMultilingual(true);
        $this->languages->setMultilingual(false);
        $this->languages->setMultilingual(true);

        $this->assertSame(['en'], $this->languages->active());
        $this->assertSame(1, AppLanguage::count());
    }

    public function test_base_language_reads_as_the_configured_default_until_saved(): void
    {
        config(['content.base_lang_default' => 'uk', 'app.locale' => 'en']);

        $this->assertNull($this->languages->savedBaseLang());
        $this->assertSame('uk', $this->languages->baseLang());

        config(['content.base_lang_default' => 'en', 'app.locale' => 'uk']);

        $this->assertSame('en', $this->languages->baseLang());
    }

    public function test_base_language_is_saved_normalised(): void
    {
        $this->assertSame('uk', $this->languages->setBaseLang('UK'));

        $this->assertSame('uk', $this->languages->baseLang());
        $this->assertSame('uk', app(AppSettings::class)->get('content.base_lang'));
    }

    public function test_base_language_rejects_invalid_reserved_and_additional_codes(): void
    {
        AppLanguage::factory()->create(['code' => 'en']);

        foreach (['default', '!!', '', 'en'] as $code) {
            try {
                $this->languages->setBaseLang($code);
                $this->fail("Code [{$code}] must be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('base_lang', $e->errors());
            }
        }

        $this->assertNull($this->languages->savedBaseLang());
    }

    public function test_multilingual_cannot_be_enabled_without_a_saved_base_language(): void
    {
        try {
            $this->languages->setMultilingual(true);
            $this->fail('Enabling must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('multilingual', $e->errors());
        }

        $this->assertFalse($this->languages->isMultilingual());

        $this->languages->setMultilingual(false);
    }

    public function test_a_language_with_the_base_code_cannot_be_added_or_renamed_to_it(): void
    {
        $this->languages->setBaseLang('uk');
        $other = AppLanguage::factory()->create(['code' => 'en']);

        $this->assertThrows(fn () => AppLanguage::factory()->create(['code' => 'UK']), ValidationException::class);
        $this->assertThrows(fn () => $other->update(['code' => 'uk']), ValidationException::class);
    }

    public function test_term_and_menu_item_translations_block_the_usage_check(): void
    {
        $this->assertFalse($this->languages->isInUse('en'));

        TermTranslation::factory()->create(['lang' => 'en']);
        $this->assertTrue($this->languages->isInUse('en'));
        $this->assertFalse($this->languages->isInUse('de'));

        MenuItemTranslation::factory()->create(['lang' => 'de']);
        $this->assertTrue($this->languages->isInUse('de'));
    }

    public function test_the_registered_usage_checkers(): void
    {
        $this->assertEqualsCanonicalizing(
            [ContentValuesUsage::class, TermTranslationsUsage::class, MenuItemTranslationsUsage::class, FieldLabelsUsage::class, SiteSettingsUsage::class, FileDescriptionsUsage::class],
            collect(app()->tagged(ContentLanguages::USAGE_TAG))->map(fn ($usage) => $usage::class)->all(),
        );
    }

    private function assertRejected(callable $call): void
    {
        try {
            $call();
            $this->fail('A ValidationException was expected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lang', $e->errors());
        }
    }
}
