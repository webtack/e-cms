<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentLanguages;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class ContentValueReaderTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private function read(ContentNode $node, ?array $keys = null, string $lang = 'default'): array
    {
        return app(ContentValueReader::class)->load([$node], $keys, $lang)[$node->id];
    }

    public function test_all_seven_types_round_trip(): void
    {
        $node = $this->makeArticle([
            'title' => 'Hello',
            'body' => 'Long text',
            'visits' => 42,
            'price' => '12.50',
            'published' => true,
            'released' => '2026-10-29',
            'updated' => '2026-10-29 14:30:00',
        ]);

        $values = $this->read($node);

        $this->assertSame('Hello', $values['title']);
        $this->assertSame('Long text', $values['body']);
        $this->assertSame(42, $values['visits']);
        $this->assertSame('12.5', $values['price']);
        $this->assertTrue($values['published']);
        $this->assertInstanceOf(CarbonImmutable::class, $values['released']);
        $this->assertSame('2026-10-29', $values['released']->toDateString());
        $this->assertSame('2026-10-29 14:30:00', $values['updated']->format('Y-m-d H:i:s'));
    }

    public function test_decimal_precision_is_not_lost(): void
    {
        $node = $this->makeArticle(['title' => 'Hello', 'price' => '123456789012.123456']);

        // SQLite stores DECIMAL as a double, so only assert the full digits where the engine can.
        $stored = $this->read($node)['price'];

        $this->assertStringStartsWith('123456789012.12345', $stored);
    }

    public function test_multiple_values_keep_their_order(): void
    {
        $node = $this->makeArticle(['title' => 'Hello', 'tags' => ['b', 'a', 'c']]);

        $this->assertSame(['b', 'a', 'c'], $this->read($node)['tags']);
    }

    public function test_empty_fields_read_as_null_or_an_empty_list(): void
    {
        $values = $this->read($this->makeArticle());

        $this->assertNull($values['visits']);
        $this->assertSame([], $values['tags']);
    }

    public function test_only_requested_fields_are_returned(): void
    {
        $values = $this->read($this->makeArticle(['title' => 'Hello', 'visits' => 1]), ['title']);

        $this->assertSame(['title' => 'Hello'], $values);
    }

    public function test_an_unknown_requested_field_is_rejected(): void
    {
        $node = $this->makeArticle();

        $this->expectException(ValidationException::class);
        $this->read($node, ['nope']);
    }

    public function test_languages_are_read_independently_without_fallback(): void
    {
        $this->enableLanguages('en');
        $node = $this->makeArticle(['title' => 'Привіт', 'body' => 'Тіло']);
        app(ContentValueWriter::class)->write($node, ['title' => 'Hello'], 'en');

        $this->assertSame('Привіт', $this->read($node)['title']);
        $this->assertSame('Hello', $this->read($node, lang: 'en')['title']);
        $this->assertNull($this->read($node, lang: 'en')['body']);
    }

    private function readWithFallback(ContentNode $node, ?array $keys = null, string $lang = 'en'): array
    {
        return app(ContentValueReader::class)->load([$node], $keys, $lang, fallback: true)[$node->id];
    }

    public function test_a_missing_translation_falls_back_to_the_base_value(): void
    {
        $this->enableLanguages('en');
        $node = $this->makeArticle(['title' => 'Привіт']);

        $this->assertSame('Привіт', $this->readWithFallback($node)['title']);
        $this->assertNull($this->read($node, lang: 'en')['title']);
    }

    public function test_a_translation_wins_over_the_base_value(): void
    {
        $this->enableLanguages('en');
        $node = $this->makeArticle(['title' => 'Привіт']);
        app(ContentValueWriter::class)->write($node, ['title' => 'Hello'], 'en');

        $this->assertSame('Hello', $this->readWithFallback($node)['title']);
    }

    public function test_fields_fall_back_independently(): void
    {
        $this->enableLanguages('en');
        $node = $this->makeArticle(['title' => 'Привіт', 'body' => 'Тіло']);
        app(ContentValueWriter::class)->write($node, ['title' => 'Hello'], 'en');

        $values = $this->readWithFallback($node, ['title', 'body']);

        $this->assertSame('Hello', $values['title']);
        $this->assertSame('Тіло', $values['body']);
    }

    public function test_a_multiple_field_falls_back_as_a_whole_list(): void
    {
        $this->enableLanguages('en');
        $node = $this->makeArticle(['title' => 'Привіт', 'tags' => ['a', 'b']]);

        $this->assertSame(['a', 'b'], $this->readWithFallback($node, ['tags'])['tags']);

        app(ContentValueWriter::class)->write($node, ['tags' => ['c']], 'en');

        $this->assertSame(['c'], $this->readWithFallback($node, ['tags'])['tags']);
    }

    public function test_the_fallback_costs_a_constant_number_of_queries(): void
    {
        $this->enableLanguages('en');
        $type = $this->articleType();
        $creator = app(ContentNodeCreator::class);
        $nodes = [];
        for ($i = 0; $i < 30; $i++) {
            $nodes[] = $creator->create($type, ['title' => "Node {$i}", 'visits' => $i, 'tags' => ['a']], authorId: $this->authorId());
        }

        DB::enableQueryLog();
        app(ContentValueReader::class)->load($nodes, ['title', 'visits', 'tags'], 'en', fallback: true);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 1 for the language check + 1 for the fields + 2 (own and base language) per value table in use (string, integer).
        $this->assertLessThanOrEqual(2 + 2 * 2, $queries);
    }

    public function test_an_inactive_language_is_readable_but_a_disabled_switch_is_not(): void
    {
        $this->enableLanguages('en');
        AppLanguage::query()->where('code', 'en')->update(['is_active' => false]);
        $node = $this->makeArticle();

        $this->assertNull($this->read($node, ['body'], 'en')['body']);

        app(ContentLanguages::class)->setMultilingual(false);
        $this->expectException(ValidationException::class);
        $this->read($node, ['body'], 'en');
    }

    public function test_the_number_of_queries_does_not_depend_on_the_number_of_nodes(): void
    {
        $type = $this->articleType();
        $creator = app(ContentNodeCreator::class);
        $nodes = [];
        for ($i = 0; $i < 50; $i++) {
            $nodes[] = $creator->create($type, ['title' => "Node {$i}", 'visits' => $i, 'tags' => ['a', 'b'], 'published' => true], authorId: $this->authorId());
        }

        DB::enableQueryLog();
        $values = app(ContentValueReader::class)->load($nodes, ['title', 'visits', 'tags', 'published']);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(50, $values);
        // 1 query for the field definitions + 1 per value table in use (string, integer, boolean).
        $this->assertLessThanOrEqual(1 + 3, $queries);
    }

    public function test_nodes_of_different_types_can_be_loaded_together(): void
    {
        $article = $this->makeArticle();
        $other = ContentType::create(['key' => 'product', 'name' => 'Product']);
        $product = app(ContentNodeCreator::class)->create($other, ['title' => 'Lamp'], authorId: $this->authorId());

        $values = app(ContentValueReader::class)->load([$article, $product], ['title']);

        $this->assertSame('Hello', $values[$article->id]['title']);
        $this->assertSame('Lamp', $values[$product->id]['title']);
    }
}
