<?php

namespace Webtack\Ecms\Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Content\ValueOwner;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\File;
use Webtack\Ecms\Settings\SiteSettings;
use Webtack\Ecms\Support\AppSettings;
use Webtack\Ecms\Tests\TestCase;
use Webtack\Ecms\Theme\PageMeta;
use Webtack\Ecms\Theme\TypeSection;

/**
 * The `<head>` meta data of a public page: the record's own `meta_*` values, then the fields its type chose in "Meta
 * data" (`settings.public.meta`), then the `meta_*` values of the type itself, then the site settings.
 */
class PageMetaTest extends TestCase
{
    use RefreshDatabase;

    private const WATCH = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function image(): File
    {
        $uuid = File::newUuid();

        return File::factory()->create(['uuid' => $uuid, 'profile' => 'image', 'mime' => 'image/png', 'path' => "{$uuid}.png", 'size' => 1000]);
    }

    /**
     * A page type with a string `headline`, a text `summary`, a richtext `body`, a multiple image `cover` and a youtube `clip`,
     * choosing them as the meta data of the type.
     *
     * @param  array<string, string>  $meta
     */
    private function doc(array $meta = ['title' => 'headline', 'description' => 'summary', 'image' => 'cover']): ContentType
    {
        $type = ContentType::create(['key' => 'doc', 'name' => 'Doc', 'kind' => 'page']);

        foreach ([['headline', 'string', false], ['summary', 'text', false], ['body', 'richtext', false], ['cover', 'image', true], ['clip', 'youtube', false]] as $weight => [$key, $fieldType, $multiple]) {
            ContentField::create(['content_type' => 'doc', 'key' => $key, 'name' => ucfirst($key), 'type' => $fieldType, 'is_multiple' => $multiple, 'weight' => $weight + 1]);
        }

        $type->update(['settings' => ['public' => ['meta' => $meta]]]);

        return $type->fresh();
    }

    /** @param  array<string, mixed>  $values */
    private function page(ContentType $type, array $values, string $slug = 'about'): ContentNode
    {
        return app(ContentNodeCreator::class)->create($type, ['title' => 'About us', ...$values], slug: $slug, authorId: $this->authorId());
    }

    private function open(string $path = '/about'): TestResponse
    {
        return $this->get($path)->assertOk();
    }

    public function test_the_home_page_takes_the_site_settings(): void
    {
        $logo = $this->image();
        app(SiteSettings::class)->save(['name' => 'Acme', 'description' => 'Best things', 'logo_large' => $logo->path]);

        $this->open('/')
            ->assertSee('<title>Acme</title>', false)
            ->assertSee('<meta name="description" content="Best things">', false)
            ->assertSee('property="og:image" content="'.url("/storage/{$logo->path}").'"', false);
    }

    public function test_the_small_logo_is_the_image_when_there_is_no_large_one(): void
    {
        $logo = $this->image();
        app(SiteSettings::class)->save(['name' => 'Acme', 'logo_small' => $logo->path]);

        $this->open('/')->assertSee('property="og:image" content="'.url("/storage/{$logo->path}").'"', false);
    }

    public function test_a_page_without_own_meta_inherits_the_site_description_and_logo(): void
    {
        $logo = $this->image();
        app(SiteSettings::class)->save(['name' => 'Acme', 'description' => 'Best things', 'logo_large' => $logo->path]);
        $this->page($this->doc([]), []);

        $this->open()
            ->assertSee('<title>About us</title>', false)
            ->assertSee('<meta name="description" content="Best things">', false)
            ->assertSee('property="og:image" content="'.url("/storage/{$logo->path}").'"', false);
    }

    public function test_the_own_meta_of_a_record_wins_over_the_chosen_fields_and_the_site(): void
    {
        app(SiteSettings::class)->save(['name' => 'Acme', 'description' => 'Best things']);
        $this->page($this->doc(), ['meta_description' => 'About us', 'summary' => 'From the field']);

        $this->open()->assertSee('<meta name="description" content="About us">', false);
    }

    public function test_the_chosen_fields_replace_the_site_settings(): void
    {
        $site = $this->image();
        $cover = $this->image();
        app(SiteSettings::class)->save(['name' => 'Acme', 'description' => 'Best things', 'logo_large' => $site->path]);
        $this->page($this->doc(), ['headline' => 'Our story', 'summary' => 'The field text', 'cover' => [$cover->path]]);

        $this->open()
            ->assertSee('<title>Our story</title>', false)
            ->assertSee('<meta name="description" content="The field text">', false)
            ->assertSee('property="og:image" content="'.url("/storage/{$cover->path}").'"', false)
            ->assertDontSee('Best things')
            ->assertDontSee('property="og:image" content="'.url("/storage/{$site->path}").'"', false);
    }

    public function test_a_multiple_image_field_gives_the_first_file(): void
    {
        $first = $this->image();
        $second = $this->image();
        $this->page($this->doc(), ['cover' => [$first->path, $second->path]]);

        // The page body shows both files, so only the tag is checked.
        $this->open()
            ->assertSee('property="og:image" content="'.url("/storage/{$first->path}").'"', false)
            ->assertDontSee('property="og:image" content="'.url("/storage/{$second->path}").'"', false);
    }

    public function test_html_of_a_richtext_description_is_removed(): void
    {
        $this->page($this->doc(['description' => 'body']), ['body' => '<p>Hello <strong>world</strong></p>']);

        $this->open()->assertSee('<meta name="description" content="Hello world">', false);
    }

    public function test_a_long_description_is_cut_at_a_word_with_an_ellipsis(): void
    {
        $text = implode(' ', array_fill(0, 40, 'Привіт світе'));
        $this->page($this->doc(), ['summary' => $text]);

        preg_match('/<meta name="description" content="([^"]*)">/u', $this->open()->getContent(), $found);
        $description = html_entity_decode($found[1]);

        $this->assertLessThanOrEqual(160, mb_strlen($description));
        $this->assertStringEndsWith('…', $description);
        $this->assertStringStartsWith('Привіт світе Привіт', $description);
        $this->assertMatchesRegularExpression('/(Привіт|світе)…$/u', $description);
    }

    public function test_a_short_description_is_kept_whole(): void
    {
        $this->page($this->doc(), ['summary' => "Two\n  lines"]);

        $this->open()->assertSee('<meta name="description" content="Two lines">', false);
    }

    public function test_a_youtube_field_gives_the_screenshot(): void
    {
        $screen = $this->image();
        $this->page($this->doc(['image' => 'clip']), ['clip' => ['url' => self::WATCH, 'screen' => $screen->path]]);

        $this->open()->assertSee('property="og:image" content="'.url("/storage/{$screen->path}").'"', false);
    }

    public function test_a_video_without_a_screenshot_falls_back_to_the_site_logo(): void
    {
        $logo = $this->image();
        app(SiteSettings::class)->save(['name' => 'Acme', 'logo_large' => $logo->path]);
        $this->page($this->doc(['image' => 'clip']), ['clip' => ['url' => self::WATCH]]);

        $this->open()->assertSee('property="og:image" content="'.url("/storage/{$logo->path}").'"', false);
    }

    public function test_an_empty_chosen_field_falls_back_to_the_site(): void
    {
        app(SiteSettings::class)->save(['name' => 'Acme', 'description' => 'Best things']);
        $this->page($this->doc(), ['summary' => '   ']);

        $this->open()->assertSee('<meta name="description" content="Best things">', false);
    }

    public function test_without_any_source_the_tags_are_not_printed(): void
    {
        $this->page($this->doc(), []);

        $this->open()
            ->assertDontSee('name="description"', false)
            ->assertDontSee('og:image', false);
    }

    public function test_a_stale_choice_is_ignored(): void
    {
        app(SiteSettings::class)->save(['name' => 'Acme', 'description' => 'Best things']);
        $type = $this->doc();
        $this->page($type, ['summary' => 'The field text']);
        ContentField::where('content_type', 'doc')->where('key', 'summary')->delete();

        $this->open()->assertSee('<meta name="description" content="Best things">', false);
    }

    /** @param  array<string, mixed>  $values */
    private function typeSeo(ContentType $type, array $values): void
    {
        app(ContentValueWriter::class)->write(ValueOwner::forType($type), $values);
    }

    public function test_the_type_meta_replaces_the_site_settings(): void
    {
        $site = $this->image();
        $typeImage = $this->image();
        app(SiteSettings::class)->save(['name' => 'Acme', 'description' => 'Best things', 'logo_large' => $site->path]);
        $type = $this->doc([]);
        $this->typeSeo($type, ['meta_description' => 'About the type', 'meta_image' => $typeImage->path]);
        $this->page($type, []);

        $this->open()
            ->assertSee('<meta name="description" content="About the type">', false)
            ->assertSee('property="og:image" content="'.url("/storage/{$typeImage->path}").'"', false)
            ->assertDontSee('Best things')
            ->assertDontSee('property="og:image" content="'.url("/storage/{$site->path}").'"', false);
    }

    public function test_the_record_title_wins_over_the_type_meta_title(): void
    {
        app(SiteSettings::class)->save(['name' => 'Acme']);
        $type = $this->doc([]);
        $this->typeSeo($type, ['meta_title' => 'Type title']);
        $this->page($type, []);

        $this->open()->assertSee('<title>About us</title>', false);
    }

    public function test_the_record_and_the_chosen_fields_win_over_the_type_meta(): void
    {
        app(SiteSettings::class)->save(['name' => 'Acme']);
        $type = $this->doc();
        $this->typeSeo($type, ['meta_title' => 'Type title', 'meta_description' => 'Type text']);
        $this->page($type, ['meta_title' => 'Record title', 'summary' => 'The field text']);

        $this->open()
            ->assertSee('<title>Record title</title>', false)
            ->assertSee('<meta name="description" content="The field text">', false);
    }

    public function test_an_empty_chosen_field_falls_back_to_the_type_meta_before_the_site(): void
    {
        app(SiteSettings::class)->save(['name' => 'Acme', 'description' => 'Best things']);
        $type = $this->doc();
        $this->typeSeo($type, ['meta_description' => 'Type text']);
        $this->page($type, ['summary' => '  ']);

        $this->open()->assertSee('<meta name="description" content="Type text">', false);
    }

    public function test_a_long_type_description_is_cut_like_a_field(): void
    {
        $type = $this->doc([]);
        $this->typeSeo($type, ['meta_description' => implode(' ', array_fill(0, 40, 'Привіт світе'))]);
        $this->page($type, []);

        preg_match('/<meta name="description" content="([^"]*)">/u', $this->open()->getContent(), $found);
        $description = html_entity_decode($found[1]);

        $this->assertLessThanOrEqual(160, mb_strlen($description));
        $this->assertStringEndsWith('…', $description);
    }

    public function test_the_list_page_of_a_type_takes_the_type_meta_then_the_site(): void
    {
        app(SiteSettings::class)->save(['name' => 'Acme', 'description' => 'Best things']);
        $news = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        $this->typeSeo($news, ['meta_title' => 'All the news']);

        // The list has the type title only; the description is the site's until the type has one.
        $this->get('/news')->assertOk()
            ->assertSee('<title>All the news</title>', false)
            ->assertSee('<meta name="description" content="Best things">', false);

        $this->typeSeo($news, ['meta_description' => 'News of the type']);
        $this->get('/news')->assertOk()->assertSee('<meta name="description" content="News of the type">', false);
    }

    public function test_a_type_without_own_meta_values_falls_back_to_the_site(): void
    {
        app(SiteSettings::class)->save(['name' => 'Acme', 'description' => 'Best things']);
        $this->page($this->doc([]), []);

        $this->open()->assertSee('<meta name="description" content="Best things">', false);
    }

    /** @param  array<string, mixed>  $values */
    private function section(ContentType $type, string $value, string $lang = 'default'): void
    {
        app(ContentValueWriter::class)->write(ValueOwner::forType($type), ['section_title' => $value], $lang);
    }

    public function test_the_list_page_takes_the_section_title_without_a_meta_title(): void
    {
        app(SiteSettings::class)->save(['name' => 'Acme']);
        $news = ContentType::create(['key' => 'news', 'name' => 'News (admin)', 'slug' => 'news']);
        $this->section($news, 'Latest news');

        $this->get('/news')->assertOk()->assertSee('<title>Latest news</title>', false);
    }

    public function test_the_meta_title_wins_over_the_section_title(): void
    {
        app(SiteSettings::class)->save(['name' => 'Acme']);
        $news = ContentType::create(['key' => 'news', 'name' => 'News (admin)', 'slug' => 'news']);
        $this->section($news, 'Latest news');
        $this->typeSeo($news, ['meta_title' => 'News | Acme']);

        $this->get('/news')->assertOk()->assertSee('<title>News | Acme</title>', false);
    }

    public function test_the_section_title_follows_the_reading_language_then_the_base_one(): void
    {
        app(AppSettings::class)->set('content.multilingual', true);
        AppLanguage::factory()->active()->create(['code' => 'uk']);
        $news = ContentType::create(['key' => 'news', 'name' => 'News (admin)', 'slug' => 'news']);
        $this->section($news, 'News');
        $this->app->instance(PresentContext::class, new PresentContext(app(ContentValueReader::class), 'uk'));
        $this->app->forgetInstance(TypeSection::class);

        $this->assertSame('News', app(PageMeta::class)->make([], $news->fresh())['title']);

        $this->section($news, 'Новини', 'uk');
        $this->app->forgetInstance(TypeSection::class);

        $this->assertSame('Новини', app(PageMeta::class)->make([], $news->fresh())['title']);
    }

    public function test_a_type_without_titles_keeps_the_site_name_not_the_type_name(): void
    {
        app(SiteSettings::class)->save(['name' => 'Acme']);
        ContentType::create(['key' => 'news', 'name' => 'News (admin)', 'slug' => 'news']);

        $this->get('/news')->assertOk()
            ->assertSee('<title>Acme</title>', false)
            ->assertDontSee('<title>News (admin)', false);
    }

    public function test_the_record_title_wins_over_the_type_section_title(): void
    {
        app(SiteSettings::class)->save(['name' => 'Acme']);
        $type = $this->doc([]);
        $this->page($type, []);

        $this->assertSame('About us', app(PageMeta::class)->make(['title' => 'About us'], $type)['title']);
    }
}
