<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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

class TypeRefFieldTypeTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->articleType();
        ContentType::create(['key' => 'hero', 'name' => 'Hero', 'kind' => 'block']);
        ContentType::create(['key' => 'banner', 'name' => 'Banner', 'kind' => 'block']);
        ContentType::create(['key' => 'home', 'name' => 'Home', 'kind' => 'page']);
        $this->field('source', ['kind' => 'block']);
        $this->field('sources', ['kind' => 'block'], ['is_multiple' => true]);
        $this->field('translated_source', ['kind' => 'block'], ['is_translatable' => true]);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $extra
     */
    private function field(string $key, array $settings, array $extra = []): ContentField
    {
        return ContentField::create(['content_type' => 'article', 'key' => $key, 'name' => ucfirst($key), 'type' => 'type_ref', 'settings' => $settings, ...$extra]);
    }

    private function write(ContentNode $node, mixed $value, string $key = 'source'): void
    {
        app(ContentValueWriter::class)->write($node, [$key => $value]);
    }

    private function read(ContentNode $node, string $key = 'source'): mixed
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

    public function test_the_key_of_a_type_of_the_chosen_kind_is_stored_and_read_back(): void
    {
        $node = $this->makeArticle();

        $this->write($node, 'hero');

        $this->assertSame('hero', $this->read($node));
        $this->assertDatabaseHas('content_values_string', ['field_key' => 'source', 'value' => 'hero']);
    }

    public function test_a_type_of_another_kind_is_rejected(): void
    {
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => $this->write($node, 'home'), 'source');
        $this->assertInvalid(fn () => $this->write($node, 'article'), 'source');
        $this->assertNull($this->read($node));
    }

    public function test_an_unknown_type_is_rejected(): void
    {
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => $this->write($node, 'nope'), 'source');
    }

    public function test_an_empty_value_clears_the_field(): void
    {
        $node = $this->makeArticle();
        $this->write($node, 'hero');

        $this->write($node, '');

        $this->assertNull($this->read($node));
    }

    public function test_the_kind_is_required_and_the_only_setting(): void
    {
        foreach ([null, [], ['kind' => ''], ['kind' => 'nope'], ['kind' => 'block', 'extra' => 1], ['content_type' => 'article']] as $i => $settings) {
            $this->assertInvalid(fn () => $this->field("bad_{$i}", $settings ?? []), 'settings.kind');
        }
    }

    public function test_the_kind_of_a_field_with_values_is_fixed(): void
    {
        $field = ContentField::query()->where('key', 'source')->firstOrFail();
        $field->update(['settings' => ['kind' => 'page']]);
        $this->assertSame('page', $field->fresh()->settings['kind']);

        $field->update(['settings' => ['kind' => 'block']]);
        $this->write($this->makeArticle(), 'hero');

        $this->assertInvalid(fn () => $field->fresh()->update(['settings' => ['kind' => 'page']]), 'settings.kind');
        $this->assertSame('block', $field->fresh()->settings['kind']);
    }

    public function test_a_multiple_field_keeps_the_order(): void
    {
        $node = $this->makeArticle();

        $this->write($node, ['hero', 'banner'], 'sources');
        $this->assertSame(['hero', 'banner'], $this->read($node, 'sources'));

        $this->write($node, ['banner', 'hero'], 'sources');
        $this->assertSame(['banner', 'hero'], $this->read($node, 'sources'));
    }

    public function test_a_translatable_field_keeps_each_language(): void
    {
        $this->enableLanguages('en', 'uk');
        $node = $this->makeArticle();

        $writer = app(ContentValueWriter::class);
        $writer->write($node, ['translated_source' => 'hero'], 'en');
        $writer->write($node, ['translated_source' => 'banner'], 'uk');

        $read = fn (string $lang) => app(ContentValueReader::class)->load([$node], ['translated_source'], $lang)[$node->id]['translated_source'];
        $this->assertSame('hero', $read('en'));
        $this->assertSame('banner', $read('uk'));
    }

    public function test_filters_by_equality_and_set(): void
    {
        $one = $this->makeArticle(['title' => 'One']);
        $two = $this->makeArticle(['title' => 'Two']);
        $this->makeArticle(['title' => 'Three']);
        $this->write($one, 'hero');
        $this->write($two, 'banner');

        $titles = fn (string $operator, mixed $value) => collect(app(ContentNodeQuery::class)->paginate(
            $one->contentType,
            new NodeQueryParams(fields: ['title'], filters: [['field' => 'source', 'operator' => $operator, 'value' => $value]]),
        )->items())->map(fn (ContentNode $item) => $item->values['title'])->all();

        $this->assertSame(['One'], $titles('eq', 'hero'));
        $this->assertSame(['One', 'Two'], $titles('in', ['hero', 'banner']));
        $this->assertSame([], $titles('eq', 'home'));
    }

    public function test_a_filter_by_an_unknown_type_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(ContentNodeQuery::class)->paginate(
            ContentType::findByKey('article'),
            new NodeQueryParams(filters: [['field' => 'source', 'operator' => 'eq', 'value' => 'nope']]),
        );
    }

    public function test_the_form_requests_take_only_the_kind(): void
    {
        $rules = StoreContentFieldRequest::settingsRules('type_ref');

        $this->assertArrayHasKey('settings.kind', $rules);
        $this->assertSame(['required', 'array:kind,labels'], $rules['settings']);
    }
}
