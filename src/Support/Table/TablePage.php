<?php

namespace Webtack\Ecms\Support\Table;

use Illuminate\Support\Collection;

/**
 * Builds the table prop the `DataTable` component consumes: `rows`, `meta`, `query`, `ignored`
 * and `operators`.
 *
 * Two ways in:
 *  - `fromCollection()` applies search, filters, sort and paging to an in-memory collection;
 *  - `make()` wraps rows a controller already fetched from its own source (database, EAV query).
 *    Count first, call `$table->withTotal($total)` so the page is in range, fetch that page, then `make()`.
 *
 * Collection rules (the client engine in `clientEngine.ts` follows the same ones): search and
 * `contains` are case-insensitive; `eq` on text is case-insensitive too; numbers compare
 * numerically, dates (`YYYY-MM-DD`) chronologically, text and select values by lowercase code
 * point; empty values (`null`, `''`) never match a filter and always sort last. Search covers the
 * searchable columns (`text` by default). Sorting is stable.
 *
 * @example
 *   $table = TableRequest::from($request, $columns);
 *
 *   return Inertia::render('Admin/Things', ['table' => TablePage::fromCollection($things, $table)]);
 * @example
 *   $table = TableRequest::from($request, $columns)->withTotal($query->count());
 *   $rows = $query->forPage($table->page, $table->perPage)->get();
 *
 *   return Inertia::render('Admin/Things', ['table' => TablePage::make($rows, $total, $table)]);
 */
final class TablePage
{
    /**
     * Applies the requested state to `$rows` (arrays or objects) and returns one page.
     *
     * @param  iterable<array-key, mixed>  $rows
     * @return array<string, mixed>
     */
    public static function fromCollection(iterable $rows, TableRequest $table): array
    {
        $rows = Collection::make($rows)->values();

        $rows = self::search($rows, $table);
        $rows = self::filter($rows, $table);
        $rows = self::sort($rows, $table);

        $table = $table->withTotal($rows->count());

        return self::make($rows->forPage($table->page, $table->perPage), $rows->count(), $table);
    }

    /**
     * Wraps an already fetched page of rows.
     *
     * @param  iterable<array-key, mixed>  $rows  The rows of `$table->page` only.
     * @return array<string, mixed>
     */
    public static function make(iterable $rows, int $total, TableRequest $table): array
    {
        $table = $table->withTotal($total);
        $rows = array_values(Collection::make($rows)->all());
        $lastPage = max(1, (int) ceil($total / $table->perPage));

        return [
            'rows' => $rows,
            'meta' => [
                'page' => $table->page,
                'perPage' => $table->perPage,
                'total' => $total,
                'lastPage' => $lastPage,
                'from' => $total === 0 ? 0 : ($table->page - 1) * $table->perPage + 1,
                'to' => $total === 0 ? 0 : min($table->page * $table->perPage, $total),
            ],
            'query' => $table->toState(),
            'ignored' => $table->ignored,
            'operators' => TableRequest::OPERATORS,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return Collection<int, mixed>
     */
    private static function search(Collection $rows, TableRequest $table): Collection
    {
        if ($table->search === '') {
            return $rows;
        }

        $columns = array_filter($table->columns, fn (TableColumn $c) => $c->isSearchable());

        return $rows->filter(function ($row) use ($columns, $table) {
            foreach ($columns as $column) {
                $cell = data_get($row, $column->key);

                if (self::isEmpty($cell)) {
                    continue;
                }

                if (mb_stripos(self::text($cell), $table->search) !== false) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return Collection<int, mixed>
     */
    private static function filter(Collection $rows, TableRequest $table): Collection
    {
        if ($table->filters === []) {
            return $rows;
        }

        return $rows->filter(function ($row) use ($table) {
            foreach ($table->filters as $filter) {
                if (! self::matches($row, $table->columns[$filter['column']], $filter['operator'], $filter['value'])) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * @param  string|array{0: string, 1: string}  $value
     */
    private static function matches(mixed $row, TableColumn $column, string $operator, string|array $value): bool
    {
        $cell = data_get($row, $column->key);

        if (self::isEmpty($cell)) {
            return false;
        }

        if ($operator === 'contains') {
            return mb_stripos(self::text($cell), (string) $value) !== false;
        }

        if ($operator === 'between') {
            return self::compare($column, $cell, $value[0]) >= 0 && self::compare($column, $cell, $value[1]) <= 0;
        }

        $order = self::compare($column, $cell, $value);

        return match ($operator) {
            'eq' => $order === 0,
            'gt' => $order > 0,
            'gte' => $order >= 0,
            'lt' => $order < 0,
            'lte' => $order <= 0,
            default => false,
        };
    }

    /**
     * @param  Collection<int, mixed>  $rows
     * @return Collection<int, mixed>
     */
    private static function sort(Collection $rows, TableRequest $table): Collection
    {
        if ($table->sort === null) {
            return $rows;
        }

        $column = $table->columns[$table->sort];
        $direction = $table->dir === 'desc' ? -1 : 1;

        $indexed = $rows->map(fn ($row, $index) => [$index, $row, data_get($row, $column->key)])->all();

        usort($indexed, function (array $a, array $b) use ($column, $direction) {
            $emptyA = self::isEmpty($a[2]);
            $emptyB = self::isEmpty($b[2]);

            if ($emptyA || $emptyB) {
                return $emptyA === $emptyB ? $a[0] <=> $b[0] : ($emptyA ? 1 : -1);
            }

            $order = self::compare($column, $a[2], self::text($b[2])) * $direction;

            return $order !== 0 ? $order : $a[0] <=> $b[0];
        });

        return Collection::make(array_column($indexed, 1));
    }

    /**
     * Three-way comparison of a row value with a canonical filter value, by column type.
     */
    private static function compare(TableColumn $column, mixed $cell, string $value): int
    {
        return match ($column->type) {
            'number' => (float) $cell <=> (float) $value,
            'date' => strcmp(substr(self::text($cell), 0, 10), $value),
            'boolean' => (self::boolean($cell) ? 'true' : 'false') <=> (self::boolean($value) ? 'true' : 'false'),
            default => strcmp(mb_strtolower(self::text($cell)), mb_strtolower($value)),
        };
    }

    private static function boolean(mixed $cell): bool
    {
        return filter_var($cell, FILTER_VALIDATE_BOOLEAN);
    }

    private static function text(mixed $cell): string
    {
        return is_bool($cell) ? ($cell ? 'true' : 'false') : (string) $cell;
    }

    private static function isEmpty(mixed $cell): bool
    {
        return $cell === null || $cell === '';
    }
}
