<?php

namespace Webtack\Ecms\Content;

use Illuminate\Support\Collection;
use Webtack\Ecms\Content\FieldTypes\AbstractFileFieldType;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;

/**
 * Reads the list settings of a content type (`settings.list`: `visible`, `search`, `sort`) for the screens that
 * show them: the list of nodes and the type form's "Node list" section both build their view from here, so the
 * list and the form cannot disagree about which keys are valid.
 *
 * A key of `visible` is a field of the type that holds node values or a service key of the node
 * ({@see ContentType::LIST_SERVICE_KEYS}); a field named like a service key is the service column. `title` is
 * always shown: at the position of its key in `visible`, or first when the key is missing. `sort` takes a sortable field or a sortable service key (never the slug).
 *
 * @example
 *   $settings = app(NodeListSettings::class);
 *   $fields = ValueOwner::forNodesOfType($type->key)->fields($type->fields->keyBy('key'));
 *   $settings->clean($type, $fields)['visible']; // ['title', 'price', 'status']
 */
class NodeListSettings
{
    /**
     * Service columns: key => [English header (translated by the client), table column type].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const SERVICE_COLUMNS = [
        'slug' => ['Slug', 'text'],
        'status' => ['State', 'text'],
        'is_active' => ['Active', 'boolean'],
        'created_at' => ['Creation date', 'date'],
        'updated_at' => ['Update date', 'date'],
        'published_at' => ['Publication date', 'date'],
        'unpublished_at' => ['Unpublication date', 'date'],
        'views' => ['Views', 'number'],
        'author' => ['Author', 'text'],
    ];

    /** Columns shown after `title` when the type lists none. */
    public const DEFAULT_SERVICE_COLUMNS = ['slug', 'status', 'published_at', 'author'];

    public function __construct(private readonly FieldTypeRegistry $registry) {}

    /** Whether the key is a service column of the list (a reserved key never means a field). */
    public function isServiceKey(string $key): bool
    {
        return isset(self::SERVICE_COLUMNS[$key]);
    }

    /**
     * Whether a list can be sorted by the field. A file column holds opaque `{uuid}.{ext}` strings and a `youtube`
     * or `url` one a link, so ordering by them means nothing, and so does a `term` one (a uuid, not the name the cell shows).
     * A multiple field is sortable: its first value (`weight = 0`) is the key.
     */
    public function sortable(ContentField $field): bool
    {
        return ! $this->registry->get($field->type) instanceof AbstractFileFieldType
            && ! in_array($field->type, ['youtube', 'url', 'term'], true);
    }

    /**
     * The settings of the list with every reference to a missing field removed.
     *
     * `visible` holds `title` (at its own position, first when its key is missing) and, in the stored order,
     * existing fields and the service keys. Without any column besides `title` the default service columns apply. `search` keeps only text fields; `sort`
     * a sortable field or a sortable service key. An emptied `search` or `sort` is dropped so that the defaults
     * apply.
     *
     * @param  Collection<string, ContentField>  $fields  The fields that hold node values, by key.
     * @return array{visible: Collection<int, string>, search?: list<string>, sort?: array<string, mixed>}
     *
     * @example
     *   // settings.list.visible = ['price', 'gone', 'status'] and no field `gone` → visible = ['title', 'price', 'status']
     *   // settings.list.visible = ['price', 'title'] → visible = ['price', 'title']
     *   // settings.list.visible missing → visible = ['title', 'slug', 'status', 'published_at']
     */
    public function clean(ContentType $type, Collection $fields): array
    {
        $list = $type->settings['list'] ?? [];

        $visible = collect($list['visible'] ?? [])
            ->filter(fn ($key) => is_string($key) && ($key === 'title' || $this->isServiceKey($key) || ($fields->has($key) && ! in_array($key, ContentField::RESERVED_KEYS, true))))
            ->unique()
            ->values();

        // The default applies to a list that names no column besides `title`; a list of stale keys only keeps `title`.
        if (collect($list['visible'] ?? [])->reject(fn ($key) => $key === 'title')->isEmpty()) {
            // Nothing but `title` (or nothing at all) is listed: the default columns.
            $visible = collect(['title', ...self::DEFAULT_SERVICE_COLUMNS]);
        } elseif (! $visible->contains('title')) {
            $visible->prepend('title');
        }

        $settings = ['visible' => $visible];

        $search = collect($list['search'] ?? [])
            ->filter(fn ($key) => is_string($key) && $fields->has($key) && $this->registry->get($fields[$key]->type)->searchable())
            ->values()
            ->all();

        if ($search !== []) {
            $settings['search'] = $search;
        }

        $sort = $list['sort'] ?? null;
        $sortField = is_array($sort) && is_string($sort['field'] ?? null) ? $sort['field'] : null;

        if ($sortField !== null && (
            in_array($sortField, ContentType::LIST_SORT_SERVICE_KEYS, true)
            || ($fields->has($sortField) && $this->sortable($fields[$sortField]))
        )) {
            $settings['sort'] = $sort;
        }

        return $settings;
    }

    /**
     * What the type form's "Node list" section offers and its current (cleaned) state.
     *
     * - `columns`: every choosable column, fields first and then the service ones, as `{key, name, service}`
     *   (`title` is among the fields: always shown, only its position can change; a service `name` is an
     *   English translation key, a field's name is data).
     * - `searchFields`: the text fields searched.
     * - `sortOptions`: the sortable service keys and fields, `{key, name, service}`.
     * - `current`: `visible` (with `title` at its position), `search` and `sort` with the defaults resolved: the default service
     *   columns, all text fields and "creation date, ascending".
     *
     * @param  Collection<string, ContentField>  $fields  The fields that hold node values, by key.
     * @return array{columns: list<array<string, mixed>>, searchFields: list<array<string, mixed>>, sortOptions: list<array<string, mixed>>, current: array<string, mixed>}
     */
    public function formOptions(ContentType $type, Collection $fields): array
    {
        $clean = $this->clean($type, $fields);
        // A field with a reserved key is the service column of that key, never a field column.
        $choosable = $fields->reject(fn (ContentField $f) => in_array($f->key, ContentField::RESERVED_KEYS, true));
        $field = fn (ContentField $f) => ['key' => $f->key, 'name' => $f->name, 'service' => false];
        $service = fn (string $key) => ['key' => $key, 'name' => self::SERVICE_COLUMNS[$key][0], 'service' => true];
        $searchable = $fields->filter(fn (ContentField $f) => $this->registry->get($f->type)->searchable());

        return [
            'columns' => [
                ...$choosable->map($field)->values()->all(),
                ...array_map($service, ContentType::LIST_SERVICE_KEYS),
            ],
            'searchFields' => $searchable->map($field)->values()->all(),
            'sortOptions' => [
                ...array_map($service, ContentType::LIST_SORT_SERVICE_KEYS),
                ...$fields->filter(fn (ContentField $f) => $this->sortable($f))
                    ->reject(fn (ContentField $f) => in_array($f->key, ContentField::RESERVED_KEYS, true))
                    ->map($field)->values()->all(),
            ],
            'current' => [
                'visible' => $clean['visible']->values()->all(),
                'search' => $clean['search'] ?? $searchable->keys()->all(),
                'sort' => [
                    'field' => $clean['sort']['field'] ?? 'created_at',
                    'direction' => $clean['sort']['direction'] ?? 'asc',
                ],
            ],
        ];
    }
}
