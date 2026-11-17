<?php

namespace Webtack\Ecms\Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\TestCase;
use Webtack\Ecms\Theme\LanguageLinks;

/**
 * The language signals of a public page: alternates with `x-default`, the canonical address, and the data and markup
 * of the language switcher.
 */
class PublicHeadTest extends TestCase
{
    use RefreshDatabase;

    private function multilingual(): void
    {
        AppLanguage::factory()->active()->create(['code' => 'en', 'name' => 'English', 'weight' => 0]);
        AppLanguage::factory()->active()->create(['code' => 'pl', 'name' => 'Polski', 'weight' => 1]);
        app(ContentLanguages::class)->setBaseLang('uk');
        app(ContentLanguages::class)->setMultilingual(true);
    }

    private function page(string $slug, string $title = 'Про нас'): ContentNode
    {
        $type = ContentType::findByKey('static') ?? ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        return app(ContentNodeCreator::class)->create($type, ['title' => $title], slug: $slug, authorId: $this->authorId());
    }

    private function translate(ContentNode $node, string $lang, string $title): void
    {
        app(ContentValueWriter::class)->write($node, ['title' => $title], $lang);
    }

    public function test_a_translated_page_has_its_own_canonical_alternates_and_x_default(): void
    {
        $this->multilingual();
        $this->translate($this->page('about-us'), 'en', 'About us');

        $html = $this->get('/en/about-us')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.url('/en/about-us').'">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="uk" href="'.url('/about-us').'">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="'.url('/en/about-us').'">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="'.url('/about-us').'">', $html);
        $this->assertStringNotContainsString('<link rel="alternate" hreflang="pl"', $html);
    }

    public function test_a_page_without_a_translation_is_canonical_to_the_base_address_and_not_an_alternate(): void
    {
        $this->multilingual();
        $this->page('about-us');

        $html = $this->get('/en/about-us')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.url('/about-us').'">', $html);
        $this->assertStringNotContainsString('<link rel="alternate" hreflang="en"', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default"', $html);
    }

    public function test_the_base_language_page_lists_the_translated_languages(): void
    {
        $this->multilingual();
        $this->translate($this->page('about-us'), 'pl', 'O nas');

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.url('/about-us').'">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="pl" href="'.url('/pl/about-us').'">', $html);
        $this->assertStringNotContainsString('<link rel="alternate" hreflang="en"', $html);
    }

    public function test_a_page_without_a_node_is_self_canonical_and_listed_in_every_language(): void
    {
        $this->multilingual();

        $html = $this->get('/en')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.url('/en').'">', $html);

        foreach (['uk' => '/', 'en' => '/en', 'pl' => '/pl'] as $code => $path) {
            $this->assertStringContainsString('hreflang="'.$code.'" href="'.rtrim(url($path), '/').'"', str_replace(url('/').'/"', url('/').'"', $html), $code);
        }
    }

    public function test_the_search_page_is_self_canonical_without_the_query(): void
    {
        $this->multilingual();
        $links = app(LanguageLinks::class);
        $this->get('/en/search?q=lamp');

        $data = $links->make();

        $this->assertSame(url('/en/search'), $data['canonical']);
        $this->assertCount(4, $data['alternates']);
    }

    public function test_a_single_language_site_has_no_alternates_canonical_or_switcher(): void
    {
        $this->page('about-us');

        $html = $this->get('/about-us')->assertOk()->getContent();

        $this->assertStringNotContainsString('rel="canonical"', $html);
        $this->assertStringNotContainsString('hreflang', $html);
        $this->assertStringNotContainsString('language-switcher', $html);
    }

    public function test_the_404_has_no_language_links(): void
    {
        $this->multilingual();

        $html = $this->get('/en/missing')->assertNotFound()->getContent();

        $this->assertStringNotContainsString('rel="canonical"', $html);
        $this->assertStringNotContainsString('rel="alternate"', $html);
    }

    public function test_the_languages_data_has_the_base_first_and_the_address_of_the_same_page(): void
    {
        $this->multilingual();
        $this->translate($this->page('about-us'), 'en', 'About us');
        $this->get('/en/about-us');

        $languages = app(LanguageLinks::class)->make(ContentNode::findBySlug('about-us'))['languages'];

        $this->assertSame(['uk', 'en', 'pl'], array_column($languages, 'code'));
        $this->assertSame(['/about-us', '/en/about-us', '/pl/about-us'], array_column($languages, 'url'));
        $this->assertSame([false, true, false], array_column($languages, 'current'));
        $this->assertSame([true, true, false], array_column($languages, 'translated'));
    }

    public function test_the_default_theme_prints_a_switcher_when_there_are_several_languages(): void
    {
        $this->multilingual();
        $this->translate($this->page('about-us'), 'en', 'About us');

        $html = $this->get('/en/about-us')->assertOk()->getContent();

        $this->assertStringContainsString('class="language-switcher', $html);
        $this->assertStringContainsString('href="/about-us" hreflang="uk"', $html);
        $this->assertStringContainsString('href="/pl/about-us" hreflang="pl"', $html);
        $this->assertStringContainsString('aria-current="true" lang="en">English</span>', $html);
    }
}
