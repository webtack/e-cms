<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Models\AppLanguage;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class ContentValueWriterTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private function writer(): ContentValueWriter
    {
        return app(ContentValueWriter::class);
    }

    private function assertInvalid(callable $call, string $key): void
    {
        try {
            $call();
            $this->fail('A ValidationException was expected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }

    public function test_values_default_to_the_base_language(): void
    {
        $node = $this->makeArticle();

        $this->assertSame('default', DB::table('content_values_string')->where('content_node_id', $node->id)->value('lang'));
    }

    public function test_rows_carry_the_type_and_uuid_of_the_node(): void
    {
        $node = $this->makeArticle();

        $row = DB::table('content_values_string')->where('content_node_id', $node->id)->first();
        $this->assertSame('article', $row->content_type);
        $this->assertSame($node->uuid, $row->content_node_uuid);
    }

    public function test_a_partial_update_keeps_other_fields(): void
    {
        $node = $this->makeArticle(['title' => 'Hello', 'visits' => 5]);

        $this->writer()->write($node, ['title' => 'Changed']);

        $this->assertSame('Changed', DB::table('content_values_string')->where('field_key', 'title')->value('value'));
        $this->assertSame(5, (int) DB::table('content_values_integer')->where('field_key', 'visits')->value('value'));
    }

    public function test_a_failed_validation_stores_nothing(): void
    {
        $node = $this->makeArticle(['title' => 'Hello', 'visits' => 5]);

        $this->assertInvalid(fn () => $this->writer()->write($node, ['title' => 'Changed', 'visits' => 'abc']), 'visits');

        $this->assertSame('Hello', DB::table('content_values_string')->where('field_key', 'title')->value('value'));
    }

    public function test_an_unknown_key_is_rejected(): void
    {
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => $this->writer()->write($node, ['nope' => 1]), 'nope');
    }

    public function test_required_fields_are_enforced_on_creation_in_the_base_language(): void
    {
        $this->articleType();

        $this->assertInvalid(fn () => $this->makeArticle(['visits' => 1]), 'title');
        $this->assertSame(0, ContentNode::count());
    }

    public function test_a_required_field_cannot_be_cleared(): void
    {
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => $this->writer()->write($node, ['title' => null]), 'title');
    }

    public function test_type_errors_and_bounds_are_reported_per_field(): void
    {
        $node = $this->makeArticle();
        $node->contentType->fields()->where('key', 'visits')->first()->update(['settings' => ['min' => 1, 'max' => 10]]);

        $this->assertInvalid(fn () => $this->writer()->write($node, ['visits' => 'abc']), 'visits');
        $this->assertInvalid(fn () => $this->writer()->write($node, ['visits' => 11]), 'visits');
        $this->assertInvalid(fn () => $this->writer()->write($node, ['visits' => 0]), 'visits');
        $this->writer()->write($node, ['visits' => 10]);
    }

    public function test_too_many_decimal_places_are_rejected_not_rounded(): void
    {
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => $this->writer()->write($node, ['price' => '1.1234567']), 'price');
        $this->assertSame(0, DB::table('content_values_decimal')->count());
    }

    public function test_multiple_values_keep_their_order_via_weight(): void
    {
        $node = $this->makeArticle(['title' => 'Hello', 'tags' => ['b', 'a', 'c']]);

        $rows = DB::table('content_values_string')->where('field_key', 'tags')->orderBy('weight')->get();
        $this->assertSame(['b', 'a', 'c'], $rows->pluck('value')->all());
        $this->assertSame([0, 1, 2], $rows->pluck('weight')->map(fn ($w) => (int) $w)->all());

        $this->writer()->write($node, ['tags' => ['x']]);
        $this->assertSame(1, DB::table('content_values_string')->where('field_key', 'tags')->count());
    }

    public function test_a_list_in_a_single_field_is_rejected(): void
    {
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => $this->writer()->write($node, ['visits' => [1, 2]]), 'visits');
    }

    public function test_null_or_an_empty_list_clears_a_field(): void
    {
        $node = $this->makeArticle(['title' => 'Hello', 'visits' => 3, 'tags' => ['a']]);

        $this->writer()->write($node, ['visits' => null, 'tags' => []]);

        $this->assertSame(0, DB::table('content_values_integer')->count());
        $this->assertSame(0, DB::table('content_values_string')->where('field_key', 'tags')->count());
    }

    public function test_translations_are_independent_rows(): void
    {
        $this->enableLanguages('en');
        $node = $this->makeArticle(['title' => 'Привіт']);

        $this->writer()->write($node, ['title' => 'Hello'], 'en');

        $rows = DB::table('content_values_string')->where('field_key', 'title')->orderBy('lang')->pluck('value', 'lang')->all();
        $this->assertSame(['default' => 'Привіт', 'en' => 'Hello'], $rows);
    }

    public function test_a_language_code_is_normalised_when_writing(): void
    {
        $this->enableLanguages('pt-BR');
        $node = $this->makeArticle();

        $this->writer()->write($node, ['title' => 'Olá'], 'pt-br');

        $this->assertSame(1, DB::table('content_values_string')->where('lang', 'pt-BR')->count());
    }

    public function test_a_non_translatable_field_rejects_another_language(): void
    {
        $this->enableLanguages('en');
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => $this->writer()->write($node, ['visits' => 1], 'en'), 'visits');
    }

    public function test_unknown_inactive_or_disabled_languages_are_rejected(): void
    {
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => $this->writer()->write($node, ['title' => 'x'], 'en'), 'lang');

        $this->enableLanguages('en');
        $this->assertInvalid(fn () => $this->writer()->write($node, ['title' => 'x'], 'fr'), 'lang');

        AppLanguage::factory()->create(['code' => 'de']);
        $this->assertInvalid(fn () => $this->writer()->write($node, ['title' => 'x'], 'de'), 'lang');
    }

    public function test_required_is_not_enforced_for_other_languages(): void
    {
        $this->enableLanguages('en');
        $node = $this->makeArticle();

        $this->writer()->write($node, ['body' => 'Only body'], 'en');

        $this->assertSame(0, DB::table('content_values_string')->where('lang', 'en')->count());
    }

    public function test_the_type_comes_from_the_node_not_from_input(): void
    {
        ContentType::create(['key' => 'product', 'name' => 'Product']);
        $node = $this->makeArticle();

        $this->assertInvalid(fn () => $this->writer()->write($node, ['content_type' => 'product']), 'content_type');
    }

    public function test_a_multiple_field_respects_max_items(): void
    {
        $this->articleType();
        ContentField::where('key', 'tags')->update(['max_items' => 3]);
        $node = $this->makeArticle();

        $this->writer()->write($node, ['tags' => ['a', 'b', 'c']]);
        $this->assertSame(3, DB::table('content_values_string')->where('content_node_id', $node->id)->where('field_key', 'tags')->count());

        $this->assertInvalid(fn () => $this->writer()->write($node, ['tags' => ['a', 'b', 'c', 'd']]), 'tags');
        $this->assertSame(3, DB::table('content_values_string')->where('content_node_id', $node->id)->where('field_key', 'tags')->count());
    }

    public function test_a_multiple_field_without_a_limit_takes_any_count(): void
    {
        $node = $this->makeArticle();

        $this->writer()->write($node, ['tags' => array_map('strval', range(1, 20))]);

        $this->assertSame(20, DB::table('content_values_string')->where('content_node_id', $node->id)->where('field_key', 'tags')->count());
    }

    public function test_empty_elements_do_not_count_against_max_items(): void
    {
        $this->articleType();
        ContentField::where('key', 'tags')->update(['max_items' => 2]);
        $node = $this->makeArticle();

        $this->writer()->write($node, ['tags' => ['a', 'b', '']]);

        $this->assertSame(2, DB::table('content_values_string')->where('content_node_id', $node->id)->where('field_key', 'tags')->count());
    }
}
