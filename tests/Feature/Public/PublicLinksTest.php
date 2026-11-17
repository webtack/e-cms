<?php

namespace Webtack\Ecms\Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\FieldTypeRegistry;
use Webtack\Ecms\Content\LocalizedUrl;
use Webtack\Ecms\Content\PresentContext;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Models\Menu;
use Webtack\Ecms\Models\MenuItem;
use Webtack\Ecms\Models\MenuItemTranslation;
use Webtack\Ecms\Tests\TestCase;

/**
 * Internal links stay in the language of the page: menus, breadcrumbs, cards, references, `url` values, rich text,
 * pagination. Absolute, anchor, service and file links are never prefixed.
 */
class PublicLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AppLanguage::factory()->active()->create(['code' => 'en', 'weight' => 0]);
        app(ContentLanguages::class)->setBaseLang('uk');
        app(ContentLanguages::class)->setMultilingual(true);
    }

    private function url(): LocalizedUrl
    {
        return app(LocalizedUrl::class);
    }

    private function english(): void
    {
        $this->url()->use('en');
    }

    private function page(string $slug, string $title = 'Page'): ContentNode
    {
        $type = ContentType::findByKey('static') ?? ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        return app(ContentNodeCreator::class)->create($type, ['title' => $title], slug: $slug, authorId: $this->authorId());
    }

    private function menuItem(string $url): void
    {
        $menu = Menu::findByKey('main') ?? Menu::factory()->create(['key' => 'main', 'name' => 'Main']);
        $item = MenuItem::factory()->in($menu)->create(['url' => $url]);
        MenuItemTranslation::factory()->create(['item_uuid' => $item->uuid, 'lang' => 'default', 'title' => 'Item '.$url]);
    }

    public function test_an_internal_address_gets_the_prefix_only_in_an_additional_language(): void
    {
        $this->assertSame('/about-us', $this->url()->path('/about-us'));

        $this->english();

        $this->assertSame('/en/about-us', $this->url()->path('/about-us'));
        $this->assertSame('/en', $this->url()->path('/'));
        $this->assertSame('/en?x=1', $this->url()->path('/?x=1'));
        $this->assertSame('/en/news?page=2#top', $this->url()->path('/news?page=2#top'));
        $this->assertSame('/en/about-us', $this->url()->path('/about-us', 'en'));
        $this->assertSame('/about-us', $this->url()->path('/about-us', 'default'));
    }

    public function test_external_anchor_service_relative_and_language_addresses_are_left_alone(): void
    {
        $this->english();

        foreach (['https://example.org/x', '//cdn.example.org/x', '#top', 'mailto:a@b.c', 'tel:+380', 'about-us', '/storage/file.pdf', '/admin/dashboard', '/sitemap.xml', '/en/about-us', '/uk/about-us', ''] as $address) {
            $this->assertSame($address, $this->url()->path($address), $address);
        }

        $this->assertSame('/en/search?q=a', $this->url()->path('/search?q=a'));
        $this->assertSame('/en/sitemap', $this->url()->path('/sitemap'));
    }

    public function test_rich_text_links_are_prefixed_on_an_additional_language_page(): void
    {
        $this->english();
        $html = '<p><a href="/about-us">A</a> <a href="https://example.org">B</a> <a href="#x">C</a> <a href="/storage/f.pdf">D</a> <a href="/news?a=1&amp;b=2">E</a> <a href="/">F</a></p>';

        $this->assertSame(
            '<p><a href="/en/about-us">A</a> <a href="https://example.org">B</a> <a href="#x">C</a> <a href="/storage/f.pdf">D</a> <a href="/en/news?a=1&amp;b=2">E</a> <a href="/en">F</a></p>',
            $this->url()->html($html),
        );
    }

    public function test_rich_text_is_untouched_on_a_base_language_page(): void
    {
        $html = '<p><a href="/about-us">A</a></p>';

        $this->assertSame($html, $this->url()->html($html));
    }

    public function test_the_rich_text_field_prefixes_its_links_when_presented(): void
    {
        $this->english();
        ContentType::create(['key' => 'article', 'name' => 'Article', 'kind' => 'page']);
        $field = ContentField::create(['content_type' => 'article', 'key' => 'body', 'name' => 'Body', 'type' => 'richtext']);

        $html = app(FieldTypeRegistry::class)->get('richtext')->present('<p><a href="/about-us">A</a></p>', $field, app(PresentContext::class));

        $this->assertStringContainsString('href="/en/about-us"', $html);
    }

    public function test_a_url_value_is_prefixed_when_presented(): void
    {
        $this->english();
        ContentType::create(['key' => 'article', 'name' => 'Article', 'kind' => 'page']);
        $field = ContentField::create(['content_type' => 'article', 'key' => 'link', 'name' => 'Link', 'type' => 'url']);
        $type = app(FieldTypeRegistry::class)->get('url');

        $this->assertSame('/en/about-us', $type->present(['url' => '/about-us', 'title' => 'A'], $field, app(PresentContext::class))['url']);
        $this->assertSame('https://example.org', $type->present(['url' => 'https://example.org', 'title' => null], $field, app(PresentContext::class))['url']);
    }

    public function test_a_node_reference_has_the_prefixed_address(): void
    {
        app(PresentContext::class)->readIn('en', fallback: true);
        $this->english();
        $node = $this->page('about-us', 'About');

        $this->assertSame('/en/about-us', app(PresentContext::class)->node($node->uuid)['url']);
    }

    public function test_the_menu_breadcrumbs_and_home_link_of_an_english_page_carry_the_prefix(): void
    {
        $this->menuItem('/about-us');
        $this->menuItem('https://example.org');
        $this->menuItem('/storage/file.pdf');
        $type = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        app(ContentNodeCreator::class)->create($type, ['title' => 'Story'], slug: 'story', authorId: $this->authorId());

        $html = $this->get('/en/news/story')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.url('/en/about-us').'"', $html);
        $this->assertStringContainsString('href="https://example.org"', $html);
        $this->assertStringContainsString('href="'.url('/storage/file.pdf').'"', $html);
        $this->assertStringContainsString('href="'.url('/en').'"', $html);
        $this->assertStringContainsString('href="'.url('/en/news').'"', $html);
        $this->assertStringContainsString('href="/en">', $html);

        $base = $this->get('/news/story')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.url('/about-us').'"', $base);
        // Only the language switcher points into another language.
        $this->assertStringNotContainsString(url('/en/about-us'), $base);
        $this->assertStringNotContainsString(url('/en/news'), $base);
    }

    public function test_pagination_keeps_the_prefix(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        $type->update(['settings' => ['public' => ['per_page' => 1]]]);

        foreach (['a', 'b', 'c'] as $slug) {
            app(ContentNodeCreator::class)->create($type, ['title' => "Story {$slug}"], slug: $slug, authorId: $this->authorId());
        }

        $english = $this->get('/en/news')->assertOk()->getContent();
        $base = $this->get('/news')->assertOk()->getContent();

        $this->assertStringContainsString(url('/en/news').'?page=2', html_entity_decode($english));
        $this->assertStringNotContainsString(url('/en/news').'?page=2', html_entity_decode($base));
        $this->assertStringContainsString(url('/news').'?page=2', html_entity_decode($base));
    }

    public function test_a_card_and_the_sitemap_page_carry_the_prefix(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news', 'settings' => ['sitemap' => ['web' => ['type' => true, 'nodes' => true]]]]);
        $node = app(ContentNodeCreator::class)->create($type, ['title' => 'Story'], slug: 'story', authorId: $this->authorId());
        app(ContentValueWriter::class)->write($node, ['title' => 'Story en'], 'en');

        $this->get('/en/news')->assertOk()->assertSee('href="/en/news/story"', false);
        $this->get('/en/sitemap')->assertOk()->assertSee('href="/en/news"', false)->assertSee('href="/en/news/story"', false)->assertSee('href="/en"', false);
        $this->get('/news')->assertOk()->assertSee('href="/news/story"', false);
    }
}
