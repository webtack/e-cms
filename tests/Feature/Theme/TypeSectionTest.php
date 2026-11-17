<?php

namespace Webtack\Ecms\Tests\Feature\Theme;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Support\AppSettings;
use Webtack\Ecms\Tests\TestCase;
use Webtack\Ecms\Theme\TypeSection;

/** The public title of a type: `section_title` in the reading language, then the base language, then the name. */
class TypeSectionTest extends TestCase
{
    use RefreshDatabase;

    private function type(string $key = 'news', string $kind = 'content'): ContentType
    {
        return ContentType::create(['key' => $key, 'name' => 'News (admin)', 'slug' => $kind === 'content' ? $key : null, 'kind' => $kind]);
    }

    private function section(ContentType $type, string $value, string $lang = 'default'): void
    {
        app(ContentValueWriter::class)->write(ValueOwner::forType($type), ['section_title' => $value], $lang);
    }

    /** Read in this language from now on (the context is scoped, so a new one replaces it). */
    private function readIn(string $lang): void
    {
        app(AppSettings::class)->set('content.multilingual', true);
        AppLanguage::factory()->active()->create(['code' => $lang]);
        $this->app->instance(PresentContext::class, new PresentContext(app(ContentValueReader::class), $lang));
        $this->app->forgetInstance(TypeSection::class);
    }

    public function test_a_translated_title_is_used(): void
    {
        $type = $this->type();
        $this->readIn('uk');
        $this->section($type, 'News', 'default');
        $this->section($type, 'Новини', 'uk');

        $this->assertSame('Новини', app(TypeSection::class)->title($type));
    }

    public function test_a_missing_translation_falls_back_to_the_base_language(): void
    {
        $type = $this->type();
        $this->readIn('uk');
        $this->section($type, 'Base title');

        $this->assertSame('Base title', app(TypeSection::class)->title($type));
    }

    public function test_no_section_title_falls_back_to_the_name(): void
    {
        $this->assertSame('News (admin)', app(TypeSection::class)->title($this->type()));
    }

    public function test_an_empty_value_counts_as_absent(): void
    {
        $type = $this->type();
        $this->readIn('uk');
        $this->section($type, 'Base title');
        $this->section($type, '   ', 'uk');

        $this->assertSame('Base title', app(TypeSection::class)->title($type));
    }

    public function test_the_title_is_read_once_per_request(): void
    {
        $type = $this->type();
        $this->section($type, 'Новини');
        $service = app(TypeSection::class);
        $service->title($type);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->assertSame('Новини', $service->title($type));
        $this->assertSame(0, $queries);
    }

    public function test_a_page_type_returns_its_name_without_reading(): void
    {
        $type = $this->type('static', 'page');
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->assertSame('News (admin)', app(TypeSection::class)->title($type));
        $this->assertSame(0, $queries);
    }
}
