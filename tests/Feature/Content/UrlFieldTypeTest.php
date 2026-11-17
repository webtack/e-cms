<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentNodeQuery;
use Webtack\Ecms\Content\ContentValueReader;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\FieldTypes\UrlType;
use Webtack\Ecms\Content\NodeQueryParams;
use Webtack\Ecms\Http\Requests\Admin\StoreContentFieldRequest;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class UrlFieldTypeTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->articleType();
        $this->field('link');
        $this->field('links', ['is_multiple' => true]);
        $this->field('translated_link', ['is_translatable' => true]);
    }

    /** @param array<string, mixed> $extra */
    private function field(string $key, array $extra = []): void
    {
        ContentField::create(['content_type' => 'article', 'key' => $key, 'name' => ucfirst($key), 'type' => 'url', ...$extra]);
    }

    private function write(ContentNode $node, mixed $value, string $key = 'link'): void
    {
        app(ContentValueWriter::class)->write($node, [$key => $value]);
    }

    private function read(ContentNode $node, string $key = 'link'): mixed
    {
        return app(ContentValueReader::class)->load([$node], [$key])[$node->id][$key];
    }

    private function assertInvalid(callable $call): void
    {
        try {
            $call();
            $this->fail('A ValidationException was expected.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_an_absolute_link_and_title_are_stored_and_read_back(): void
    {
        $node = $this->makeArticle();

        $this->write($node, ['url' => 'https://example.com/page?a=1#top', 'title' => 'Example']);

        $this->assertSame(['url' => 'https://example.com/page?a=1#top', 'title' => 'Example'], $this->read($node));
        $this->assertSame('https://example.com/page?a=1#top', DB::table('content_values_string')->where('field_key', 'link')->value('value'));
    }

    public function test_accepted_links_are_kept_as_entered(): void
    {
        foreach (['/about', './team', '../docs/a.pdf', '#contacts', '?page=2', 'http://example.com', 'mailto:info@example.com', 'tel:+380441234567'] as $link) {
            $node = $this->makeArticle();
            $this->write($node, $link);

            $this->assertSame($link, $this->read($node)['url'], $link);
        }
    }

    public function test_a_link_without_a_title_reads_a_null_title(): void
    {
        $node = $this->makeArticle();

        $this->write($node, ['url' => '/about']);
        $this->assertSame(['url' => '/about', 'title' => null], $this->read($node));

        $this->write($node, ['url' => '/about', 'title' => '   ']);
        $this->assertSame(['url' => '/about', 'title' => null], $this->read($node));
    }

    public function test_spaces_around_the_link_are_trimmed(): void
    {
        $node = $this->makeArticle();

        $this->write($node, '  /about  ');

        $this->assertSame('/about', $this->read($node)['url']);
    }

    public function test_forbidden_or_malformed_links_are_rejected(): void
    {
        $node = $this->makeArticle();

        foreach ([
            'javascript:alert(1)', 'data:text/html,x', 'ftp://example.com/a', '//example.com/a', '/\\evil.com',
            '/about us', 'https://', 'mailto:', 'just text', 'example.com',
        ] as $text) {
            $this->assertInvalid(fn () => $this->write($node, $text));
        }

        $this->assertNull($this->read($node));
    }

    public function test_length_limits(): void
    {
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => $this->write($node, '/'.str_repeat('a', 512)));
        $this->assertInvalid(fn () => $this->write($node, ['url' => '/a', 'title' => str_repeat('a', 256)]));
        $this->write($node, ['url' => '/'.str_repeat('a', 511), 'title' => str_repeat('a', 255)]);
        $this->assertSame(512, mb_strlen($this->read($node)['url']));
    }

    public function test_a_required_field_needs_a_link(): void
    {
        $node = $this->makeArticle();
        $this->field('required_link', ['is_required' => true]);

        $this->assertInvalid(fn () => $this->write($node, ['url' => '', 'title' => 'Only a title'], 'required_link'));
        $this->assertInvalid(fn () => $this->write($node, null, 'required_link'));
    }

    public function test_settings_accept_only_translated_labels(): void
    {
        $this->assertSame(['settings' => ['nullable', 'array:labels']], array_intersect_key(StoreContentFieldRequest::settingsRules('url'), ['settings' => 1]));
    }

    public function test_a_multiple_field_keeps_order_and_title_of_each_link(): void
    {
        $node = $this->makeArticle();

        $this->write($node, [['url' => '/a', 'title' => 'A'], ['url' => '/b']], 'links');

        $links = $this->read($node, 'links');
        $this->assertSame(['/a', '/b'], array_column($links, 'url'));
        $this->assertSame(['A', null], array_column($links, 'title'));
    }

    public function test_a_translatable_field_keeps_each_language(): void
    {
        $this->enableLanguages('en', 'uk');
        $node = $this->makeArticle();

        $writer = app(ContentValueWriter::class);
        $writer->write($node, ['translated_link' => ['url' => '/en', 'title' => 'EN']], 'en');
        $writer->write($node, ['translated_link' => ['url' => '/uk', 'title' => 'UK']], 'uk');

        $read = fn (string $lang) => app(ContentValueReader::class)->load([$node], ['translated_link'], $lang)[$node->id]['translated_link'];
        $this->assertSame(['url' => '/en', 'title' => 'EN'], $read('en'));
        $this->assertSame(['url' => '/uk', 'title' => 'UK'], $read('uk'));
    }

    public function test_filters_by_equality_and_containment(): void
    {
        $one = $this->makeArticle(['title' => 'One']);
        $two = $this->makeArticle(['title' => 'Two']);
        $this->write($one, 'https://example.com/a');
        $this->write($two, '/local');

        $titles = fn (string $operator, mixed $value) => collect(app(ContentNodeQuery::class)->paginate(
            $one->contentType,
            new NodeQueryParams(fields: ['title'], filters: [['field' => 'link', 'operator' => $operator, 'value' => $value]]),
        )->items())->map(fn ($item) => $item->values['title'] ?? $item['values']['title'] ?? null)->all();

        $this->assertSame(['One'], $titles('contains', 'example.com'));
        $this->assertSame(['Two'], $titles('eq', '/local'));
        $this->assertSame(['One'], $titles('in', ['https://example.com/a']));
    }

    public function test_saving_does_not_use_the_network_and_needs_no_new_table(): void
    {
        Http::fake();
        $node = $this->makeArticle();

        $this->write($node, ['url' => 'https://example.com', 'title' => 'x']);

        Http::assertNothingSent();
        $this->assertFalse(Schema::hasTable('content_values_url'));
        $this->assertSame('content_values_string', app(UrlType::class)->table());
    }
}
