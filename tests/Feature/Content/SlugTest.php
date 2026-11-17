<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\SlugGenerator;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\TestCase;

class SlugTest extends TestCase
{
    use RefreshDatabase;

    private function creator(): ContentNodeCreator
    {
        return app(ContentNodeCreator::class);
    }

    public function test_a_cyrillic_title_becomes_a_slug(): void
    {
        $page = ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        $this->assertSame('pro-nas', $this->creator()->create($page, ['title' => 'Про нас'], authorId: $this->authorId())->slug);
    }

    public function test_the_creator_accepts_a_legacy_slug_only_with_the_flag(): void
    {
        $type = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        $node = $this->creator()->create($type, ['title' => 'Jam'], slug: 'jam-session---', authorId: $this->authorId(), legacySlug: true);

        $this->assertSame('jam-session---', $node->fresh()->slug);

        try {
            $this->creator()->create($type, ['title' => 'Jam'], slug: 'etno_wave', authorId: $this->authorId());
            $this->fail('A legacy slug must be rejected without the flag.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->errors());
            $this->assertSame(1, ContentNode::count());
        }
    }

    public function test_a_taken_slug_gets_a_numeric_suffix(): void
    {
        $page = ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        $slugs = [
            $this->creator()->create($page, ['title' => 'Про нас'], authorId: $this->authorId())->slug,
            $this->creator()->create($page, ['title' => 'Про нас'], authorId: $this->authorId())->slug,
            $this->creator()->create($page, ['title' => 'Про нас'], authorId: $this->authorId())->slug,
        ];

        $this->assertSame(['pro-nas', 'pro-nas-2', 'pro-nas-3'], $slugs);
    }

    public function test_a_slug_taken_by_another_type_or_kind_is_avoided(): void
    {
        $article = ContentType::create(['key' => 'article', 'name' => 'Article']);
        $page = ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        $this->creator()->create($article, ['title' => 'About'], slug: 'about', authorId: $this->authorId());

        $this->assertSame('about-2', $this->creator()->create($page, ['title' => 'About'], authorId: $this->authorId())->slug);
    }

    public function test_an_explicit_slug_is_kept(): void
    {
        $article = ContentType::create(['key' => 'article', 'name' => 'Article']);

        $this->assertSame('about', $this->creator()->create($article, ['title' => 'Whatever'], slug: 'about', authorId: $this->authorId())->slug);
    }

    public function test_a_taken_explicit_slug_is_rejected(): void
    {
        $article = ContentType::create(['key' => 'article', 'name' => 'Article']);
        $this->creator()->create($article, ['title' => 'One'], slug: 'about', authorId: $this->authorId());

        try {
            $this->creator()->create($article, ['title' => 'Two'], slug: 'about', authorId: $this->authorId());
            $this->fail('Duplicate explicit slug must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('slug', $e->errors());
        }
        $this->assertSame(1, ContentNode::count());
    }

    public function test_a_title_without_usable_characters_uses_the_fallback(): void
    {
        $article = ContentType::create(['key' => 'article', 'name' => 'Article']);

        $this->assertSame('node', $this->creator()->create($article, ['title' => '!!! ???'], authorId: $this->authorId())->slug);
        $this->assertSame('node-2', $this->creator()->create($article, ['title' => '***'], authorId: $this->authorId())->slug);
    }

    public function test_a_title_starting_with_the_block_prefix_is_kept_as_is_for_content_slugs(): void
    {
        $article = ContentType::create(['key' => 'article', 'name' => 'Article']);

        $slug = $this->creator()->create($article, ['title' => 'Block 1'], authorId: $this->authorId())->slug;

        $this->assertSame('block-1', $slug);
    }

    public function test_the_slug_does_not_follow_a_title_change(): void
    {
        $article = ContentType::create(['key' => 'article', 'name' => 'Article']);
        $node = $this->creator()->create($article, ['title' => 'First'], authorId: $this->authorId());

        app(ContentValueWriter::class)->write($node, ['title' => 'Second']);

        $this->assertSame('first', $node->fresh()->slug);
    }

    public function test_a_block_gets_a_random_prefixed_identifier(): void
    {
        $block = ContentType::create(['key' => 'blk', 'name' => 'Block', 'kind' => 'block']);

        $slug = $this->creator()->create($block, ['title' => 'Footer'], authorId: $this->authorId())->slug;

        $this->assertMatchesRegularExpression('/^block-[a-z]{8}$/', $slug);
    }

    public function test_a_manual_block_identifier_is_kept_and_must_have_the_prefix(): void
    {
        $block = ContentType::create(['key' => 'blk', 'name' => 'Block', 'kind' => 'block']);

        $this->assertSame('block-footer-menu', $this->creator()->create($block, ['title' => 'Footer'], slug: 'block-footer-menu', authorId: $this->authorId())->slug);

        $this->expectException(ValidationException::class);
        $this->creator()->create($block, ['title' => 'Footer'], slug: 'footer-menu', authorId: $this->authorId());
    }

    public function test_block_generation_repeats_on_collision(): void
    {
        $block = ContentType::create(['key' => 'blk', 'name' => 'Block', 'kind' => 'block']);
        $generator = new class extends SlugGenerator
        {
            public int $calls = 0;

            public function forBlock(): string
            {
                return ++$this->calls === 1 ? 'block-aaaaaaaa' : parent::forBlock();
            }
        };
        ContentNode::factory()->create(['content_type' => 'blk', 'slug' => 'block-aaaaaaaa']);

        $first = $generator->forBlock();
        $this->assertSame('block-aaaaaaaa', $first);
        $this->assertNotSame($first, $generator->forType($block, null));
    }

    public function test_a_page_slug_avoids_reserved_paths(): void
    {
        $page = ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        $this->assertSame('admin-2', $this->creator()->create($page, ['title' => 'Admin'], authorId: $this->authorId())->slug);
        $this->assertSame('admin-3', $this->creator()->create($page, ['title' => 'Admin'], authorId: $this->authorId())->slug);
    }

    public function test_a_page_slug_is_not_offered_as_search(): void
    {
        $page = ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        $this->assertSame('search-2', $this->creator()->create($page, ['title' => 'Search'], authorId: $this->authorId())->slug);
    }

    public function test_a_page_slug_avoids_a_content_type_slug_with_consecutive_suffixes(): void
    {
        ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);
        ContentType::create(['key' => 'news2', 'name' => 'News 2', 'slug' => 'news-2']);
        $page = ContentType::create(['key' => 'static', 'name' => 'Static', 'kind' => 'page']);

        $this->assertSame('news-3', $this->creator()->create($page, ['title' => 'News'], authorId: $this->authorId())->slug);
    }

    public function test_a_content_node_slug_ignores_type_slugs_and_reserved_paths(): void
    {
        $news = ContentType::create(['key' => 'news', 'name' => 'News', 'slug' => 'news']);

        $this->assertSame('news', $this->creator()->create($news, ['title' => 'News'], authorId: $this->authorId())->slug);
        $this->assertSame('admin', $this->creator()->create($news, ['title' => 'Admin'], authorId: $this->authorId())->slug);
    }
}
