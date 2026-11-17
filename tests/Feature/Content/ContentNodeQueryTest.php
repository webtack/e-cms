<?php

namespace Webtack\Ecms\Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\ContentNodeCreator;
use Webtack\Ecms\Content\ContentNodeQuery;
use Webtack\Ecms\Content\ContentValueWriter;
use Webtack\Ecms\Content\NodeQueryParams;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;
use Webtack\Ecms\Tests\Feature\Content\Concerns\BuildsArticleType;
use Webtack\Ecms\Tests\TestCase;

class ContentNodeQueryTest extends TestCase
{
    use BuildsArticleType, RefreshDatabase;

    private ContentType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = $this->articleType();
    }

    /** @param array<string, mixed> $values */
    private function node(array $values): ContentNode
    {
        return app(ContentNodeCreator::class)->create($this->type, $values, authorId: $this->authorId());
    }

    /** @return array<int, mixed> */
    private function titles(NodeQueryParams $params): array
    {
        return collect(app(ContentNodeQuery::class)->paginate($this->type, $params)->items())
            ->map(fn (ContentNode $n) => $n->values['title'] ?? null)->all();
    }

    private function assertInvalid(NodeQueryParams $params, string $key): void
    {
        try {
            app(ContentNodeQuery::class)->paginate($this->type, $params);
            $this->fail('A ValidationException was expected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }

    public function test_only_the_visible_fields_are_returned(): void
    {
        $this->node(['title' => 'A', 'visits' => 1, 'price' => '5']);

        $page = app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams(fields: ['title', 'price']));

        $this->assertSame(['title' => 'A', 'price' => '5'], $page->items()[0]->values);
    }

    public function test_list_settings_are_used_when_nothing_is_passed(): void
    {
        $this->type->update(['settings' => ['list' => [
            'visible' => ['title', 'visits'],
            'sort' => ['field' => 'visits', 'direction' => 'desc'],
        ]]]);
        $this->node(['title' => 'Low', 'visits' => 1]);
        $this->node(['title' => 'High', 'visits' => 9]);

        $page = app(ContentNodeQuery::class)->paginate($this->type->fresh(), new NodeQueryParams);

        $this->assertSame(['High', 'Low'], collect($page->items())->map(fn ($n) => $n->values['title'])->all());
        $this->assertSame(['title', 'visits'], array_keys($page->items()[0]->values));
    }

    public function test_pagination(): void
    {
        foreach (range(1, 25) as $i) {
            $this->node(['title' => sprintf('N%02d', $i)]);
        }

        $page = app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams(page: 2, perPage: 10, sort: ['field' => 'title']));

        $this->assertSame(25, $page->total());
        $this->assertSame(range(11, 20), collect($page->items())->map(fn ($n) => (int) substr($n->values['title'], 1))->all());
    }

    public function test_a_translation_language_selects_translated_values_with_null_gaps(): void
    {
        $this->enableLanguages('en');
        $translated = $this->node(['title' => 'Привіт']);
        $this->node(['title' => 'Без перекладу']);
        app(ContentValueWriter::class)->write($translated, ['title' => 'Hello'], 'en');

        $page = app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams(lang: 'en'));

        $this->assertSame(['Hello', null], collect($page->items())->map(fn ($n) => $n->values['title'])->all());
    }

    public function test_an_unavailable_language_is_rejected(): void
    {
        $this->assertInvalid(new NodeQueryParams(lang: 'en'), 'lang');
    }

    public function test_only_nodes_of_the_type_are_returned_and_foreign_fields_rejected(): void
    {
        $product = ContentType::create(['key' => 'product', 'name' => 'Product']);
        ContentField::create(['content_type' => 'product', 'key' => 'sku', 'name' => 'SKU', 'type' => 'string']);
        app(ContentNodeCreator::class)->create($product, ['title' => 'Lamp'], authorId: $this->authorId());
        $this->node(['title' => 'Article']);

        $this->assertSame(['Article'], $this->titles(new NodeQueryParams));
        $this->assertInvalid(new NodeQueryParams(fields: ['sku']), 'fields');
        $this->assertInvalid(new NodeQueryParams(filters: [['field' => 'sku', 'operator' => 'eq', 'value' => 'x']]), 'filters.0.field');
    }

    public function test_numeric_range_filter_is_inclusive(): void
    {
        foreach ([5, 10, 15, 20, 25] as $visits) {
            $this->node(['title' => "V{$visits}", 'visits' => $visits]);
        }

        $titles = $this->titles(new NodeQueryParams(
            filters: [['field' => 'visits', 'operator' => 'between', 'value' => [10, 20]]],
            sort: ['field' => 'visits'],
        ));

        $this->assertSame(['V10', 'V15', 'V20'], $titles);
    }

    public function test_comparison_operators(): void
    {
        foreach ([1, 2, 3] as $visits) {
            $this->node(['title' => "V{$visits}", 'visits' => $visits]);
        }

        $by = fn (string $op, int $v) => $this->titles(new NodeQueryParams(
            filters: [['field' => 'visits', 'operator' => $op, 'value' => $v]],
            sort: ['field' => 'visits'],
        ));

        $this->assertSame(['V2'], $by('eq', 2));
        $this->assertSame(['V3'], $by('gt', 2));
        $this->assertSame(['V2', 'V3'], $by('gte', 2));
        $this->assertSame(['V1'], $by('lt', 2));
        $this->assertSame(['V1', 'V2'], $by('lte', 2));
    }

    public function test_a_multiple_field_matches_when_any_value_matches(): void
    {
        $this->node(['title' => 'Both', 'tags' => ['a', 'news']]);
        $this->node(['title' => 'Other', 'tags' => ['a', 'b']]);

        $titles = $this->titles(new NodeQueryParams(filters: [['field' => 'tags', 'operator' => 'eq', 'value' => 'news']]));

        $this->assertSame(['Both'], $titles);
    }

    public function test_the_in_operator_matches_any_listed_string_or_number(): void
    {
        $this->node(['title' => 'A', 'visits' => 1, 'note' => 'x']);
        $this->node(['title' => 'B', 'visits' => 2, 'note' => 'y']);
        $this->node(['title' => 'C', 'visits' => 3, 'note' => 'z']);

        $byNote = $this->titles(new NodeQueryParams(filters: [['field' => 'note', 'operator' => 'in', 'value' => ['x', 'z']]], sort: ['field' => 'title']));
        $byViews = $this->titles(new NodeQueryParams(filters: [['field' => 'visits', 'operator' => 'in', 'value' => [2, '3']]], sort: ['field' => 'title']));

        $this->assertSame(['A', 'C'], $byNote);
        $this->assertSame(['B', 'C'], $byViews);
    }

    public function test_the_in_operator_on_a_multiple_field_matches_any_value_in_the_list(): void
    {
        $this->node(['title' => 'Has a', 'tags' => ['a', 'q']]);
        $this->node(['title' => 'Has b', 'tags' => ['q', 'b']]);
        $this->node(['title' => 'Neither', 'tags' => ['q']]);

        $titles = $this->titles(new NodeQueryParams(filters: [['field' => 'tags', 'operator' => 'in', 'value' => ['a', 'b']]], sort: ['field' => 'title']));

        $this->assertSame(['Has a', 'Has b'], $titles);
    }

    public function test_the_in_operator_needs_a_non_empty_list_of_valid_values(): void
    {
        $this->assertInvalid(new NodeQueryParams(filters: [['field' => 'visits', 'operator' => 'in', 'value' => []]]), 'filters.0.value');
        $this->assertInvalid(new NodeQueryParams(filters: [['field' => 'visits', 'operator' => 'in', 'value' => 5]]), 'filters.0.value');
        $this->assertInvalid(new NodeQueryParams(filters: [['field' => 'visits', 'operator' => 'in', 'value' => [1, 'abc']]]), 'filters.0.value');
    }

    public function test_the_in_operator_is_rejected_for_boolean_youtube_and_file_fields(): void
    {
        foreach (['clip' => 'youtube', 'cover' => 'image'] as $key => $fieldType) {
            ContentField::create(['content_type' => 'article', 'key' => $key, 'name' => ucfirst($key), 'type' => $fieldType, 'is_required' => false]);
        }

        foreach (['published', 'clip', 'cover'] as $field) {
            $this->assertInvalid(new NodeQueryParams(filters: [['field' => $field, 'operator' => 'in', 'value' => ['a']]]), 'filters.0.operator');
        }
    }

    public function test_several_filters_on_one_multiple_field_may_match_different_values(): void
    {
        $this->node(['title' => 'Both', 'tags' => ['a', 'b']]);
        $this->node(['title' => 'Only a', 'tags' => ['a']]);

        $titles = $this->titles(new NodeQueryParams(filters: [
            ['field' => 'tags', 'operator' => 'eq', 'value' => 'a'],
            ['field' => 'tags', 'operator' => 'eq', 'value' => 'b'],
        ]));

        $this->assertSame(['Both'], $titles);
    }

    public function test_filters_are_combined_with_and(): void
    {
        $this->node(['title' => 'A', 'visits' => 5, 'published' => true]);
        $this->node(['title' => 'B', 'visits' => 5, 'published' => false]);
        $this->node(['title' => 'C', 'visits' => 9, 'published' => true]);

        $titles = $this->titles(new NodeQueryParams(filters: [
            ['field' => 'visits', 'operator' => 'eq', 'value' => 5],
            ['field' => 'published', 'operator' => 'eq', 'value' => true],
        ]));

        $this->assertSame(['A'], $titles);
    }

    public function test_date_filters(): void
    {
        $this->node(['title' => 'Old', 'released' => '2025-01-01']);
        $this->node(['title' => 'New', 'released' => '2026-06-01']);

        $titles = $this->titles(new NodeQueryParams(filters: [['field' => 'released', 'operator' => 'gte', 'value' => '2026-01-01']]));

        $this->assertSame(['New'], $titles);
    }

    public function test_filter_by_language_ignores_the_base_value(): void
    {
        $this->enableLanguages('en');
        $a = $this->node(['title' => 'Hello base']);
        $b = $this->node(['title' => 'Hello also base']);
        app(ContentValueWriter::class)->write($a, ['title' => 'Bye'], 'en');
        app(ContentValueWriter::class)->write($b, ['title' => 'Hello there'], 'en');

        $titles = $this->titles(new NodeQueryParams(lang: 'en', filters: [['field' => 'title', 'operator' => 'contains', 'value' => 'Hello']]));

        $this->assertSame(['Hello there'], $titles);
    }

    public function test_an_unsupported_operator_is_rejected(): void
    {
        $this->assertInvalid(new NodeQueryParams(filters: [['field' => 'published', 'operator' => 'contains', 'value' => 'x']]), 'filters.0.operator');
    }

    public function test_an_invalid_filter_value_is_rejected(): void
    {
        $this->assertInvalid(new NodeQueryParams(filters: [['field' => 'visits', 'operator' => 'eq', 'value' => 'abc']]), 'filters.0.value');
        $this->assertInvalid(new NodeQueryParams(filters: [['field' => 'visits', 'operator' => 'between', 'value' => [1]]]), 'filters.0.value');
    }

    public function test_the_number_of_filters_is_limited(): void
    {
        $filters = array_fill(0, 11, ['field' => 'visits', 'operator' => 'eq', 'value' => 1]);

        $this->assertInvalid(new NodeQueryParams(filters: $filters), 'filters');
    }

    public function test_search_over_several_fields(): void
    {
        $this->node(['title' => 'Desk lamp', 'body' => 'x']);
        $this->node(['title' => 'Chair', 'body' => 'a LAMP shade']);
        $this->node(['title' => 'Table', 'body' => 'none']);

        $titles = $this->titles(new NodeQueryParams(search: 'lamp', searchFields: ['title', 'body'], sort: ['field' => 'title']));

        $this->assertSame(['Chair', 'Desk lamp'], $titles);
    }

    public function test_search_defaults_to_the_list_settings_then_all_text_fields(): void
    {
        $this->node(['title' => 'Plain', 'body' => 'lamp']);
        $this->node(['title' => 'lamp', 'body' => 'x']);

        $this->assertCount(2, app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams(search: 'lamp'))->items());

        $this->type->update(['settings' => ['list' => ['search' => ['title']]]]);
        $this->assertSame(['lamp'], $this->titlesFor(new NodeQueryParams(search: 'lamp')));
    }

    /** @return array<int, mixed> */
    private function titlesFor(NodeQueryParams $params): array
    {
        return collect(app(ContentNodeQuery::class)->paginate($this->type->fresh(), $params)->items())
            ->map(fn ($n) => $n->values['title'])->all();
    }

    public function test_search_rejects_non_text_fields(): void
    {
        $this->assertInvalid(new NodeQueryParams(search: 'x', searchFields: ['visits']), 'searchFields');
    }

    public function test_like_wildcards_in_the_search_term_are_escaped(): void
    {
        $this->node(['title' => '100% sure']);
        $this->node(['title' => '100 percent']);
        $this->node(['title' => 'a_b']);
        $this->node(['title' => 'axb']);

        $this->assertSame(['100% sure'], $this->titles(new NodeQueryParams(search: '100%', searchFields: ['title'])));
        $this->assertSame(['a_b'], $this->titles(new NodeQueryParams(search: 'a_b', searchFields: ['title'])));
        $this->assertSame([], $this->titles(new NodeQueryParams(search: '%', searchFields: ['note'])));
    }

    public function test_default_search_also_matches_the_slug_case_insensitively(): void
    {
        $this->app->make(ContentNodeCreator::class)->create($this->type, ['title' => 'Unrelated'], 'summer-sale-2026', authorId: $this->authorId());
        $this->node(['title' => 'Other']);

        $this->assertSame(['Unrelated'], $this->titles(new NodeQueryParams(search: 'SUMMER-sale')));
    }

    public function test_slug_is_searched_even_when_the_list_settings_restrict_the_fields(): void
    {
        $this->type->update(['settings' => ['list' => ['search' => ['title']]]]);
        $this->app->make(ContentNodeCreator::class)->create($this->type, ['title' => 'Unrelated'], 'summer-sale-2026', authorId: $this->authorId());
        $this->node(['title' => 'Other']);

        $this->assertSame(['Unrelated'], $this->titlesFor(new NodeQueryParams(search: 'summer-sale')));
    }

    public function test_like_wildcards_are_literal_in_the_slug_search(): void
    {
        $creator = $this->app->make(ContentNodeCreator::class);
        $creator->create($this->type, ['title' => 'One'], 'a-b', authorId: $this->authorId());
        $creator->create($this->type, ['title' => 'Two'], 'axb', authorId: $this->authorId());

        // An unescaped `_` would match `a-b` and `axb`, an unescaped `%` would match everything.
        $this->assertSame([], $this->titles(new NodeQueryParams(search: 'a_b')));
        $this->assertSame([], $this->titles(new NodeQueryParams(search: '%')));
    }

    public function test_an_empty_search_field_list_in_the_settings_leaves_the_slug_search(): void
    {
        DB::table('content_types')->where('key', $this->type->key)->update(['settings' => json_encode(['list' => ['search' => []]])]);
        $this->app->make(ContentNodeCreator::class)->create($this->type, ['title' => 'Unrelated'], 'daily-hits', authorId: $this->authorId());
        $this->node(['title' => 'Other']);

        $this->assertSame(['Unrelated'], $this->titlesFor(new NodeQueryParams(search: 'daily')));
        // The title is not searched any more: only the slug is.
        $this->assertSame([], $this->titlesFor(new NodeQueryParams(search: 'Unrelated')));
    }

    public function test_explicit_search_fields_do_not_add_the_slug(): void
    {
        $this->app->make(ContentNodeCreator::class)->create($this->type, ['title' => 'Unrelated'], 'summer-sale-2026', authorId: $this->authorId());

        $this->assertSame([], $this->titles(new NodeQueryParams(search: 'summer-sale', searchFields: ['title'])));
        $this->assertSame([], $this->titles(new NodeQueryParams(search: 'summer-sale', searchFields: [])));
    }

    public function test_sorting_with_the_fallback_orders_an_untranslated_node_by_its_base_value(): void
    {
        $this->enableLanguages('en');
        $a = $this->node(['title' => 'Омега']);
        app(ContentValueWriter::class)->write($a, ['title' => 'Beta'], 'en');
        $this->node(['title' => 'Alpha']); // no English title: the base title stands in

        $sort = ['field' => 'title', 'direction' => 'asc'];

        $this->assertSame(['Alpha', 'Beta'], $this->titles(new NodeQueryParams(lang: 'en', sort: $sort, fallback: true)));
    }

    public function test_sorting_without_the_fallback_puts_untranslated_nodes_last(): void
    {
        $this->enableLanguages('en');
        $a = $this->node(['title' => 'Омега']);
        app(ContentValueWriter::class)->write($a, ['title' => 'Beta'], 'en');
        $this->node(['title' => 'Alpha']);

        $this->assertSame(['Beta', null], $this->titles(new NodeQueryParams(lang: 'en', sort: ['field' => 'title'])));
    }

    public function test_a_list_with_the_fallback_returns_base_values_for_blank_translations(): void
    {
        $this->enableLanguages('en');
        $this->node(['title' => 'Привіт']);

        $this->assertSame(['Привіт'], $this->titles(new NodeQueryParams(lang: 'en', fallback: true)));
    }

    public function test_numeric_sorting_is_numeric_not_lexicographic(): void
    {
        foreach ([9, 10, 100] as $visits) {
            $this->node(['title' => "V{$visits}", 'visits' => $visits]);
        }

        $this->assertSame(['V100', 'V10', 'V9'], $this->titles(new NodeQueryParams(sort: ['field' => 'visits', 'direction' => 'desc'])));
        $this->assertSame(['V9', 'V10', 'V100'], $this->titles(new NodeQueryParams(sort: ['field' => 'visits', 'direction' => 'asc'])));
    }

    public function test_date_and_boolean_sorting(): void
    {
        $this->node(['title' => 'Late', 'released' => '2026-12-01', 'published' => true]);
        $this->node(['title' => 'Early', 'released' => '2025-01-15', 'published' => false]);

        $this->assertSame(['Early', 'Late'], $this->titles(new NodeQueryParams(sort: ['field' => 'released'])));
        $this->assertSame(['Early', 'Late'], $this->titles(new NodeQueryParams(sort: ['field' => 'published'])));
    }

    public function test_nodes_without_a_value_come_last_in_both_directions(): void
    {
        $this->node(['title' => 'None']);
        $this->node(['title' => 'Low', 'visits' => 1]);
        $this->node(['title' => 'High', 'visits' => 9]);

        $this->assertSame(['Low', 'High', 'None'], $this->titles(new NodeQueryParams(sort: ['field' => 'visits', 'direction' => 'asc'])));
        $this->assertSame(['High', 'Low', 'None'], $this->titles(new NodeQueryParams(sort: ['field' => 'visits', 'direction' => 'desc'])));
    }

    public function test_ties_are_broken_by_node_id(): void
    {
        foreach (['A', 'B', 'C'] as $title) {
            $this->node(['title' => $title, 'visits' => 1]);
        }

        $this->assertSame(['A', 'B', 'C'], $this->titles(new NodeQueryParams(sort: ['field' => 'visits', 'direction' => 'desc'])));
    }

    public function test_sorting_by_a_translated_title(): void
    {
        $this->enableLanguages('en');
        $first = $this->node(['title' => 'А']);
        $second = $this->node(['title' => 'Б']);
        app(ContentValueWriter::class)->write($first, ['title' => 'Zebra'], 'en');
        app(ContentValueWriter::class)->write($second, ['title' => 'Apple'], 'en');

        $this->assertSame(['Apple', 'Zebra'], $this->titles(new NodeQueryParams(lang: 'en', sort: ['field' => 'title'])));
    }

    public function test_sorting_by_an_unknown_field_or_direction_is_rejected(): void
    {
        $this->assertInvalid(new NodeQueryParams(sort: ['field' => 'nope']), 'sort.field');
        $this->assertInvalid(new NodeQueryParams(sort: ['field' => 'visits', 'direction' => 'sideways']), 'sort.direction');
    }

    public function test_a_multiple_field_is_sorted_by_its_first_value_in_both_directions(): void
    {
        $this->node(['title' => 'Mid', 'tags' => ['m', 'a']]);
        $this->node(['title' => 'Low', 'tags' => ['b', 'z']]);
        $this->node(['title' => 'High', 'tags' => ['x']]);

        $this->assertSame(['Low', 'Mid', 'High'], $this->titles(new NodeQueryParams(sort: ['field' => 'tags', 'direction' => 'asc'])));
        $this->assertSame(['High', 'Mid', 'Low'], $this->titles(new NodeQueryParams(sort: ['field' => 'tags', 'direction' => 'desc'])));
    }

    public function test_a_filter_does_not_change_the_multiple_field_sort_key(): void
    {
        $this->node(['title' => 'First b', 'tags' => ['b', 'news']]);
        $this->node(['title' => 'First a', 'tags' => ['a', 'news']]);
        $this->node(['title' => 'Last news', 'tags' => ['c', 'news']]);

        $titles = $this->titles(new NodeQueryParams(
            filters: [['field' => 'tags', 'operator' => 'eq', 'value' => 'news']],
            sort: ['field' => 'tags'],
        ));

        $this->assertSame(['First a', 'First b', 'Last news'], $titles);
    }

    public function test_a_node_without_values_of_a_multiple_field_comes_last_in_both_directions(): void
    {
        $this->node(['title' => 'None']);
        $this->node(['title' => 'A', 'tags' => ['a']]);
        $this->node(['title' => 'B', 'tags' => ['b']]);

        $this->assertSame(['A', 'B', 'None'], $this->titles(new NodeQueryParams(sort: ['field' => 'tags', 'direction' => 'asc'])));
        $this->assertSame(['B', 'A', 'None'], $this->titles(new NodeQueryParams(sort: ['field' => 'tags', 'direction' => 'desc'])));
    }

    public function test_a_multiple_field_sort_has_no_duplicates_in_the_page_and_the_total(): void
    {
        $this->node(['title' => 'One', 'tags' => ['a', 'b', 'c']]);
        $this->node(['title' => 'Two', 'tags' => ['d', 'e']]);

        $page = app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams(sort: ['field' => 'tags']));

        $this->assertSame(2, $page->total());
        $this->assertCount(2, $page->items());
    }

    public function test_sorting_by_file_and_youtube_fields_does_not_fail(): void
    {
        foreach (['cover' => 'image', 'clip' => 'youtube'] as $key => $fieldType) {
            ContentField::create(['content_type' => 'article', 'key' => $key, 'name' => ucfirst($key), 'type' => $fieldType, 'is_required' => false]);
        }

        $second = $this->node(['title' => 'Second']);
        $first = $this->node(['title' => 'First']);

        foreach ([[$second, 'cover', 'b.png'], [$first, 'cover', 'a.png'], [$second, 'clip', 'b'], [$first, 'clip', 'a']] as [$node, $key, $value]) {
            DB::table('content_values_string')->insert([
                'content_node_id' => $node->id,
                'content_node_uuid' => $node->uuid,
                'content_type' => 'article',
                'field_key' => $key,
                'lang' => 'default',
                'weight' => 0,
                'value' => $value,
            ]);
        }

        $this->assertSame(['First', 'Second'], $this->titles(new NodeQueryParams(sort: ['field' => 'cover'])));
        $this->assertSame(['First', 'Second'], $this->titles(new NodeQueryParams(sort: ['field' => 'clip'])));
    }

    public function test_equal_sort_keys_are_ordered_by_title_ascending_in_both_directions(): void
    {
        $this->node(['title' => 'C', 'visits' => 1]);
        $this->node(['title' => 'A', 'visits' => 1]);
        $this->node(['title' => 'B', 'visits' => 1]);

        $this->assertSame(['A', 'B', 'C'], $this->titles(new NodeQueryParams(sort: ['field' => 'visits', 'direction' => 'asc'])));
        $this->assertSame(['A', 'B', 'C'], $this->titles(new NodeQueryParams(sort: ['field' => 'visits', 'direction' => 'desc'])));
    }

    public function test_a_node_without_a_title_in_the_language_follows_those_with_one_without_fallback(): void
    {
        $this->enableLanguages('en');
        $date = now()->subDay();
        $untitled = $this->node(['title' => 'AAA untitled in en']);
        $banana = $this->node(['title' => 'Базовий 2']);
        $apple = $this->node(['title' => 'Базовий 3']);
        foreach ([$untitled, $banana, $apple] as $node) {
            $node->update(['published_at' => $date]);
        }
        app(ContentValueWriter::class)->write($banana, ['title' => 'Banana'], 'en');
        app(ContentValueWriter::class)->write($apple, ['title' => 'Apple'], 'en');

        $titles = $this->titles(new NodeQueryParams(lang: 'en', sort: ['field' => 'published_at']));

        $this->assertSame(['Apple', 'Banana', null], $titles);
    }

    public function test_the_same_key_and_title_are_ordered_by_node_id(): void
    {
        $first = $this->node(['title' => 'Same', 'visits' => 1, 'note' => 'first']);
        $second = $this->node(['title' => 'Same', 'visits' => 1, 'note' => 'second']);

        $page = app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams(sort: ['field' => 'visits']));

        $this->assertSame([$first->id, $second->id], collect($page->items())->pluck('id')->all());
    }

    public function test_published_at_sort_uses_the_title_as_the_secondary_order(): void
    {
        $date = now()->subDay();
        $this->node(['title' => 'B'])->update(['published_at' => $date]);
        $this->node(['title' => 'A'])->update(['published_at' => $date]);
        $this->node(['title' => 'Z'])->update(['published_at' => $date->copy()->subDay()]);

        $this->assertSame(['Z', 'A', 'B'], $this->titles(new NodeQueryParams(sort: ['field' => 'published_at', 'direction' => 'asc'])));
        $this->assertSame(['A', 'B', 'Z'], $this->titles(new NodeQueryParams(sort: ['field' => 'published_at', 'direction' => 'desc'])));
    }

    /** Create nodes A, B, C in this order, each with a fixed `created_at` so the order is not by insert time only. */
    private function datedNodes(string $column): void
    {
        foreach (['A' => 3, 'B' => 2, 'C' => 1] as $title => $daysAgo) {
            $this->node(['title' => $title]);
            ContentNode::query()->latest('id')->first()->forceFill([$column => now()->subDays($daysAgo)])->saveQuietly();
        }
    }

    public function test_sorting_by_created_at_and_updated_at_in_both_directions(): void
    {
        foreach (['created_at', 'updated_at'] as $column) {
            ContentNode::query()->delete();
            $this->datedNodes($column);

            $this->assertSame(['A', 'B', 'C'], $this->titles(new NodeQueryParams(sort: ['field' => $column, 'direction' => 'asc'])));
            $this->assertSame(['C', 'B', 'A'], $this->titles(new NodeQueryParams(sort: ['field' => $column, 'direction' => 'desc'])));
        }
    }

    public function test_equal_service_dates_are_ordered_by_node_id_not_by_title(): void
    {
        $date = now()->subDay();
        $this->node(['title' => 'Z']);
        $this->node(['title' => 'A']);
        ContentNode::query()->update(['created_at' => $date]);

        $this->assertSame(['Z', 'A'], $this->titles(new NodeQueryParams(sort: ['field' => 'created_at', 'direction' => 'asc'])));
        $this->assertSame(['A', 'Z'], $this->titles(new NodeQueryParams(sort: ['field' => 'created_at', 'direction' => 'desc'])));
    }

    public function test_nodes_without_unpublished_at_come_last_in_both_directions(): void
    {
        $this->node(['title' => 'None']);
        $this->node(['title' => 'Early'])->update(['unpublished_at' => now()->addDay()]);
        $this->node(['title' => 'Late'])->update(['unpublished_at' => now()->addDays(5)]);

        $this->assertSame(['Early', 'Late', 'None'], $this->titles(new NodeQueryParams(sort: ['field' => 'unpublished_at', 'direction' => 'asc'])));
        $this->assertSame(['Late', 'Early', 'None'], $this->titles(new NodeQueryParams(sort: ['field' => 'unpublished_at', 'direction' => 'desc'])));
    }

    public function test_is_active_sort_puts_disabled_nodes_first_ascending(): void
    {
        $this->node(['title' => 'On B']);
        $this->node(['title' => 'Off'])->update(['is_active' => false]);
        $this->node(['title' => 'On A']);

        $this->assertSame(['Off', 'On A', 'On B'], $this->titles(new NodeQueryParams(sort: ['field' => 'is_active', 'direction' => 'asc'])));
        $this->assertSame(['On A', 'On B', 'Off'], $this->titles(new NodeQueryParams(sort: ['field' => 'is_active', 'direction' => 'desc'])));
    }

    public function test_status_sort_follows_the_node_status_in_all_four_states(): void
    {
        $this->node(['title' => 'Disabled'])->update(['is_active' => false]);
        $this->node(['title' => 'Expired'])->update(['published_at' => now()->subDays(2), 'unpublished_at' => now()->subDay()]);
        $this->node(['title' => 'Scheduled'])->update(['published_at' => now()->addDay()]);
        $this->node(['title' => 'Published B'])->update(['published_at' => now()->subDay()]);
        $this->node(['title' => 'Published A'])->update(['published_at' => now()->subDay()]);

        $asc = $this->titles(new NodeQueryParams(sort: ['field' => 'status', 'direction' => 'asc']));

        $this->assertSame(['Published A', 'Published B', 'Scheduled', 'Expired', 'Disabled'], $asc);
        // The SQL order must agree with ContentNode::status() for every state.
        $nodes = app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams(sort: ['field' => 'status', 'direction' => 'asc']))->items();
        $this->assertSame(['published', 'published', 'scheduled', 'expired', 'disabled'], array_map(fn (ContentNode $n) => $n->status(), $nodes));
        $this->assertSame(['Disabled', 'Expired', 'Scheduled', 'Published A', 'Published B'], $this->titles(new NodeQueryParams(sort: ['field' => 'status', 'direction' => 'desc'])));
    }

    /** @param  array<string, int>  $counters  Title => views */
    private function nodesWithViews(array $counters): void
    {
        foreach ($counters as $title => $views) {
            $this->node(['title' => (string) $title])->incrementViews($views);
        }
    }

    public function test_views_are_sorted_numerically(): void
    {
        $this->nodesWithViews(['V9' => 9, 'V10' => 10, 'V100' => 100]);

        $this->assertSame(['V100', 'V10', 'V9'], $this->titles(new NodeQueryParams(sort: ['field' => 'views', 'direction' => 'desc'])));
        $this->assertSame(['V9', 'V10', 'V100'], $this->titles(new NodeQueryParams(sort: ['field' => 'views', 'direction' => 'asc'])));
    }

    public function test_a_zero_counter_is_a_value_and_comes_first_ascending(): void
    {
        $this->nodesWithViews(['Some' => 3, 'None' => 0]);

        $this->assertSame(['None', 'Some'], $this->titles(new NodeQueryParams(sort: ['field' => 'views', 'direction' => 'asc'])));
        $this->assertSame(['Some', 'None'], $this->titles(new NodeQueryParams(sort: ['field' => 'views', 'direction' => 'desc'])));
    }

    public function test_equal_views_are_ordered_by_title_then_id(): void
    {
        $this->nodesWithViews(['C' => 4, 'A' => 4, 'B' => 4]);
        $twin = $this->node(['title' => 'A']);
        $twin->incrementViews(4);

        foreach (['asc', 'desc'] as $direction) {
            $this->assertSame(['A', 'A', 'B', 'C'], $this->titles(new NodeQueryParams(sort: ['field' => 'views', 'direction' => $direction])));
        }

        $ids = collect(app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams(sort: ['field' => 'views']))->items())
            ->filter(fn (ContentNode $n) => ($n->values['title'] ?? null) === 'A')->pluck('id')->all();
        $this->assertSame([min($ids), max($ids)], $ids);
    }

    public function test_a_bad_direction_for_a_service_sort_is_rejected(): void
    {
        foreach (['status', 'is_active', 'created_at', 'updated_at', 'published_at', 'unpublished_at', 'views'] as $field) {
            $this->assertInvalid(new NodeQueryParams(sort: ['field' => $field, 'direction' => 'up']), 'sort.direction');
        }
    }

    public function test_without_an_explicit_sort_the_order_is_unchanged(): void
    {
        foreach (['C', 'A', 'B'] as $title) {
            $this->node(['title' => $title]);
        }

        $this->assertSame(['C', 'A', 'B'], $this->titles(new NodeQueryParams));
    }

    public function test_only_visible_hides_scheduled_and_disabled_nodes(): void
    {
        $this->node(['title' => 'Published']);
        $scheduled = $this->node(['title' => 'Scheduled']);
        $scheduled->update(['published_at' => now()->addDay()]);
        $disabled = $this->node(['title' => 'Disabled']);
        $disabled->update(['is_active' => false]);
        $past = $this->node(['title' => 'Past disabled']);
        $past->update(['is_active' => false, 'published_at' => now()->subDay()]);

        $page = app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams(onlyVisible: true));

        $this->assertSame(['Published'], $this->titles(new NodeQueryParams(onlyVisible: true)));
        $this->assertSame(1, $page->total());
    }

    public function test_only_visible_hides_expired_nodes_and_keeps_open_ended_ones(): void
    {
        $this->node(['title' => 'Open']);
        $this->node(['title' => 'In interval'])->update(['published_at' => now()->subDay(), 'unpublished_at' => now()->addDay()]);
        $this->node(['title' => 'Expired'])->update(['published_at' => now()->subDays(2), 'unpublished_at' => now()->subDay()]);

        $params = new NodeQueryParams(onlyVisible: true);
        $titles = $this->titles($params);
        sort($titles);

        $this->assertSame(['In interval', 'Open'], $titles);
        $this->assertSame(2, app(ContentNodeQuery::class)->paginate($this->type, $params)->total());
        $this->assertSame(3, app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams)->total());
    }

    public function test_without_the_option_everything_is_listed(): void
    {
        $this->node(['title' => 'Published']);
        $this->node(['title' => 'Scheduled'])->update(['published_at' => now()->addDay()]);
        $this->node(['title' => 'Disabled'])->update(['is_active' => false]);

        $page = app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams);

        $this->assertSame(3, $page->total());
    }

    public function test_only_visible_combines_with_search_filters_and_sorting(): void
    {
        $this->node(['title' => 'Lamp A', 'visits' => 5]);
        $this->node(['title' => 'Lamp B', 'visits' => 9]);
        $this->node(['title' => 'Lamp C', 'visits' => 7])->update(['is_active' => false]);
        $this->node(['title' => 'Chair', 'visits' => 8]);

        $titles = $this->titles(new NodeQueryParams(
            search: 'Lamp',
            filters: [['field' => 'visits', 'operator' => 'gte', 'value' => 6]],
            sort: ['field' => 'visits', 'direction' => 'desc'],
            onlyVisible: true,
        ));

        $this->assertSame(['Lamp B'], $titles);
    }

    public function test_find_by_slug_can_require_a_visible_node(): void
    {
        $this->node(['title' => 'Off']);
        $disabled = $this->node(['title' => 'Disabled']);
        $disabled->update(['is_active' => false]);
        $scheduled = $this->node(['title' => 'Later']);
        $scheduled->update(['published_at' => now()->addHour()]);
        $live = $this->node(['title' => 'Live']);

        $this->assertNull(ContentNode::findBySlug($disabled->slug, onlyVisible: true));
        $this->assertNull(ContentNode::findBySlug($scheduled->slug, onlyVisible: true));
        $this->assertNotNull(ContentNode::findBySlug($live->slug, onlyVisible: true));
        $this->assertNotNull(ContentNode::findBySlug($disabled->slug));
    }

    public function test_per_page_zero_lists_all_nodes_in_one_page(): void
    {
        foreach (['A', 'B', 'C'] as $title) {
            $this->node(['title' => $title]);
        }

        $page = app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams(perPage: 0, sort: ['field' => 'title', 'direction' => 'desc']));

        $this->assertSame(3, $page->total());
        $this->assertSame(1, $page->lastPage());
        $this->assertSame(['C', 'B', 'A'], collect($page->items())->map(fn ($n) => $n->values['title'])->all());
    }

    public function test_per_page_zero_is_capped_by_max_all(): void
    {
        config(['content.max_all' => 2]);

        foreach (['A', 'B', 'C'] as $title) {
            $this->node(['title' => $title]);
        }

        $this->assertSame(['A', 'B'], $this->titles(new NodeQueryParams(perPage: 0, sort: ['field' => 'title', 'direction' => 'asc'])));
    }

    public function test_positive_per_page_is_unchanged_and_capped_by_max_per_page(): void
    {
        config(['content.max_per_page' => 2]);

        foreach (['A', 'B', 'C'] as $title) {
            $this->node(['title' => $title]);
        }

        $page = app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams(perPage: 50));

        $this->assertCount(2, $page->items());
        $this->assertSame(3, $page->total());
        $this->assertSame(2, $page->lastPage());
    }

    public function test_a_type_reference_sorts_by_the_name_of_the_target(): void
    {
        ContentType::create(['key' => 'b_first', 'name' => 'Zebra', 'kind' => 'block']);
        ContentType::create(['key' => 'a_last', 'name' => 'Alpha', 'kind' => 'block']);
        ContentField::create(['content_type' => 'article', 'key' => 'source', 'name' => 'Source', 'type' => 'type_ref', 'settings' => ['kind' => 'block']]);
        $this->node(['title' => 'None']);
        $this->node(['title' => 'Zebra node', 'source' => 'b_first']);
        $this->node(['title' => 'Alpha node', 'source' => 'a_last']);

        $this->assertSame(['Alpha node', 'Zebra node', 'None'], $this->titles(new NodeQueryParams(sort: ['field' => 'source', 'direction' => 'asc'])));
        $this->assertSame(['Zebra node', 'Alpha node', 'None'], $this->titles(new NodeQueryParams(sort: ['field' => 'source', 'direction' => 'desc'])));
    }

    public function test_a_node_reference_sorts_by_the_title_of_the_target(): void
    {
        $news = ContentType::create(['key' => 'news', 'name' => 'News']);
        ContentField::create(['content_type' => 'article', 'key' => 'related', 'name' => 'Related', 'type' => 'node_ref', 'settings' => ['content_type' => 'news']]);
        $make = fn (string $title) => app(ContentNodeCreator::class)->create($news, ['title' => $title], authorId: $this->authorId());
        $zulu = $make('Zulu');
        $alpha = $make('Alpha');
        $this->node(['title' => 'None']);
        $this->node(['title' => 'Zulu node', 'related' => $zulu->uuid]);
        $this->node(['title' => 'Alpha node', 'related' => $alpha->uuid]);

        $this->assertSame(['Alpha node', 'Zulu node', 'None'], $this->titles(new NodeQueryParams(sort: ['field' => 'related', 'direction' => 'asc'])));
        $this->assertSame(['Zulu node', 'Alpha node', 'None'], $this->titles(new NodeQueryParams(sort: ['field' => 'related', 'direction' => 'desc'])));
    }

    /** @return array<int, mixed> */
    private function periodTitles(string $field, string $part, array $extra = []): array
    {
        return $this->titles(new NodeQueryParams(...[
            'period' => ['field' => $field, 'part' => $part, 'since' => now()->parse('2026-11-11 00:00:00')],
            'sort' => ['field' => 'title', 'direction' => 'asc'],
            ...$extra,
        ]));
    }

    public function test_period_by_a_single_date(): void
    {
        $this->node(['title' => 'Old', 'released' => '2026-11-10']);
        $this->node(['title' => 'Today', 'released' => '2026-11-11']);
        $this->node(['title' => 'Next', 'released' => '2026-11-12']);

        $this->assertSame(['Next', 'Today'], $this->periodTitles('released', 'current'));
        $this->assertSame(['Old'], $this->periodTitles('released', 'past'));
    }

    public function test_period_by_a_datetime_field_compares_the_moment(): void
    {
        $this->node(['title' => 'Before', 'updated' => '2026-11-10 23:59:59']);
        $this->node(['title' => 'Start', 'updated' => '2026-11-11 00:00:00']);

        $this->assertSame(['Start'], $this->periodTitles('updated', 'current'));
        $this->assertSame(['Before'], $this->periodTitles('updated', 'past'));
    }

    public function test_period_keeps_a_running_node_of_a_multiple_field(): void
    {
        ContentField::create(['content_type' => 'article', 'key' => 'dates', 'name' => 'Dates', 'type' => 'datetime', 'is_multiple' => true]);
        $this->node(['title' => 'Running', 'dates' => ['2026-11-09 18:00:00', '2026-11-12 20:00:00']]);
        $this->node(['title' => 'Finished', 'dates' => ['2026-11-08 18:00:00', '2026-11-09 20:00:00']]);

        $this->assertSame(['Running'], $this->periodTitles('dates', 'current'));
        $this->assertSame(['Finished'], $this->periodTitles('dates', 'past'));
    }

    public function test_period_treats_a_node_without_a_value_as_past(): void
    {
        $this->node(['title' => 'Empty']);

        $this->assertSame([], $this->periodTitles('released', 'current'));
        $this->assertSame(['Empty'], $this->periodTitles('released', 'past'));
    }

    public function test_period_by_unpublished_at_counts_an_empty_value_as_current(): void
    {
        $this->node(['title' => 'Open']);
        $passed = $this->node(['title' => 'Passed']);
        $future = $this->node(['title' => 'Future']);
        // Written directly: the model rejects an unpublish date before the publish date (now).
        ContentNode::query()->whereKey($passed->id)->update(['unpublished_at' => '2026-11-10 12:00:00']);
        ContentNode::query()->whereKey($future->id)->update(['unpublished_at' => '2026-11-20 12:00:00']);

        $this->assertSame(['Future', 'Open'], $this->periodTitles('unpublished_at', 'current'));
        $this->assertSame(['Passed'], $this->periodTitles('unpublished_at', 'past'));
    }

    public function test_period_by_created_at(): void
    {
        $old = $this->node(['title' => 'Old']);
        $new = $this->node(['title' => 'New']);
        ContentNode::query()->whereKey($old->id)->update(['created_at' => '2026-11-10 23:00:00']);
        ContentNode::query()->whereKey($new->id)->update(['created_at' => '2026-11-11 00:00:00']);

        $this->assertSame(['New'], $this->periodTitles('created_at', 'current'));
        $this->assertSame(['Old'], $this->periodTitles('created_at', 'past'));
    }

    public function test_period_parts_are_exact_complements(): void
    {
        $this->node(['title' => 'A', 'released' => '2026-11-01']);
        $this->node(['title' => 'B', 'released' => '2026-11-11']);
        $this->node(['title' => 'C']);

        foreach (['released', 'created_at', 'unpublished_at'] as $field) {
            $current = $this->periodTitles($field, 'current');
            $past = $this->periodTitles($field, 'past');

            $this->assertSame([], array_intersect($current, $past), $field);
            $this->assertSame(['A', 'B', 'C'], collect([...$current, ...$past])->sort()->values()->all(), $field);
        }
    }

    public function test_period_combines_with_filters_and_visibility(): void
    {
        $this->node(['title' => 'Cheap', 'released' => '2026-11-12', 'price' => '5']);
        $this->node(['title' => 'Dear', 'released' => '2026-11-12', 'price' => '50']);
        $this->node(['title' => 'Off', 'released' => '2026-11-12', 'price' => '5'])->update(['is_active' => false]);

        $this->assertSame(['Cheap'], $this->periodTitles('released', 'current', [
            'filters' => [['field' => 'price', 'operator' => 'lt', 'value' => 10]],
            'onlyVisible' => true,
        ]));
    }

    public function test_period_pagination_counts_the_same_condition(): void
    {
        $this->node(['title' => 'Old', 'released' => '2026-11-01']);
        $this->node(['title' => 'New', 'released' => '2026-11-12']);

        $page = app(ContentNodeQuery::class)->paginate($this->type, new NodeQueryParams(
            period: ['field' => 'released', 'part' => 'current', 'since' => now()->parse('2026-11-11')],
        ));

        $this->assertSame(1, $page->total());
    }

    public function test_period_rejects_an_unsupported_field(): void
    {
        $since = now();

        foreach (['body', 'price', 'missing', 'published_at'] as $field) {
            $this->assertInvalid(new NodeQueryParams(period: ['field' => $field, 'part' => 'current', 'since' => $since]), 'period.field');
        }
    }
}
