<?php

namespace Webtack\Ecms\Tests\Unit\Support\Table;

use Illuminate\Http\Request;
use Webtack\Ecms\Support\Table\TablePage;
use Webtack\Ecms\Support\Table\TableRequest;
use Webtack\Ecms\Tests\TestCase;

class TablePageTest extends TestCase
{
    private array $columns = [
        ['key' => 'name', 'type' => 'text', 'sortable' => true, 'filterable' => true],
        ['key' => 'price', 'type' => 'number', 'sortable' => true, 'filterable' => true],
        ['key' => 'released', 'type' => 'date', 'sortable' => true, 'filterable' => true],
        ['key' => 'active', 'type' => 'boolean', 'sortable' => true, 'filterable' => true],
        ['key' => 'status', 'type' => 'select', 'sortable' => true, 'filterable' => true, 'options' => ['draft', 'live']],
    ];

    private function rows(): array
    {
        return [
            ['id' => 1, 'name' => 'Lamp', 'price' => 100, 'released' => '2024-03-01', 'active' => true, 'status' => 'live'],
            ['id' => 2, 'name' => 'lampshade', 'price' => 9, 'released' => '2023-12-31', 'active' => false, 'status' => 'draft'],
            ['id' => 3, 'name' => 'Table', 'price' => 10, 'released' => '2024-03-01', 'active' => true, 'status' => 'live'],
            ['id' => 4, 'name' => 'Chair', 'price' => null, 'released' => null, 'active' => null, 'status' => null],
            ['id' => 5, 'name' => 'Árbol', 'price' => 55.5, 'released' => '2025-01-15', 'active' => false, 'status' => 'live'],
        ];
    }

    private function page(array $query, ?array $rows = null): array
    {
        $table = TableRequest::from(Request::create('/items', 'GET', $query), $this->columns);

        return TablePage::fromCollection($rows ?? $this->rows(), $table);
    }

    private function ids(array $page): array
    {
        return array_column($page['rows'], 'id');
    }

    private function filtered(string $column, string $operator, mixed $value): array
    {
        return $this->ids($this->page(['filters' => [['c' => $column, 'o' => $operator, 'v' => $value]]]));
    }

    public function test_the_response_has_the_contract_shape(): void
    {
        $page = $this->page([]);

        $this->assertSame(['rows', 'meta', 'query', 'ignored', 'operators'], array_keys($page));
        $this->assertSame(
            ['page' => 1, 'perPage' => 15, 'total' => 5, 'lastPage' => 1, 'from' => 1, 'to' => 5],
            $page['meta'],
        );
        $this->assertSame(TableRequest::OPERATORS, $page['operators']);
        $this->assertSame(1, $page['query']['page']);
    }

    public function test_text_operators(): void
    {
        $this->assertSame([1, 2], $this->filtered('name', 'contains', 'LAMP'));
        $this->assertSame([1], $this->filtered('name', 'eq', 'lamp'));
    }

    public function test_numbers_compare_numerically(): void
    {
        $this->assertSame([1], $this->filtered('price', 'gt', '55.5'));
        $this->assertSame([1, 5], $this->filtered('price', 'gte', '55.5'));
        $this->assertSame([2], $this->filtered('price', 'lt', '10'));
        $this->assertSame([2, 3], $this->filtered('price', 'lte', '10'));
        $this->assertSame([3], $this->filtered('price', 'eq', '10'));
        $this->assertSame([3, 5], $this->filtered('price', 'between', ['10', '55.5']));
    }

    public function test_dates_compare_chronologically(): void
    {
        $this->assertSame([5], $this->filtered('released', 'gt', '2024-03-01'));
        $this->assertSame([1, 3], $this->filtered('released', 'eq', '2024-03-01'));
        $this->assertSame([2], $this->filtered('released', 'lt', '2024-01-01'));
        $this->assertSame([1, 3, 5], $this->filtered('released', 'between', ['2024-03-01', '2025-12-31']));
    }

    public function test_boolean_and_select_equality(): void
    {
        $this->assertSame([1, 3], $this->filtered('active', 'eq', 'true'));
        $this->assertSame([2, 5], $this->filtered('active', 'eq', 'false'));
        $this->assertSame([2], $this->filtered('status', 'eq', 'draft'));
    }

    public function test_empty_values_never_match_a_filter(): void
    {
        $this->assertNotContains(4, $this->filtered('price', 'lte', '1000'));
        $this->assertNotContains(4, $this->filtered('active', 'eq', 'false'));
    }

    public function test_filters_combine_with_and(): void
    {
        $page = $this->page(['filters' => [
            ['c' => 'active', 'o' => 'eq', 'v' => 'true'],
            ['c' => 'price', 'o' => 'gte', 'v' => '50'],
        ]]);

        $this->assertSame([1], $this->ids($page));
    }

    public function test_search_is_case_insensitive_over_text_columns_only(): void
    {
        $this->assertSame([1, 2], $this->ids($this->page(['q' => 'LAMP'])));
        $this->assertSame([], $this->ids($this->page(['q' => '100'])));
        $this->assertSame([5], $this->ids($this->page(['q' => 'árb'])));
    }

    public function test_sorting_numbers_puts_empty_values_last_in_both_directions(): void
    {
        $this->assertSame([2, 3, 5, 1, 4], $this->ids($this->page(['sort' => 'price', 'dir' => 'asc'])));
        $this->assertSame([1, 5, 3, 2, 4], $this->ids($this->page(['sort' => 'price', 'dir' => 'desc'])));
    }

    public function test_sorting_is_stable_for_equal_values(): void
    {
        $this->assertSame([2, 1, 3, 5, 4], $this->ids($this->page(['sort' => 'released', 'dir' => 'asc'])));
        $this->assertSame([5, 1, 3, 2, 4], $this->ids($this->page(['sort' => 'released', 'dir' => 'desc'])));
    }

    public function test_text_sorts_by_lowercase_and_booleans_false_first(): void
    {
        $this->assertSame('Árbol', $this->page(['sort' => 'name', 'dir' => 'desc'])['rows'][0]['name']);
        $this->assertSame([2, 5, 1, 3, 4], $this->ids($this->page(['sort' => 'active', 'dir' => 'asc'])));
    }

    public function test_paging_summary_and_slice(): void
    {
        $rows = array_map(fn ($i) => ['id' => $i, 'name' => "Row {$i}", 'price' => $i], range(1, 42));

        $page = $this->page(['page' => '2'], $rows);

        $this->assertSame(range(16, 30), $this->ids($page));
        $this->assertSame(['page' => 2, 'perPage' => 15, 'total' => 42, 'lastPage' => 3, 'from' => 16, 'to' => 30], $page['meta']);

        $last = $this->page(['page' => '3'], $rows);
        $this->assertSame(['from' => 31, 'to' => 42], ['from' => $last['meta']['from'], 'to' => $last['meta']['to']]);
    }

    public function test_a_page_out_of_range_shows_the_last_one(): void
    {
        $rows = array_map(fn ($i) => ['id' => $i, 'name' => "Row {$i}"], range(1, 42));

        $page = $this->page(['page' => '99'], $rows);

        $this->assertSame(3, $page['meta']['page']);
        $this->assertSame(range(31, 42), $this->ids($page));
        $this->assertSame(3, $page['query']['page']);
    }

    public function test_an_empty_result_has_a_zero_summary(): void
    {
        $page = $this->page(['q' => 'zzz']);

        $this->assertSame([], $page['rows']);
        $this->assertSame(['page' => 1, 'perPage' => 15, 'total' => 0, 'lastPage' => 1, 'from' => 0, 'to' => 0], $page['meta']);
    }

    public function test_ignored_parameters_are_reported_and_the_rest_applied(): void
    {
        $page = $this->page(['sort' => 'nope', 'dir' => 'asc', 'q' => 'table']);

        $this->assertSame([3], $this->ids($page));
        $this->assertSame([['param' => 'sort', 'reason' => 'unknown_column']], $page['ignored']);
    }

    public function test_make_wraps_an_already_fetched_page(): void
    {
        $table = TableRequest::from(Request::create('/items', 'GET', ['page' => '9', 'per_page' => '15']), $this->columns)->withTotal(20);

        $page = TablePage::make([['id' => 16]], 20, $table);

        $this->assertSame(2, $page['meta']['page']);
        $this->assertSame(['from' => 16, 'to' => 20, 'lastPage' => 2], [
            'from' => $page['meta']['from'], 'to' => $page['meta']['to'], 'lastPage' => $page['meta']['lastPage'],
        ]);
    }
}
