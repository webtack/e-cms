<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentNodeQuery;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\NodeQueryParams;
use Webtack\Ecms\Http\Requests\Admin\StoreContentFieldRequest;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class NodeRefFieldTypeTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private ContentNode $news;

    private ContentNode $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->articleType();
        $news = ContentType::create(['key' => 'news', 'name' => 'News']);
        $this->field('related', 'news');
        $this->field('relateds', 'news', ['is_multiple' => true]);
        $this->field('translated_related', 'news', ['is_translatable' => true]);
        $this->news = app(ContentNodeCreator::class)->create($news, ['title' => 'First'], authorId: $this->authorId());
        $this->other = $this->makeArticle(['title' => 'Other']);
    }

    /** @param array<string, mixed> $extra */
    private function field(string $key, string $target, array $extra = []): ContentField
    {
        return ContentField::create(['content_type' => 'article', 'key' => $key, 'name' => ucfirst($key), 'type' => 'node_ref', 'settings' => ['content_type' => $target], ...$extra]);
    }

    private function write(ContentNode $node, mixed $value, string $key = 'related'): void
    {
        app(ContentValueWriter::class)->write($node, [$key => $value]);
    }

    private function read(ContentNode $node, string $key = 'related'): mixed
    {
        return app(ContentValueReader::class)->load([$node], [$key])[$node->id][$key];
    }

    private function assertInvalid(callable $call, string $key = ''): void
    {
        try {
            $call();
            $this->fail('A ValidationException was expected.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($key, implode(',', array_keys($e->errors())));
        }
    }

    public function test_the_uuid_of_a_node_of_the_chosen_type_is_stored_lower_case(): void
    {
        $node = $this->makeArticle();

        $this->write($node, strtoupper($this->news->uuid));

        $this->assertSame($this->news->uuid, $this->read($node));
        $this->assertDatabaseHas('content_values_string', ['field_key' => 'related', 'value' => $this->news->uuid]);
    }

    public function test_a_node_of_another_type_is_rejected(): void
    {
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => $this->write($node, $this->other->uuid), 'related');
        $this->assertNull($this->read($node));
    }

    public function test_an_unknown_node_or_a_non_uuid_is_rejected(): void
    {
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => $this->write($node, '0190a1b2-0000-7000-8000-000000000000'), 'related');
        $this->assertInvalid(fn () => $this->write($node, 'not-a-uuid'), 'related');
    }

    public function test_an_empty_value_clears_the_field(): void
    {
        $node = $this->makeArticle();
        $this->write($node, $this->news->uuid);

        $this->write($node, '');

        $this->assertNull($this->read($node));
    }

    public function test_the_content_type_is_required_existing_and_the_only_setting(): void
    {
        foreach ([[], ['content_type' => ''], ['content_type' => 'nope'], ['content_type' => 'news', 'extra' => 1], ['kind' => 'block']] as $i => $settings) {
            $this->assertInvalid(fn () => ContentField::create(['content_type' => 'article', 'key' => "bad_{$i}", 'name' => 'Bad', 'type' => 'node_ref', 'settings' => $settings]), 'settings.content_type');
        }
    }

    public function test_the_content_type_of_a_field_with_values_is_fixed(): void
    {
        $field = ContentField::query()->where('key', 'related')->firstOrFail();
        $this->write($this->makeArticle(), $this->news->uuid);

        $this->assertInvalid(fn () => $field->fresh()->update(['settings' => ['content_type' => 'article']]), 'settings.content_type');
        $this->assertSame('news', $field->fresh()->settings['content_type']);
    }

    public function test_a_multiple_field_keeps_the_order(): void
    {
        $second = app(ContentNodeCreator::class)->create(ContentType::findByKey('news'), ['title' => 'Second'], authorId: $this->authorId());
        $node = $this->makeArticle();

        $this->write($node, [$second->uuid, $this->news->uuid], 'relateds');

        $this->assertSame([$second->uuid, $this->news->uuid], $this->read($node, 'relateds'));
    }

    public function test_a_translatable_field_keeps_each_language(): void
    {
        $this->enableLanguages('en', 'uk');
        $second = app(ContentNodeCreator::class)->create(ContentType::findByKey('news'), ['title' => 'Second'], authorId: $this->authorId());
        $node = $this->makeArticle();

        $writer = app(ContentValueWriter::class);
        $writer->write($node, ['translated_related' => $this->news->uuid], 'en');
        $writer->write($node, ['translated_related' => $second->uuid], 'uk');

        $read = fn (string $lang) => app(ContentValueReader::class)->load([$node], ['translated_related'], $lang)[$node->id]['translated_related'];
        $this->assertSame($this->news->uuid, $read('en'));
        $this->assertSame($second->uuid, $read('uk'));
    }

    public function test_filters_by_equality_and_set(): void
    {
        $second = app(ContentNodeCreator::class)->create(ContentType::findByKey('news'), ['title' => 'Second'], authorId: $this->authorId());
        $one = $this->makeArticle(['title' => 'One']);
        $two = $this->makeArticle(['title' => 'Two']);
        $this->makeArticle(['title' => 'Three']);
        $this->write($one, $this->news->uuid);
        $this->write($two, $second->uuid);

        $titles = fn (string $operator, mixed $value) => collect(app(ContentNodeQuery::class)->paginate(
            ContentType::findByKey('article'),
            new NodeQueryParams(fields: ['title'], filters: [['field' => 'related', 'operator' => $operator, 'value' => $value]]),
        )->items())->map(fn (ContentNode $item) => $item->values['title'])->all();

        $this->assertSame(['One'], $titles('eq', $this->news->uuid));
        $this->assertSame(['One', 'Two'], $titles('in', [$this->news->uuid, $second->uuid]));
    }

    public function test_a_filter_by_a_non_uuid_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(ContentNodeQuery::class)->paginate(
            ContentType::findByKey('article'),
            new NodeQueryParams(filters: [['field' => 'related', 'operator' => 'eq', 'value' => 'nope']]),
        );
    }

    public function test_the_form_requests_take_only_the_content_type(): void
    {
        $rules = StoreContentFieldRequest::settingsRules('node_ref');

        $this->assertArrayHasKey('settings.content_type', $rules);
        $this->assertSame(['required', 'array:content_type,labels'], $rules['settings']);
    }
}
