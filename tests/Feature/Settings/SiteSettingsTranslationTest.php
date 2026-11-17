<?php

namespace Webtack\Ecms\Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Settings\SiteSettings;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

/**
 * Saving one language of the translatable site settings (`SiteSettings::saveTranslation()`).
 */
class SiteSettingsTranslationTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableLanguages('en', 'pl');
        $this->site()->save([
            'name' => 'Мій сайт',
            'description' => 'Опис',
            'phones' => [['value' => '+380 44 000', 'label' => 'Відділ продажів']],
            'emails' => [['value' => 'a@b.c']],
            'socials' => [['network' => 'facebook', 'url' => 'https://f.example', 'label' => 'ФБ']],
            'schedule' => [['Пн', '9', '18', '']],
            'indexable' => true,
        ]);
    }

    private function site(): SiteSettings
    {
        return app(SiteSettings::class);
    }

    private function stored(string $key): mixed
    {
        $row = DB::table('app_settings')->where('key', "site.{$key}")->value('value');

        return $row === null ? null : json_decode($row, true);
    }

    public function test_translated_texts_are_stored_next_to_default(): void
    {
        $this->site()->saveTranslation('en', ['name' => ' My site ', 'description' => 'Description']);

        $this->assertSame(['default' => 'Мій сайт', 'en' => 'My site'], $this->stored('name'));
        $this->assertSame('My site', $this->site()->name('en'));
        $this->assertSame('Мій сайт', $this->site()->name('pl'));
    }

    public function test_a_value_equal_to_the_base_is_not_stored_and_removes_the_old_one(): void
    {
        $this->site()->saveTranslation('en', ['name' => 'My site']);
        $this->site()->saveTranslation('en', ['name' => 'Мій сайт']);

        $this->assertSame(['default' => 'Мій сайт'], $this->stored('name'));
    }

    public function test_a_blank_value_removes_the_translation(): void
    {
        $this->site()->saveTranslation('en', ['name' => 'My site']);
        $this->site()->saveTranslation('en', ['name' => '  ']);

        $this->assertSame(['default' => 'Мій сайт'], $this->stored('name'));
    }

    public function test_labels_are_matched_by_identity_and_missing_items_are_ignored(): void
    {
        $this->site()->saveTranslation('en', [
            'phones' => [['value' => '+380 44 000', 'label' => 'Sales'], ['value' => '+1 000 000', 'label' => 'Ghost']],
            'socials' => [['network' => 'facebook', 'url' => 'https://f.example', 'label' => 'FB'], ['network' => 'facebook', 'url' => 'https://other.example', 'label' => 'Other']],
        ]);

        $this->assertSame([['value' => '+380 44 000', 'label' => ['default' => 'Відділ продажів', 'en' => 'Sales']]], $this->stored('phones'));
        $this->assertSame([['network' => 'facebook', 'url' => 'https://f.example', 'label' => ['default' => 'ФБ', 'en' => 'FB']]], $this->stored('socials'));
        $this->assertSame('Sales', $this->site()->phones('en')[0]['label']);
        $this->assertSame('+380 44 000', $this->site()->phones('en')[0]['value']);
    }

    public function test_a_label_equal_to_the_base_label_is_not_stored(): void
    {
        $this->site()->saveTranslation('en', ['phones' => [['value' => '+380 44 000', 'label' => 'Sales']]]);
        $this->site()->saveTranslation('en', ['phones' => [['value' => '+380 44 000', 'label' => 'Відділ продажів']]]);

        $this->assertSame([['value' => '+380 44 000', 'label' => ['default' => 'Відділ продажів']]], $this->stored('phones'));
    }

    public function test_the_schedule_is_stored_as_a_whole_table_and_not_when_equal_to_the_base(): void
    {
        $this->site()->saveTranslation('en', ['schedule' => [['Mon', '9', '18', '']]]);
        $this->assertSame(['default' => [['Пн', '9', '18', '']], 'en' => [['Mon', '9', '18', '']]], $this->stored('schedule'));

        $this->site()->saveTranslation('en', ['schedule' => [[' Пн ', '9', '18', '']]]);
        $this->assertSame(['default' => [['Пн', '9', '18', '']]], $this->stored('schedule'));
    }

    public function test_other_languages_and_base_data_are_unchanged(): void
    {
        $this->site()->saveTranslation('pl', ['name' => 'Moja strona', 'phones' => [['value' => '+380 44 000', 'label' => 'Sprzedaż']]]);
        $this->site()->saveTranslation('en', ['name' => 'My site', 'phones' => [['value' => '+380 44 000', 'label' => 'Sales']]]);

        $this->assertSame('Moja strona', $this->site()->name('pl'));
        $this->assertSame('Sprzedaż', $this->site()->phones('pl')[0]['label']);
        $this->assertSame('Мій сайт', $this->site()->name('default'));
        $this->assertTrue($this->site()->indexable());
        $this->assertSame([['value' => 'a@b.c']], $this->stored('emails'));
    }

    public function test_only_present_keys_are_written(): void
    {
        $this->site()->saveTranslation('en', ['name' => 'My site', 'description' => 'Description']);
        $this->site()->saveTranslation('en', ['name' => 'Renamed']);

        $this->assertSame('Description', $this->site()->description('en'));
    }

    public function test_clearing_removes_every_value_of_the_language_only(): void
    {
        $this->site()->saveTranslation('en', ['name' => 'My site', 'phones' => [['value' => '+380 44 000', 'label' => 'Sales']], 'schedule' => [['Mon', '9', '18', '']]]);
        $this->site()->saveTranslation('pl', ['name' => 'Moja strona']);

        $this->site()->clearTranslation('en');

        $this->assertFalse($this->site()->hasTranslation('en'));
        $this->assertSame(['default' => 'Мій сайт', 'pl' => 'Moja strona'], $this->stored('name'));
        $this->assertSame('Мій сайт', $this->site()->name('en'));
        $this->assertSame('Відділ продажів', $this->site()->phones('en')[0]['label']);
        $this->assertSame([['Пн', '9', '18', '']], $this->site()->schedule('en'));
    }

    public function test_the_translation_form_shape_is_prefilled_from_the_base(): void
    {
        $values = $this->site()->translationValues('en');

        $this->assertSame('Мій сайт', $values['name']);
        $this->assertSame([['value' => '+380 44 000', 'label' => 'Відділ продажів']], $values['phones']);
        $this->assertSame([['value' => 'a@b.c', 'label' => '']], $values['emails']);

        $this->site()->saveTranslation('en', ['name' => 'My site', 'phones' => [['value' => '+380 44 000', 'label' => 'Sales']]]);
        $values = $this->site()->translationValues('en');

        $this->assertSame('My site', $values['name']);
        $this->assertSame('Sales', $values['phones'][0]['label']);
    }

    public function test_the_base_and_unknown_languages_are_rejected(): void
    {
        foreach (['default', 'de'] as $lang) {
            try {
                $this->site()->saveTranslation($lang, ['name' => 'X']);
                $this->fail("Language {$lang} must be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('lang', $e->errors());
            }
        }

        $this->assertSame(['default' => 'Мій сайт'], $this->stored('name'));
    }

    public function test_a_language_with_a_translated_setting_cannot_be_deleted(): void
    {
        $languages = app(ContentLanguages::class);
        $this->assertFalse($languages->isInUse('en'));

        $this->site()->saveTranslation('en', ['address' => 'Kyiv']);

        $this->assertTrue($languages->isInUse('en'));
        $this->assertFalse($languages->isInUse('pl'));

        $this->site()->clearTranslation('en');
        $this->assertFalse($languages->isInUse('en'));
    }

    public function test_a_translated_label_alone_marks_the_language_as_used(): void
    {
        $this->site()->saveTranslation('en', ['phones' => [['value' => '+380 44 000', 'label' => 'Sales']]]);

        $this->assertTrue(app(ContentLanguages::class)->isInUse('en'));
        $this->assertSame(['en'], $this->site()->translatedLanguages(['en', 'pl']));
    }
}
