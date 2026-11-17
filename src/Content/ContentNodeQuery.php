<?php

namespace Webtack\Ecms\Content;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webtack\Ecms\Content\FieldTypes\ExpandsFilterValues;
use Webtack\Ecms\Content\FieldTypes\FieldType;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentNode;
use Webtack\Ecms\Models\ContentType;

/**
 * Lists the nodes of one content type, filtering, searching and sorting by dynamic fields in the
 * database (never in PHP) and returning a page of nodes with the values of the visible fields.
 *
 * - Filters are `EXISTS` sub-queries on the value table (any value of a multiple field matches,
 *   conditions are ANDed, each one independently of the others). Operators come from
 *   {@see FieldType::operators()}; `in` matches any of a list of values.
 * - A period keeps the not-past or the past nodes by one date relative to a boundary (see {@see self::applyPeriod()}).
 * - Search is an OR of `LIKE` conditions over text fields, with `%` and `_` escaped. Without an explicit
 *   `searchFields` the node slug is searched too (a type without text fields is searched by slug alone);
 *   an explicit `searchFields` is used exactly as given, with no implicit slug (`[]` matches nothing).
 * - Sorting joins the first value (`weight = 0`) of any field, multiple or not; nodes without a
 *   value come last in both directions. Ties are broken by `title` ascending, then by node id.
 * - The language applies to the filtered/sorted values and to the returned ones; a missing
 *   translation is `null`. With `NodeQueryParams::$fallback` (the public site) returned values and sort
 *   keys fall back to the base language per field; filters and search still look at the requested language.
 *
 * Every returned node has its values in the `values` relation (`$node->values['title']`).
 *
 * @example
 *   $page = app(ContentNodeQuery::class)->paginate($articleType, new NodeQueryParams(
 *       fields: ['title', 'views'],
 *       filters: [['field' => 'views', 'operator' => 'gte', 'value' => 100]],
 *       sort: ['field' => 'views', 'direction' => 'desc'],
 *   ));
 *   $page->total();
 * @example Sorting by a multiple field uses its first value
 *   new NodeQueryParams(sort: ['field' => 'showtimes', 'direction' => 'asc']);
 */
class ContentNodeQuery
{
    /** Escape character for LIKE patterns; identical in MySQL and SQLite, unlike backslash. */
    private const LIKE_ESCAPE = '!';

    /** Service attributes of a node that a list can be sorted by (the slug is not sortable). */
    public const SORTABLE_SERVICE_KEYS = ['status', 'is_active', 'created_at', 'updated_at', 'published_at', 'unpublished_at', 'views'];

    private const COMPARISONS = ['eq' => '=', 'gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<='];

    public function __construct(
        private readonly FieldTypeRegistry $registry,
        private readonly ContentLanguages $languages,
        private readonly ContentValueReader $reader,
        private readonly TranslatedLanguages $translated,
    ) {}

    /**
     * Run the listing.
     *
     * @return LengthAwarePaginator<int, ContentNode>
     *
     * @throws ValidationException For an unknown field, unsupported operator, bad value or language.
     */
    public function paginate(ContentType $type, NodeQueryParams $params): LengthAwarePaginator
    {
        $lang = $this->languages->assertReadable($params->lang);
        $list = $type->settings['list'] ?? [];
        // Only the fields that hold node values: fields of the type itself (`section_*`) are not queryable.
        $fields = ValueOwner::forNodesOfType($type->key)
            ->fields(ContentField::query()->where('content_type', $type->key)->get()->keyBy('key'));

        $visible = $params->fields ?? $list['visible'] ?? ['title'];
        $this->assertKnown($fields, $visible, 'fields');

        $query = ContentNode::query()->where('content_nodes.content_type', $type->key)->select('content_nodes.*');

        // One condition for the page and the counter: the paginator counts the same query.
        if ($params->onlyVisible) {
            $query->visible();
        }

        $this->applyFilters($query, $type, $fields, $params->filters, $lang);
        $this->applyPeriod($query, $type, $fields, $params->period, $lang);
        if ($params->untranslated !== null) {
            $this->translated->withoutTranslation($query, $this->languages->normalize($params->untranslated));
        }
        $this->applySearch($query, $type, $fields, $params, $list, $lang);
        $this->applySort($query, $type, $fields, $params->sort ?? $list['sort'] ?? null, $lang, $params->fallback);

        $perPage = $params->perPage ?? config('content.per_page');

        if ($perPage === 0) {
            // "All": one page holding up to `content.max_all` nodes in the requested order.
            $items = $query->limit(config('content.max_all'))->get();
            $page = new LengthAwarePaginator($items, $items->count(), max($items->count(), 1), 1);
        } else {
            $page = $query->paginate(max(min($perPage, config('content.max_per_page')), 1), page: max($params->page, 1));
        }

        $values = $this->reader->load($page->items(), $visible, $lang, $params->fallback);
        foreach ($page->items() as $node) {
            $node->setRelation('values', $values[$node->id]);
        }

        return $page;
    }

    /**
     * @param  Collection<string, ContentField>  $fields
     * @param  array<int, array{field: string, operator: string, value: mixed}>  $filters
     */
    private function applyFilters(Builder $query, ContentType $type, Collection $fields, array $filters, string $lang): void
    {
        if (count($filters) > config('content.max_filters')) {
            throw ValidationException::withMessages(['filters' => 'Too many filters (maximum '.config('content.max_filters').').']);
        }

        foreach (array_values($filters) as $i => $filter) {
            $field = $fields->get($filter['field'] ?? null);

            if ($field === null) {
                throw ValidationException::withMessages(["filters.{$i}.field" => 'The filter field does not exist in this content type.']);
            }

            $fieldType = $this->registry->get($field->type);
            $operator = $filter['operator'] ?? 'eq';

            if (! in_array($operator, $fieldType->operators(), true)) {
                throw ValidationException::withMessages(["filters.{$i}.operator" => "The operator [{$operator}] is not supported by the field [{$field->key}]."]);
            }

            $value = $this->filterValue($fieldType, $operator, $filter['value'] ?? null, "filters.{$i}.value");

            // A value that stands for more than itself (a term and its descendants) becomes a set.
            if ($fieldType instanceof ExpandsFilterValues) {
                $value = $fieldType->expandFilterValues(is_array($value) ? $value : [$value]);
                $operator = 'in';
            }

            $query->whereExists(fn ($sub) => $this->valueCondition($sub, $type, $field, $fieldType, $lang, $operator, $value));
        }
    }

    /**
     * Keep the not-past (`current`) or the past nodes by one date; the two parts are exact complements.
     *
     * - An added `date` / `datetime` field: not past when ANY value (in the language) is on or after the boundary,
     *   so a multiple field keeps a node that has begun but not ended; a node without a value is past.
     *   A `date` field is compared as a date.
     * - `created_at`: not past when on or after the boundary.
     * - `unpublished_at`: not past when empty ("no end of display") or on or after the boundary.
     *
     * @example
     *   new NodeQueryParams(period: ['field' => 'date', 'part' => 'past', 'since' => now()->startOfDay()]);
     *
     * @param  Collection<string, ContentField>  $fields
     * @param  array{field: string, part: string, since: CarbonInterface}|null  $period
     *
     * @throws ValidationException For a field that is not a date field, `created_at` or `unpublished_at`, or a bad part.
     */
    private function applyPeriod(Builder $query, ContentType $type, Collection $fields, ?array $period, string $lang): void
    {
        if ($period === null) {
            return;
        }

        $key = $period['field'] ?? null;
        $past = ($period['part'] ?? null) === 'past';

        if (! in_array($period['part'] ?? null, ['current', 'past'], true)) {
            throw ValidationException::withMessages(['period.part' => 'The period part must be current or past.']);
        }

        $since = $period['since'];

        if (in_array($key, ['created_at', 'unpublished_at'], true)) {
            $moment = $since->format('Y-m-d H:i:s');
            $column = "content_nodes.{$key}";

            if ($key === 'created_at') {
                $query->where($column, $past ? '<' : '>=', $moment);
            } elseif ($past) {
                $query->whereNotNull($column)->where($column, '<', $moment);
            } else {
                $query->where(fn (Builder $group) => $group->whereNull($column)->orWhere($column, '>=', $moment));
            }

            return;
        }

        $field = is_string($key) ? $fields->get($key) : null;

        if ($field === null || ! in_array($field->type, ['date', 'datetime'], true)) {
            throw ValidationException::withMessages(['period.field' => 'The period field must be a date or datetime field of this content type, created_at, or unpublished_at.']);
        }

        $fieldType = $this->registry->get($field->type);
        $boundary = $fieldType->prepare($since);

        $query->whereExists(
            fn ($sub) => $this->valueCondition($sub, $type, $field, $fieldType, $lang, 'gte', $boundary),
            'and',
            $past,
        );
    }

    /**
     * @param  Collection<string, ContentField>  $fields
     * @param  array<string, mixed>  $list
     */
    private function applySearch(Builder $query, ContentType $type, Collection $fields, NodeQueryParams $params, array $list, string $lang): void
    {
        if ($params->search === null || $params->search === '') {
            return;
        }

        // The slug is searched only on the default path; an explicit `searchFields` is taken exactly as given.
        $withSlug = $params->searchFields === null;

        $keys = $params->searchFields ?? $list['search'] ?? $fields
            ->filter(fn (ContentField $f) => $this->registry->get($f->type)->searchable())
            ->keys()->all();

        $this->assertKnown($fields, $keys, 'searchFields');

        foreach ($keys as $key) {
            if (! $this->registry->get($fields[$key]->type)->searchable()) {
                throw ValidationException::withMessages(['searchFields' => "The field [{$key}] is not a text field and cannot be searched."]);
            }
        }

        if ($keys === [] && ! $withSlug) {
            // Nothing can match: a search over no fields is empty, not unfiltered.
            $query->whereRaw('1 = 0');

            return;
        }

        $term = $this->likePattern($params->search);

        $query->where(function (Builder $group) use ($keys, $fields, $type, $lang, $term, $withSlug) {
            foreach ($keys as $key) {
                $field = $fields[$key];
                $group->orWhereExists(fn ($sub) => $this->valueCondition($sub, $type, $field, $this->registry->get($field->type), $lang, 'contains', $term));
            }

            if ($withSlug) {
                // The slug is a column of the node, outside the value tables.
                $group->orWhereRaw("content_nodes.slug LIKE ? ESCAPE '".self::LIKE_ESCAPE."'", [$term]);
            }
        });
    }

    /**
     * @param  Collection<string, ContentField>  $fields
     * @param  array{field: string, direction?: string}|null  $sort
     */
    private function applySort(Builder $query, ContentType $type, Collection $fields, ?array $sort, string $lang, bool $fallback = false): void
    {
        $serviceField = $sort['field'] ?? null;

        // Service attributes of the node (reserved field keys) are sorted before any field lookup.
        if (in_array($serviceField, self::SORTABLE_SERVICE_KEYS, true)) {
            $direction = strtolower($sort['direction'] ?? 'asc');

            if (! in_array($direction, ['asc', 'desc'], true)) {
                throw ValidationException::withMessages(['sort.direction' => 'The sort direction must be asc or desc.']);
            }

            $this->applyServiceSort($query, $type, $serviceField, $direction, $lang, $fallback);

            return;
        }

        if ($sort !== null) {
            $field = $fields->get($sort['field'] ?? null);
            $direction = strtolower($sort['direction'] ?? 'asc');

            if ($field === null) {
                throw ValidationException::withMessages(['sort.field' => 'The sort field does not exist in this content type.']);
            }

            if (! in_array($direction, ['asc', 'desc'], true)) {
                throw ValidationException::withMessages(['sort.direction' => 'The sort direction must be asc or desc.']);
            }

            $table = $this->registry->get($field->type)->table();
            $fallback = $fallback && $lang !== config('content.default_lang');

            $query->leftJoin("{$table} as sort_value", function ($join) use ($type, $field, $lang) {
                $join->on('sort_value.content_node_id', '=', 'content_nodes.id')
                    ->where('sort_value.content_type', $type->key)
                    ->where('sort_value.field_key', $field->key)
                    ->where('sort_value.lang', $lang)
                    ->where('sort_value.weight', 0);
            });

            // With the fallback a node without a value in the language is ordered by its base value.
            $column = 'sort_value.value';

            if ($fallback) {
                $query->leftJoin("{$table} as sort_base", function ($join) use ($type, $field) {
                    $join->on('sort_base.content_node_id', '=', 'content_nodes.id')
                        ->where('sort_base.content_type', $type->key)
                        ->where('sort_base.field_key', $field->key)
                        ->where('sort_base.lang', config('content.default_lang'))
                        ->where('sort_base.weight', 0);
                });
                $column = 'COALESCE(sort_value.value, sort_base.value)';
            }

            if (in_array($field->type, ['type_ref', 'node_ref'], true)) {
                // A reference is ordered by what the cell shows (the target's label), not by its key or uuid.
                $label = $this->joinReferenceLabel($query, $field->type, $column);
                $query->orderByRaw("({$label} IS NULL) asc")->orderBy(DB::raw($label), $direction);
            } else {
                // Nodes without a value go last whatever the direction.
                $query->orderByRaw("({$column} IS NULL) asc")->orderBy(DB::raw($column), $direction);
            }
            $this->applyTitleOrder($query, $type, $lang, $fallback);
        }

        $query->orderBy('content_nodes.id');
    }

    /**
     * Join the label of the first value (`sort_value`) of a `type_ref` / `node_ref` field and return its SQL column:
     * the target type's `name`, or the target node's `title` in the default language. A value whose target is gone
     * has no label, so it sorts like an empty one.
     */
    private function joinReferenceLabel(Builder $query, string $fieldType, string $valueColumn = 'sort_value.value'): string
    {
        if ($fieldType === 'type_ref') {
            $query->leftJoin('content_types as sort_target', 'sort_target.key', '=', DB::raw($valueColumn));

            return 'sort_target.name';
        }

        $query->leftJoin('content_nodes as sort_target', 'sort_target.uuid', '=', DB::raw($valueColumn))
            ->leftJoin('content_values_string as sort_label', function ($join) {
                $join->on('sort_label.content_node_id', '=', 'sort_target.id')
                    ->whereColumn('sort_label.content_type', 'sort_target.content_type')
                    ->where('sort_label.field_key', 'title')
                    ->where('sort_label.lang', config('content.default_lang'))
                    ->where('sort_label.weight', 0);
            });

        return 'sort_label.value';
    }

    /**
     * Sort by a service attribute of the node.
     *
     * - `created_at`, `updated_at`, `unpublished_at`: by the column, nodes without a value last in
     *   both directions, ties by node id in the same direction (never by `title`).
     * - `published_at`, `status`, `is_active`, `views`: by the key (`views` is an integer column, so it is numeric, not
     *   textual), ties by `title` ascending, then node id.
     *   `status` orders published, scheduled, expired, disabled (see {@see self::statusOrderExpression()}).
     */
    private function applyServiceSort(Builder $query, ContentType $type, string $key, string $direction, string $lang, bool $fallback = false): void
    {
        if (in_array($key, ['created_at', 'updated_at', 'unpublished_at'], true)) {
            $query->orderByRaw("(content_nodes.{$key} IS NULL) asc")
                ->orderBy("content_nodes.{$key}", $direction)
                ->orderBy('content_nodes.id', $direction);

            return;
        }

        if ($key === 'status') {
            [$sql, $bindings] = $this->statusOrderExpression();
            $query->orderByRaw("{$sql} {$direction}", $bindings);
        } else {
            $query->orderBy("content_nodes.{$key}", $direction);
        }

        $this->applyTitleOrder($query, $type, $lang, $fallback && $lang !== config('content.default_lang'));
        $query->orderBy('content_nodes.id');
    }

    /**
     * SQL `CASE` giving the position of a node's derived state, the same rules as
     * {@see ContentNode::status()}: published 0, scheduled 1, expired 2, disabled 3.
     *
     * @return array{0: string, 1: array<int, string>} SQL and its bindings
     */
    public function statusOrderExpression(): array
    {
        $now = now()->format('Y-m-d H:i:s');

        return [
            'CASE WHEN content_nodes.is_active = 0 THEN 3'
            .' WHEN content_nodes.published_at > ? THEN 1'
            .' WHEN content_nodes.unpublished_at IS NOT NULL AND content_nodes.unpublished_at <= ? THEN 2'
            .' ELSE 0 END',
            [$now, $now],
        ];
    }

    /**
     * Secondary order of an explicit sort: `title` ascending in the requested language, nodes without
     * a `title` in that language after those that have one (no fallback to the default language unless
     * `$fallback`, then the base title stands in for a missing one).
     */
    private function applyTitleOrder(Builder $query, ContentType $type, string $lang, bool $fallback = false): void
    {
        $table = $this->registry->get('string')->table();

        $query->leftJoin("{$table} as title_value", function ($join) use ($type, $lang) {
            $join->on('title_value.content_node_id', '=', 'content_nodes.id')
                ->where('title_value.content_type', $type->key)
                ->where('title_value.field_key', 'title')
                ->where('title_value.lang', $lang)
                ->where('title_value.weight', 0);
        });

        $column = 'title_value.value';

        if ($fallback) {
            $query->leftJoin("{$table} as title_base", function ($join) use ($type) {
                $join->on('title_base.content_node_id', '=', 'content_nodes.id')
                    ->where('title_base.content_type', $type->key)
                    ->where('title_base.field_key', 'title')
                    ->where('title_base.lang', config('content.default_lang'))
                    ->where('title_base.weight', 0);
            });
            $column = 'COALESCE(title_value.value, title_base.value)';
        }

        $query->orderByRaw("({$column} IS NULL) asc")->orderBy(DB::raw($column));
    }

    /**
     * Build the EXISTS body: a value of the field in the language satisfying the condition.
     */
    private function valueCondition(BuilderContract|\Illuminate\Database\Query\Builder $sub, ContentType $type, ContentField $field, FieldType $fieldType, string $lang, string $operator, mixed $value): void
    {
        $table = $fieldType->table();

        $sub->select(DB::raw(1))
            ->from($table)
            ->whereColumn("{$table}.content_node_id", 'content_nodes.id')
            ->where("{$table}.content_type", $type->key)
            ->where("{$table}.field_key", $field->key)
            ->where("{$table}.lang", $lang);

        match (true) {
            $operator === 'between' => $sub->whereBetween("{$table}.value", $value),
            $operator === 'in' => $sub->whereIn("{$table}.value", $value),
            $operator === 'contains' => $sub->whereRaw("{$table}.value LIKE ? ESCAPE '".self::LIKE_ESCAPE."'", [$value]),
            default => $sub->where("{$table}.value", self::COMPARISONS[$operator], $value),
        };
    }

    /**
     * Validate a filter value with the field type's rules and convert it for the database.
     */
    private function filterValue(FieldType $fieldType, string $operator, mixed $value, string $attribute): mixed
    {
        // Bounds from the field settings limit stored values, not what may be searched for.
        $rules = $fieldType->rules(new ContentField);

        if ($operator === 'between') {
            if (! is_array($value) || count($value) !== 2) {
                throw ValidationException::withMessages([$attribute => 'A range filter needs exactly two values.']);
            }

            Validator::make(['v' => array_values($value)], ['v.*' => $rules])->validate();

            return array_map($fieldType->prepare(...), array_values($value));
        }

        if ($operator === 'in') {
            if (! is_array($value) || $value === []) {
                throw ValidationException::withMessages([$attribute => 'An in filter needs a non-empty list of values.']);
            }

            $validator = Validator::make(['v' => array_values($value)], ['v.*' => ['required', ...$rules]]);

            if ($validator->fails()) {
                throw ValidationException::withMessages([$attribute => $validator->errors()->first()]);
            }

            return array_map($fieldType->prepare(...), array_values($value));
        }

        if ($operator === 'contains') {
            Validator::make(['v' => $value], ['v' => ['required', 'string']])->validate();

            return $this->likePattern($value);
        }

        $validator = Validator::make(['v' => $value], ['v' => ['required', ...$rules]]);

        if ($validator->fails()) {
            throw ValidationException::withMessages([$attribute => $validator->errors()->first('v')]);
        }

        return $fieldType->prepare($value);
    }

    /**
     * `%term%` with LIKE wildcards in the term escaped.
     */
    private function likePattern(string $term): string
    {
        $e = self::LIKE_ESCAPE;

        return '%'.str_replace([$e, '%', '_'], [$e.$e, $e.'%', $e.'_'], $term).'%';
    }

    /**
     * @param  Collection<string, ContentField>  $fields
     * @param  array<int, string>  $keys
     */
    private function assertKnown(Collection $fields, array $keys, string $attribute): void
    {
        if ($unknown = array_diff($keys, $fields->keys()->all())) {
            throw ValidationException::withMessages([$attribute => 'Unknown fields for the content type: '.implode(', ', $unknown).'.']);
        }
    }
}
