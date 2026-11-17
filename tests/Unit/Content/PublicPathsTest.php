<?php

namespace Webtack\Ecms\Tests\Unit\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Webtack\Ecms\Content\PublicPaths;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\TestCase;

class PublicPathsTest extends TestCase
{
    use RefreshDatabase;

    private function paths(): PublicPaths
    {
        return app(PublicPaths::class);
    }

    public function test_reserved_paths_come_from_the_config_in_lower_case(): void
    {
        config(['content.reserved_paths' => ['Admin', 'login']]);

        // `vendor` is always reserved: the package publishes its static files to `public/vendor/ecms`.
        $this->assertSame(['admin', 'login', 'vendor'], $this->paths()->reserved());
        $this->assertSame('reserved', $this->paths()->pageSlugConflict('ADMIN'));
        $this->assertSame('reserved', $this->paths()->typeSlugConflict('Login'));
    }

    public function test_a_page_slug_conflicts_with_a_content_type_slug(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        $this->assertSame('type', $this->paths()->pageSlugConflict('news'));
        $this->assertSame('type', $this->paths()->pageSlugConflict('News'));
        $this->assertNull($this->paths()->pageSlugConflict('about'));
        $this->assertTrue($this->paths()->isTakenForPage('news'));
        $this->assertFalse($this->paths()->isTakenForPage('about'));
    }

    public function test_a_type_slug_conflicts_with_a_page_slug(): void
    {
        ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);
        ContentNode::factory()->create(['content_type' => 'static', 'slug' => 'about-us']);

        $this->assertSame('page', $this->paths()->typeSlugConflict('about-us'));
        $this->assertSame('page', $this->paths()->typeSlugConflict('About-Us'));
        $this->assertNull($this->paths()->typeSlugConflict('contacts'));
    }

    public function test_content_and_block_nodes_do_not_take_a_first_segment(): void
    {
        ContentType::create(['key' => 'article', 'name' => 'Article']);
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);
        ContentNode::factory()->create(['content_type' => 'article', 'slug' => 'story']);
        ContentNode::factory()->create(['content_type' => 'hero', 'slug' => 'block-abcdefgh']);

        $this->assertNull($this->paths()->typeSlugConflict('story'));
        $this->assertNull($this->paths()->typeSlugConflict('block-abcdefgh'));
    }

    public function test_a_page_type_has_no_slug_so_it_never_conflicts_with_a_page(): void
    {
        ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        $this->assertNull($this->paths()->pageSlugConflict('static'));
    }

    public function test_search_is_a_reserved_path_for_pages_and_the_first_segment_of_type_slugs(): void
    {
        $this->assertContains('search', $this->paths()->reserved());
        $this->assertSame('reserved', $this->paths()->pageSlugConflict('search'));
        $this->assertSame('reserved', $this->paths()->typeSlugConflict('search'));
        $this->assertSame('reserved', $this->paths()->typeSlugConflict('search/reports'));
        $this->assertNull($this->paths()->typeSlugConflict('news/search'));
    }

    public function test_sitemap_is_a_reserved_site_path(): void
    {
        $this->assertContains('sitemap', $this->paths()->reserved());
        $this->assertContains('sitemap', PublicPaths::SITE_PATHS);
        $this->assertSame('reserved', $this->paths()->pageSlugConflict('sitemap'));
        $this->assertSame('reserved', $this->paths()->typeSlugConflict('sitemap'));
        $this->assertSame('reserved', $this->paths()->typeSlugConflict('sitemap/archive'));
        $this->assertNull($this->paths()->typeSlugConflict('news/sitemap'));
    }
}
