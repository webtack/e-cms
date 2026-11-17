<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\PublicPaths;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

/**
 * Language codes are reserved first segments of public addresses.
 */
class PublicPathsTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private function paths(): PublicPaths
    {
        return app(PublicPaths::class);
    }

    public function test_a_page_slug_equal_to_an_additional_language_code_is_rejected_even_when_inactive(): void
    {
        $this->enableLanguages('en');
        AppLanguage::query()->where('code', 'en')->update(['is_active' => false]);

        $this->assertSame(PublicPaths::REASON_LANGUAGE, $this->paths()->pageSlugConflict('en'));
        $this->assertSame(PublicPaths::REASON_LANGUAGE, $this->paths()->pageSlugConflict('EN'));
        $this->assertNull($this->paths()->pageSlugConflict('english'));
    }

    public function test_a_type_whose_first_segment_is_a_language_code_is_rejected(): void
    {
        $this->enableLanguages('pl');

        $this->assertSame(PublicPaths::REASON_LANGUAGE, $this->paths()->typeSlugConflict('pl/news'));
        $this->assertSame(PublicPaths::REASON_LANGUAGE, $this->paths()->typeSlugConflict('pl'));
        $this->assertNull($this->paths()->typeSlugConflict('news/pl'));
    }

    public function test_the_saved_base_code_is_reserved(): void
    {
        $this->enableLanguages('en');
        app(ContentLanguages::class)->setBaseLang('uk');

        $this->assertSame(PublicPaths::REASON_LANGUAGE, $this->paths()->pageSlugConflict('uk'));
    }

    public function test_a_language_code_equal_to_a_page_slug_or_a_type_first_segment_is_a_conflict(): void
    {
        $page = ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);
        ContentNode::factory()->create(['content_type' => $page->key, 'slug' => 'faq']);
        ContentType::create(['key' => 'catalog', 'name' => 'Catalog', 'slug' => 'shop/catalog']);

        $this->assertSame(PublicPaths::REASON_PAGE, $this->paths()->languageCodeConflict('faq'));
        $this->assertSame(PublicPaths::REASON_TYPE, $this->paths()->languageCodeConflict('shop'));
        $this->assertSame(PublicPaths::REASON_RESERVED, $this->paths()->languageCodeConflict('admin'));
        $this->assertNull($this->paths()->languageCodeConflict('en'));
    }

    public function test_saving_a_page_with_a_language_code_slug_fails_on_the_slug_field(): void
    {
        $this->enableLanguages('en');
        $page = ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        try {
            ContentNode::factory()->create(['content_type' => $page->key, 'slug' => 'en']);
            $this->fail('A ValidationException was expected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->errors());
        }
    }

    public function test_saving_a_type_with_a_language_code_first_segment_fails_on_the_slug_field(): void
    {
        $this->enableLanguages('pl');

        try {
            ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'pl/news']);
            $this->fail('A ValidationException was expected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->errors());
        }
    }

    public function test_the_generator_skips_a_language_code_for_a_page(): void
    {
        $this->enableLanguages('en');
        $page = ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        $node = app(ContentNodeCreator::class)->create($page, ['title' => 'En'], authorId: $this->authorId());

        $this->assertSame('en-2', $node->slug);
    }
}
