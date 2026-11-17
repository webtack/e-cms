<?php

namespace Webtack\Ecms\Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Support\AppSettings;
use Webtack\Ecms\Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    private AppSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = app(AppSettings::class);
    }

    public function test_it_is_a_singleton(): void
    {
        $this->assertSame($this->settings, app(AppSettings::class));
    }

    public function test_a_value_is_written_and_read(): void
    {
        $this->settings->set('content.multilingual', true);

        $this->assertTrue($this->settings->get('content.multilingual'));
    }

    public function test_structures_keep_their_types(): void
    {
        $value = ['a' => [1, 2], 'b' => ['c' => 1.5, 'd' => null, 'e' => 'x', 'f' => false]];

        $this->settings->set('mail.config', $value);
        $this->settings->flush();

        $this->assertSame($value, $this->settings->get('mail.config'));
    }

    public function test_overwriting_keeps_a_single_row(): void
    {
        $this->settings->set('seo.title', 'one');
        $this->settings->set('seo.title', 'two');

        $this->assertSame(1, DB::table('app_settings')->count());
        $this->assertSame('two', $this->settings->get('seo.title'));
    }

    public function test_forgetting_returns_the_default(): void
    {
        $this->settings->set('seo.title', 'one');
        $this->settings->forget('seo.title');

        $this->assertNull($this->settings->get('seo.title'));
        $this->assertSame('fallback', $this->settings->get('seo.title', 'fallback'));
        $this->assertFalse($this->settings->has('seo.title'));
    }

    public function test_a_missing_key_returns_the_default(): void
    {
        $this->assertFalse($this->settings->get('content.multilingual', false));
    }

    public function test_a_stored_null_differs_from_a_missing_key(): void
    {
        $this->settings->set('seo.title', null);
        $this->settings->flush();

        $this->assertTrue($this->settings->has('seo.title'));
        $this->assertNull($this->settings->get('seo.title', 'fallback'));
    }

    public function test_invalid_keys_are_rejected(): void
    {
        foreach (['Multilingual', 'a..b', 'single', 'a.B', '.a.b', 'a.b.', str_repeat('a', 190).'.b'] as $key) {
            try {
                $this->settings->set($key, 1);
                $this->fail("Key [{$key}] must be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('key', $e->errors());
            }
        }
        $this->assertSame(0, DB::table('app_settings')->count());
    }

    public function test_prefix_returns_only_matching_keys(): void
    {
        $this->settings->set('content.multilingual', true);
        $this->settings->set('content.extra', [1]);
        $this->settings->set('seo.title', 'x');

        $this->assertSame(
            ['content.multilingual' => true, 'content.extra' => [1]],
            $this->settings->prefix('content.'),
        );
    }

    public function test_a_read_after_a_write_sees_the_new_value(): void
    {
        $this->assertNull($this->settings->get('seo.title'));
        $this->settings->set('seo.title', 'fresh');
        $this->assertSame('fresh', $this->settings->get('seo.title'));

        $this->settings->forget('seo.title');
        $this->assertFalse($this->settings->has('seo.title'));
    }
}
