<?php

namespace Webtack\Ecms\Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Settings\SiteSettings;
use Webtack\Ecms\Support\AppSettings;
use Webtack\Ecms\Tests\TestCase;

class SiteSettingsReadTest extends TestCase
{
    use RefreshDatabase;

    private function site(): SiteSettings
    {
        return app(SiteSettings::class);
    }

    private function raw(string $key, mixed $value): void
    {
        app(AppSettings::class)->set("site.{$key}", $value);
    }

    public function test_an_empty_installation_reads_empty(): void
    {
        $site = $this->site();

        $this->assertNull($site->name());
        $this->assertNull($site->shortName());
        $this->assertNull($site->logoLarge());
        $this->assertNull($site->logoSmall());
        $this->assertSame([], $site->emails());
        $this->assertSame([], $site->phones());
        $this->assertSame([], $site->socials());
        $this->assertSame([], $site->schedule());
        $this->assertFalse($site->indexable());
    }

    public function test_a_partial_installation_reads_the_rest_as_empty(): void
    {
        $this->raw('name', ['default' => 'My site']);

        $this->assertSame('My site', $this->site()->name());
        $this->assertSame([], $this->site()->phones());
    }

    public function test_a_language_falls_back_to_default(): void
    {
        $this->raw('name', ['default' => 'Мій сайт', 'en' => 'My site']);
        $this->raw('short_name', ['default' => 'Сайт']);

        $this->assertSame('My site', $this->site()->name('en'));
        $this->assertSame('Мій сайт', $this->site()->name('de'));
        $this->assertSame('Сайт', $this->site()->shortName('en'));
    }

    public function test_lists_keep_their_order_and_labels_fall_back(): void
    {
        $this->raw('phones', [
            ['value' => '+380 1', 'label' => ['default' => 'Sales', 'en' => 'Sales EN']],
            ['value' => '+380 2'],
            ['value' => '+380 3', 'label' => ['default' => 'Support']],
        ]);

        $this->assertSame([
            ['value' => '+380 1', 'label' => 'Sales EN'],
            ['value' => '+380 2', 'label' => null],
            ['value' => '+380 3', 'label' => 'Support'],
        ], $this->site()->phones('en'));
    }

    public function test_socials_and_schedule_are_read(): void
    {
        $this->raw('socials', [['network' => 'instagram', 'url' => 'https://i.example/a'], ['network' => 'instagram', 'url' => 'https://i.example/b']]);
        $this->raw('schedule', ['default' => [['Mon–Fri', '9:00', '18:00', ''], ['Sat', '10:00', '14:00', '']]]);

        $this->assertSame(['https://i.example/a', 'https://i.example/b'], array_column($this->site()->socials(), 'url'));
        $this->assertSame(['Mon–Fri', '9:00', '18:00', ''], $this->site()->schedule()[0]);
    }

    public function test_indexable_is_read(): void
    {
        $this->raw('indexable', true);

        $this->assertTrue($this->site()->indexable());
    }

    public function test_form_values_are_flat(): void
    {
        $this->raw('name', ['default' => 'A']);
        $this->raw('emails', [['value' => 'a@b.c']]);

        $values = $this->site()->formValues();

        $this->assertSame('A', $values['name']);
        $this->assertSame('', $values['short_name']);
        $this->assertSame([['value' => 'a@b.c', 'label' => '']], $values['emails']);
        $this->assertFalse($values['indexable']);
    }

    public function test_a_view_bound_to_a_language_reads_it_with_the_default_fallback(): void
    {
        $this->raw('name', ['default' => 'Мій сайт', 'en' => 'My site']);
        $this->raw('short_name', ['default' => 'Сайт', 'en' => '']);

        $view = $this->site()->forLang('en');

        $this->assertSame('My site', $view->name());
        $this->assertSame('Сайт', $view->shortName());
        $this->assertSame('Мій сайт', $this->site()->forLang('pl')->name());
    }
}
