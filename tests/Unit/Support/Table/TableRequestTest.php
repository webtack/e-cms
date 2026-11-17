<?php

namespace Webtack\Ecms\Tests\Unit\Support\Table;

use Illuminate\Http\Request;
use Webtack\Ecms\Support\Table\TableRequest;
use Webtack\Ecms\Tests\TestCase;

class TableRequestTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $columns = [
        ['key' => 'name', 'type' => 'text', 'sortable' => true, 'filterable' => true],
        ['key' => 'price', 'type' => 'number', 'sortable' => true, 'filterable' => true],
        ['key' => 'released', 'type' => 'date', 'filterable' => true],
        ['key' => 'active', 'type' => 'boolean', 'filterable' => true],
        ['key' => 'status', 'type' => 'select', 'filterable' => true, 'options' => ['draft', 'live']],
        ['key' => 'note', 'type' => 'text'],
    ];

    private function parse(array $query): TableRequest
    {
        return TableRequest::from(Request::create('/items', 'GET', $query), $this->columns);
    }

    private function filter(string $column, string $operator, mixed $value): array
    {
        return ['filters' => [['c' => $column, 'o' => $operator, 'v' => $value]]];
    }

    public function test_defaults_when_the_address_has_no_parameters(): void
    {
        $table = $this->parse([]);

        $this->assertSame(
            ['search' => '', 'filters' => [], 'sort' => null, 'dir' => null, 'page' => 1, 'perPage' => 15],
            $table->toState(),
        );
        $this->assertSame([], $table->ignored);
    }

    public function test_a_full_address_is_parsed(): void
    {
        $table = $this->parse([
            'q' => '  lamp ',
            'sort' => 'price',
            'dir' => 'desc',
            'page' => '3',
            'per_page' => '30',
            'filters' => [['c' => 'price', 'o' => 'gte', 'v' => '100']],
        ]);

        $this->assertSame('lamp', $table->search);
        $this->assertSame([['column' => 'price', 'operator' => 'gte', 'value' => '100']], $table->filters);
        $this->assertSame('price', $table->sort);
        $this->assertSame('desc', $table->dir);
        $this->assertSame(3, $table->page);
        $this->assertSame(30, $table->perPage);
        $this->assertSame([], $table->ignored);
    }

    public function test_every_operator_is_accepted_for_its_column_type(): void
    {
        foreach (TableRequest::OPERATORS['text'] as $operator) {
            $this->assertCount(1, $this->parse($this->filter('name', $operator, 'a'))->filters, "text {$operator}");
        }

        foreach (TableRequest::OPERATORS['number'] as $operator) {
            $value = $operator === 'between' ? ['1', '9'] : '5';
            $this->assertCount(1, $this->parse($this->filter('price', $operator, $value))->filters, "number {$operator}");
        }

        foreach (TableRequest::OPERATORS['date'] as $operator) {
            $value = $operator === 'between' ? ['2024-01-01', '2024-12-31'] : '2024-05-05';
            $this->assertCount(1, $this->parse($this->filter('released', $operator, $value))->filters, "date {$operator}");
        }

        $this->assertCount(1, $this->parse($this->filter('active', 'eq', 'true'))->filters);
        $this->assertCount(1, $this->parse($this->filter('status', 'eq', 'live'))->filters);
    }

    public function test_between_needs_two_bounds(): void
    {
        $table = $this->parse($this->filter('price', 'between', ['1', '9']));
        $this->assertSame(['1', '9'], $table->filters[0]['value']);

        foreach ([['1'], '5', ['1', ''], ['', '9']] as $value) {
            $table = $this->parse($this->filter('price', 'between', $value));
            $this->assertSame([], $table->filters);
            $this->assertSame([['param' => 'filters[0]', 'reason' => 'invalid_value']], $table->ignored);
        }
    }

    public function test_an_unknown_column_is_ignored(): void
    {
        $table = $this->parse($this->filter('nope', 'eq', '1'));

        $this->assertSame([], $table->filters);
        $this->assertSame([['param' => 'filters[0]', 'reason' => 'unknown_column']], $table->ignored);
    }

    public function test_a_column_that_is_not_filterable_is_ignored(): void
    {
        $table = $this->parse($this->filter('note', 'eq', 'x'));

        $this->assertSame([['param' => 'filters[0]', 'reason' => 'not_filterable']], $table->ignored);
    }

    public function test_an_operator_not_allowed_for_the_type_is_ignored(): void
    {
        foreach ([['name', 'gt'], ['active', 'contains'], ['status', 'between'], ['price', 'contains'], ['price', 'nope']] as [$column, $operator]) {
            $table = $this->parse($this->filter($column, $operator, '1'));

            $this->assertSame([], $table->filters, "{$column} {$operator}");
            $this->assertSame([['param' => 'filters[0]', 'reason' => 'invalid_operator']], $table->ignored);
        }
    }

    public function test_invalid_values_are_ignored(): void
    {
        $cases = [
            ['price', 'abc'], ['price', ''], ['released', '2024-13-40'], ['released', 'yesterday'],
            ['active', 'maybe'], ['status', 'archived'], ['name', ''],
        ];

        foreach ($cases as [$column, $value]) {
            $operator = $column === 'price' ? 'gt' : 'eq';
            $table = $this->parse($this->filter($column, $operator, $value));

            $this->assertSame([], $table->filters, "{$column}={$value}");
            $this->assertSame('invalid_value', $table->ignored[0]['reason']);
        }
    }

    public function test_booleans_are_normalized(): void
    {
        $this->assertSame('true', $this->parse($this->filter('active', 'eq', '1'))->filters[0]['value']);
        $this->assertSame('false', $this->parse($this->filter('active', 'eq', '0'))->filters[0]['value']);
    }

    public function test_valid_filters_survive_next_to_invalid_ones(): void
    {
        $table = $this->parse(['filters' => [
            ['c' => 'nope', 'o' => 'eq', 'v' => '1'],
            ['c' => 'price', 'o' => 'lt', 'v' => '10'],
        ]]);

        $this->assertCount(1, $table->filters);
        $this->assertSame('filters[0]', $table->ignored[0]['param']);
    }

    public function test_malformed_filters_do_not_fail(): void
    {
        $this->assertSame([['param' => 'filters', 'reason' => 'invalid_value']], $this->parse(['filters' => 'x'])->ignored);
        $this->assertSame('unknown_column', $this->parse(['filters' => ['x']])->ignored[0]['reason']);
    }

    public function test_too_many_filters_are_capped(): void
    {
        $filters = array_fill(0, TableRequest::MAX_FILTERS + 2, ['c' => 'price', 'o' => 'gt', 'v' => '1']);
        $table = $this->parse(['filters' => $filters]);

        $this->assertCount(TableRequest::MAX_FILTERS, $table->filters);
        $this->assertCount(2, $table->ignored);
        $this->assertSame('too_many_filters', $table->ignored[0]['reason']);
    }

    public function test_an_unknown_or_unsortable_sort_column_is_ignored(): void
    {
        $table = $this->parse(['sort' => 'nope', 'dir' => 'asc']);
        $this->assertNull($table->sort);
        $this->assertSame([['param' => 'sort', 'reason' => 'unknown_column']], $table->ignored);

        $table = $this->parse(['sort' => 'note', 'dir' => 'asc']);
        $this->assertNull($table->sort);
        $this->assertSame([['param' => 'sort', 'reason' => 'not_sortable']], $table->ignored);
    }

    public function test_a_bad_direction_drops_the_sort(): void
    {
        $table = $this->parse(['sort' => 'price', 'dir' => 'sideways']);

        $this->assertNull($table->sort);
        $this->assertNull($table->dir);
        $this->assertSame([['param' => 'dir', 'reason' => 'invalid_direction']], $table->ignored);
    }

    public function test_a_missing_direction_defaults_to_ascending(): void
    {
        $table = $this->parse(['sort' => 'price']);

        $this->assertSame('asc', $table->dir);
        $this->assertSame([], $table->ignored);
    }

    public function test_a_direction_without_a_sort_column_is_reported(): void
    {
        $this->assertSame([['param' => 'dir', 'reason' => 'sort_missing']], $this->parse(['dir' => 'asc'])->ignored);
    }

    public function test_per_page_above_the_maximum_is_capped_silently(): void
    {
        $table = $this->parse(['per_page' => '500']);

        $this->assertSame(100, $table->perPage);
        $this->assertSame([], $table->ignored);
    }

    public function test_an_unsupported_per_page_falls_back_to_the_default(): void
    {
        foreach (['20', '0', '-5', 'abc'] as $value) {
            $table = $this->parse(['per_page' => $value]);

            $this->assertSame(15, $table->perPage, $value);
            $this->assertSame([['param' => 'per_page', 'reason' => 'invalid_value']], $table->ignored);
        }
    }

    public function test_an_invalid_page_falls_back_to_the_first(): void
    {
        foreach (['0', '-1', 'x', '1.5'] as $value) {
            $table = $this->parse(['page' => $value]);

            $this->assertSame(1, $table->page, $value);
            $this->assertSame([['param' => 'page', 'reason' => 'invalid_value']], $table->ignored);
        }
    }

    public function test_a_page_beyond_the_range_is_clamped_to_the_last(): void
    {
        $table = $this->parse(['page' => '99'])->withTotal(42);

        $this->assertSame(3, $table->page);
        $this->assertSame(1, $this->parse(['page' => '5'])->withTotal(0)->page);
    }

    public function test_array_valued_search_is_ignored(): void
    {
        $table = $this->parse(['q' => ['x']]);

        $this->assertSame('', $table->search);
        $this->assertSame([['param' => 'q', 'reason' => 'invalid_value']], $table->ignored);
    }
}
