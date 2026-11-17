<?php

namespace Webtack\Ecms\Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\LocalizedUrl;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\TestCase;

/**
 * Language prefix routing of the public site: the prefix is stripped before routing, the base language has none, the
 * reading language comes from the address only, and service paths are never prefixed.
 */
class PublicLanguageRoutingTest extends TestCase
{
    use RefreshDatabase;

    private string $views;

    protected function setUp(): void
    {
        parent::setUp();

        // The `default` theme has no `search/results`, so a throw-away template stands in for a theme's.
        $this->views = sys_get_temp_dir().'/lang-search-views-'.uniqid();
        File::ensureDirectoryExists($this->views.'/search');
        File::put($this->views.'/search/results.blade.php', '<div id="search">{{ $query }}</div>');
        View::prependNamespace('theme', $this->views);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->views);

        parent::tearDown();
    }

    /** Base language `uk`, active additional languages `en` and `pl` (by weight). */
    private function multilingual(string ...$codes): void
    {
        foreach ($codes ?: ['en', 'pl'] as $i => $code) {
            AppLanguage::factory()->active()->create(['code' => $code, 'weight' => $i]);
        }

        app(ContentLanguages::class)->setBaseLang('uk');
        app(ContentLanguages::class)->setMultilingual(true);
    }

    private function page(string $slug, string $title, array $attributes = []): ContentNode
    {
        $type = ContentType::findByKey('static') ?? ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        return app(ContentNodeCreator::class)->create($type, ['title' => $title], slug: $slug, authorId: $this->authorId(), isActive: $attributes['is_active'] ?? true);
    }

    private function record(string $slug, string $title): ContentNode
    {
        $type = ContentType::findByKey('news') ?? ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        return app(ContentNodeCreator::class)->create($type, ['title' => $title], slug: $slug, authorId: $this->authorId());
    }

    private function translate(ContentNode $node, string $lang, array $values): void
    {
        app(ContentValueWriter::class)->write($node, $values, $lang);
    }

    public function test_a_page_is_served_in_an_additional_language_under_its_prefix(): void
    {
        $this->multilingual();
        $this->translate($this->page('about-us', 'Про нас'), 'en', ['title' => 'About us']);

        $this->get('/en/about-us')->assertOk()->assertSee('<title>About us</title>', false)->assertSee('<html lang="en">', false);
        $this->get('/about-us')->assertOk()->assertSee('<title>Про нас</title>', false)->assertSee('<html lang="uk">', false);
    }

    public function test_the_home_page_has_a_prefixed_address(): void
    {
        $this->multilingual();

        $this->get('/en')->assertOk()->assertSee('<html lang="en">', false);
        $this->get('/en/')->assertOk()->assertSee('<html lang="en">', false);
    }

    public function test_a_record_is_served_in_an_additional_language(): void
    {
        $this->multilingual();
        $this->translate($this->record('story', 'Історія'), 'en', ['title' => 'Story']);

        $this->get('/en/news/story')->assertOk()->assertSee('<title>Story</title>', false);
        $this->get('/en/news')->assertOk()->assertSee('Story');
    }

    public function test_the_prefix_is_matched_case_insensitively(): void
    {
        $this->multilingual();
        $this->translate($this->page('about-us', 'Про нас'), 'en', ['title' => 'About us']);

        $this->get('/EN/about-us')->assertOk()->assertSee('<title>About us</title>', false);
    }

    public function test_an_inactive_or_unknown_language_ends_in_the_site_404(): void
    {
        $this->multilingual();
        AppLanguage::query()->where('code', 'pl')->firstOrFail()->update(['is_active' => false]);
        $this->page('about-us', 'Про нас');

        $this->get('/xx/about-us')->assertNotFound()->assertSee('<html lang=', false);
        $this->get('/pl/about-us')->assertNotFound()->assertSee('<html lang=', false);
    }

    public function test_a_prefix_is_an_ordinary_address_while_multilingual_mode_is_off(): void
    {
        AppLanguage::factory()->active()->create(['code' => 'en']);
        $this->page('about-us', 'Про нас');

        $this->get('/en/about-us')->assertNotFound();
        $this->get('/about-us')->assertOk();
    }

    public function test_the_base_prefix_redirects_permanently_keeping_the_query(): void
    {
        $this->multilingual();
        $this->page('about-us', 'Про нас');

        $this->get('/uk/about-us?x=1')->assertStatus(301)->assertRedirect('/about-us?x=1');
        $this->get('/uk')->assertStatus(301)->assertRedirect('/');
        $this->get('/UK/about-us')->assertStatus(301)->assertRedirect('/about-us');
    }

    public function test_the_browser_language_never_changes_the_reading_language(): void
    {
        $this->multilingual();
        $this->translate($this->page('about-us', 'Про нас'), 'en', ['title' => 'About us']);

        $this->get('/about-us', ['Accept-Language' => 'en'])->assertOk()->assertSee('<title>Про нас</title>', false);
    }

    public function test_the_prefix_is_never_stripped_before_a_service_path(): void
    {
        $this->multilingual();

        $this->get('/en/admin/dashboard')->assertNotFound();
        $this->get('/en/login')->assertNotFound();
        $this->get('/en/sitemap.xml')->assertNotFound();
        $this->get('/sitemap.xml')->assertOk();
    }

    public function test_the_search_and_sitemap_pages_have_prefixed_addresses(): void
    {
        $this->multilingual();

        $this->get('/en/search?q=lamp')->assertOk()->assertSee('<div id="search">lamp</div>', false);
        $this->assertSame('en', app(LocalizedUrl::class)->lang());
        $this->get('/en/sitemap')->assertOk()->assertSee('<html lang="en">', false);
    }

    public function test_a_node_without_a_translation_is_served_with_the_base_content(): void
    {
        $this->multilingual();
        $this->page('about-us', 'Про нас');

        $this->get('/en/about-us')->assertOk()->assertSee('<title>Про нас</title>', false);
    }

    public function test_an_invisible_node_is_a_404_in_every_language(): void
    {
        $this->multilingual();
        $this->translate($this->page('hidden', 'Прихована', ['is_active' => false]), 'en', ['title' => 'Hidden']);

        $this->get('/hidden')->assertNotFound();
        $this->get('/en/hidden')->assertNotFound();
    }

    public function test_the_reading_language_does_not_leak_into_the_next_request(): void
    {
        $this->multilingual();
        $this->translate($this->page('about-us', 'Про нас'), 'en', ['title' => 'About us']);

        $this->get('/en/about-us')->assertSee('<title>About us</title>', false);
        $this->get('/about-us')->assertSee('<title>Про нас</title>', false);
        $this->assertSame('default', app(LocalizedUrl::class)->lang());
    }

    public function test_the_interface_locale_follows_the_reading_language_when_it_has_translations(): void
    {
        $this->multilingual();
        $this->page('about-us', 'Про нас');

        $this->get('/en/about-us');
        $this->assertSame('en', app()->getLocale());

        // No lang/pl.json: the interface falls back to the base language's locale (lang/uk.json exists).
        $this->get('/pl/about-us')->assertSee('<html lang="pl">', false);
        $this->assertSame('uk', app()->getLocale());

        $this->get('/about-us')->assertSee('<html lang="uk">', false);
        $this->assertSame('uk', app()->getLocale());
    }

    public function test_a_single_language_site_keeps_the_application_locale(): void
    {
        config(['app.locale' => 'en']);
        $this->page('about-us', 'About');

        $this->get('/about-us')->assertOk()->assertSee('<html lang="en">', false);
        $this->assertSame('en', app()->getLocale());
    }
}
