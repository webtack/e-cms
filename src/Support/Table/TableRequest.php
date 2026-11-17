<?php

namespace Webtack\Ecms\Support\Table;

use Illuminate\Http\Request;

/**
 * Normalized table state parsed from a request address (the server half of the `DataTable` contract).
 *
 * Address parameters: `q` (search), `filters[N][c|o|v]` (column, operator, value; `between` takes
 * `v` as a two-item array), `sort` + `dir` (`asc|desc`), `page`, `per_page` (15, 30, 50 or 100).
 *
 * Invalid input never fails the request: it is dropped, recorded in `$ignored` as
 * `['param' => ..., 'reason' => ...]` and the rest is applied. A `per_page` above the maximum is
 * capped silently. The class runs no queries; the controller applies the state to its own source
 * (Eloquent, `ContentNodeQuery`) or hands a collection to `TablePage::fromCollection()`.
 *
 * @example
 *   // GET /items?q=lamp&filters[0][c]=price&filters[0][o]=gte&filters[0][v]=100&sort=price&dir=desc&page=2
 *   $table = TableRequest::from($request, [
 *       ['key' => 'name', 'type' => 'text', 'sortable' => true],
 *       ['key' => 'price', 'type' => 'number', 'sortable' => true, 'filterable' => true],
 *   ]);
 *   $table->search;   // 'lamp'
 *   $table->filters;  // [['column' => 'price', 'operator' => 'gte', 'value' => '100']]
 *   $table->sort;     // 'price'
 *   $table->dir;      // 'desc'
 *   $table->page;     // 2
 */
final class TableRequest
{
    /** Operators allowed for each column type; the single source of truth sent to the UI. */
    public const OPERATORS = [
        'text' => ['eq', 'contains'],
        'number' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
        'date' => ['eq', 'gt', 'gte', 'lt', 'lte', 'between'],
        'boolean' => ['eq'],
        'select' => ['eq'],
    ];

    public const PER_PAGE_OPTIONS = [15, 30, 50, 100];

    public const DEFAULT_PER_PAGE = 15;

    public const MAX_PER_PAGE = 100;

    public const MAX_FILTERS = 10;

    /**
     * @param  array<string, TableColumn>  $columns  By key.
     * @param  list<array{column: string, operator: string, value: string|array{0: string, 1: string}}>  $filters
     * @param  list<array{param: string, reason: string}>  $ignored
     */
    private function __construct(
        public readonly array $columns,
        public readonly string $search,
        public readonly array $filters,
        public readonly ?string $sort,
        public readonly ?string $dir,
        public readonly int $page,
        public readonly int $perPage,
        public readonly array $ignored,
    ) {}

    /**
     * @param  list<TableColumn|array<string, mixed>>  $columns
     */
    public static function from(Request $request, array $columns): self
    {
        $byKey = [];

        foreach ($columns as $column) {
            $column = $column instanceof TableColumn ? $column : TableColumn::make($column);
            $byKey[$column->key] = $column;
        }

        $ignored = [];

        $search = self::parseSearch($request->query('q'), $ignored);
        $filters = self::parseFilters($request->query('filters'), $byKey, $ignored);
        [$sort, $dir] = self::parseSort($request->query('sort'), $request->query('dir'), $byKey, $ignored);
        $page = self::parsePage($request->query('page'), $ignored);
        $perPage = self::parsePerPage($request->query('per_page'), $ignored);

        return new self($byKey, $search, $filters, $sort, $dir, $page, $perPage, $ignored);
    }

    /**
     * A copy whose page is brought into range for `$total` rows (the last page when too high).
     */
    public function withTotal(int $total): self
    {
        $page = min($this->page, max(1, (int) ceil($total / $this->perPage)));

        return new self($this->columns, $this->search, $this->filters, $this->sort, $this->dir, $page, $this->perPage, $this->ignored);
    }

    /**
     * The state in the shape of the TypeScript `TableState` (camelCase).
     *
     * @return array{search: string, filters: list<array<string, mixed>>, sort: ?string, dir: ?string, page: int, perPage: int}
     */
    public function toState(): array
    {
        return [
            'search' => $this->search,
            'filters' => $this->filters,
            'sort' => $this->sort,
            'dir' => $this->dir,
            'page' => $this->page,
            'perPage' => $this->perPage,
        ];
    }

    /**
     * @param  list<array{param: string, reason: string}>  $ignored
     */
    private static function parseSearch(mixed $value, array &$ignored): string
    {
        if ($value === null) {
            return '';
        }

        if (! is_string($value)) {
            $ignored[] = ['param' => 'q', 'reason' => 'invalid_value'];

            return '';
        }

        return trim($value);
    }

    /**
     * @param  array<string, TableColumn>  $columns
     * @param  list<array{param: string, reason: string}>  $ignored
     * @return list<array{column: string, operator: string, value: string|array{0: string, 1: string}}>
     */
    private static function parseFilters(mixed $raw, array $columns, array &$ignored): array
    {
        if ($raw === null) {
            return [];
        }

        if (! is_array($raw)) {
            $ignored[] = ['param' => 'filters', 'reason' => 'invalid_value'];

            return [];
        }

        $filters = [];
        $count = 0;

        foreach ($raw as $index => $item) {
            $param = "filters[{$index}]";

            if (++$count > self::MAX_FILTERS) {
                $ignored[] = ['param' => $param, 'reason' => 'too_many_filters'];

                continue;
            }

            $filter = self::parseFilter($item, $columns, $reason);

            if ($filter === null) {
                $ignored[] = ['param' => $param, 'reason' => $reason];

                continue;
            }

            $filters[] = $filter;
        }

        return $filters;
    }

    /**
     * @param  array<string, TableColumn>  $columns
     * @return array{column: string, operator: string, value: string|array{0: string, 1: string}}|null
     */
    private static function parseFilter(mixed $item, array $columns, ?string &$reason): ?array
    {
        $column = is_array($item) ? ($item['c'] ?? null) : null;
        $operator = is_array($item) ? ($item['o'] ?? null) : null;
        $value = is_array($item) ? ($item['v'] ?? null) : null;

        if (! is_string($column) || ! isset($columns[$column])) {
            $reason = 'unknown_column';

            return null;
        }

        $definition = $columns[$column];

        if (! $definition->filterable) {
            $reason = 'not_filterable';

            return null;
        }

        if (! is_string($operator) || ! in_array($operator, self::OPERATORS[$definition->type], true)) {
            $reason = 'invalid_operator';

            return null;
        }

        if ($operator === 'between') {
            $from = is_array($value) ? self::normalizeValue($definition, $value[0] ?? null) : null;
            $to = is_array($value) ? self::normalizeValue($definition, $value[1] ?? null) : null;

            if ($from === null || $to === null) {
                $reason = 'invalid_value';

                return null;
            }

            return ['column' => $column, 'operator' => $operator, 'value' => [$from, $to]];
        }

        $normalized = self::normalizeValue($definition, $value);

        if ($normalized === null) {
            $reason = 'invalid_value';

            return null;
        }

        return ['column' => $column, 'operator' => $operator, 'value' => $normalized];
    }

    /**
     * Validates a filter value against the column type; returns the canonical string or null.
     * Booleans become `true` / `false`; dates must be `YYYY-MM-DD`.
     */
    private static function normalizeValue(TableColumn $column, mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return match ($column->type) {
            'number' => is_numeric($value) && is_finite((float) $value) ? $value : null,
            'date' => self::isIsoDate($value) ? $value : null,
            'boolean' => match ($value) {
                'true', '1' => 'true',
                'false', '0' => 'false',
                default => null,
            },
            'select' => $column->options === [] || in_array($value, $column->options, true) ? $value : null,
            default => $value,
        };
    }

    private static function isIsoDate(string $value): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /**
     * @param  array<string, TableColumn>  $columns
     * @param  list<array{param: string, reason: string}>  $ignored
     * @return array{0: ?string, 1: ?string}
     */
    private static function parseSort(mixed $sort, mixed $dir, array $columns, array &$ignored): array
    {
        if ($sort === null) {
            if ($dir !== null) {
                $ignored[] = ['param' => 'dir', 'reason' => 'sort_missing'];
            }

            return [null, null];
        }

        if (! is_string($sort) || ! isset($columns[$sort])) {
            $ignored[] = ['param' => 'sort', 'reason' => 'unknown_column'];

            return [null, null];
        }

        if (! $columns[$sort]->sortable) {
            $ignored[] = ['param' => 'sort', 'reason' => 'not_sortable'];

            return [null, null];
        }

        if ($dir === null) {
            return [$sort, 'asc'];
        }

        if ($dir !== 'asc' && $dir !== 'desc') {
            $ignored[] = ['param' => 'dir', 'reason' => 'invalid_direction'];

            return [null, null];
        }

        return [$sort, $dir];
    }

    /**
     * @param  list<array{param: string, reason: string}>  $ignored
     */
    private static function parsePage(mixed $value, array &$ignored): int
    {
        if ($value === null) {
            return 1;
        }

        $page = self::toInt($value);

        if ($page === null || $page < 1) {
            $ignored[] = ['param' => 'page', 'reason' => 'invalid_value'];

            return 1;
        }

        return $page;
    }

    /**
     * @param  list<array{param: string, reason: string}>  $ignored
     */
    private static function parsePerPage(mixed $value, array &$ignored): int
    {
        if ($value === null) {
            return self::DEFAULT_PER_PAGE;
        }

        $perPage = self::toInt($value);

        if ($perPage !== null && $perPage > self::MAX_PER_PAGE) {
            return self::MAX_PER_PAGE;
        }

        if ($perPage === null || ! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $ignored[] = ['param' => 'per_page', 'reason' => 'invalid_value'];

            return self::DEFAULT_PER_PAGE;
        }

        return $perPage;
    }

    private static function toInt(mixed $value): ?int
    {
        return is_string($value) && preg_match('/^\d{1,9}$/', $value) === 1 ? (int) $value : null;
    }
}
