<?php

namespace Webtack\Ecms\Content;

use Illuminate\Support\Collection;
use Webtack\Ecms\Models\ContentField;
use Webtack\Ecms\Models\ContentType;

/**
 * Reads the public display settings of a content type (`settings.public`): which added fields a list card and
 * a node page show, how the public list is sorted, how many nodes it holds and whether it has a paginator. The
 * type form's "Public display" section and the (future) templates both read through here, so they cannot disagree
 * about which keys are valid or what a missing key means.
 *
 * "Added" fields are the user's own fields (`is_system = false`) that hold node values and have no reserved key.
 * `title`, the service attributes of a node, `meta_*` and `section_*` values are always available to a template and
 * are never stored here. `list_fields`, `sort`, `per_page` and `pagination` exist for kind `content` only (only it
 * has a public list); `page_fields` exists for every kind. `meta` (kinds `content` and `page`; a block has no own
 * address) picks one added field each for the page's `title`, `description` and `image` meta data: a `string`
 * field for the title, a `text` or `richtext` one for the description and an `image` or `youtube` one for the image
 * ({@see self::META_TYPES}). A missing key means "not chosen". The "SEO" tab edits `meta` ({@see self::metaOptions()}), the "Display" tab edits the rest.
 *
 * `archive` (kind `content` only) splits the public list into the main list and the archive by one date: `field` is
 * an added `date` / `datetime` field or a service date ({@see self::ARCHIVE_SERVICE_KEYS}), `sort` is the sort of
 * the archive list; without a valid `field` there is no archive.
 *
 * A missing `page_fields` means "all added fields" at read time, so a new field shows up without touching the
 * settings; a stored one (even empty) is a list. A missing `list_fields` means `title` only.
 *
 * @example
 *   $settings = app(PublicDisplaySettings::class);
 *   $fields = ValueOwner::forNodesOfType($type->key)->fields($type->fields->keyBy('key'));
 *   $settings->resolve($type, $fields);
 *   // ['list_fields' => ['title'], 'page_fields' => ['price', 'color'], 'sort' => ['field' => 'published_at',
 *   //  'direction' => 'desc'], 'per_page' => 0, 'pagination' => false]
 */
class PublicDisplaySettings
{
    /** Sort of a public list without settings: the newest publication first. */
    public const DEFAULT_SORT = ['field' => 'published_at', 'direction' => 'desc'];

    /** Keys of `settings.public` that only a list (kind `content`) has. */
    public const LIST_KEYS = ['list_fields', 'sort', 'per_page', 'pagination', 'archive'];

    /** Service dates a list can be split into "not past" and "past" by (`settings.public.archive.field`), besides added date fields. */
    public const ARCHIVE_SERVICE_KEYS = ['unpublished_at', 'created_at'];

    /** Field types an archive can be split by. */
    public const ARCHIVE_TYPES = ['date', 'datetime'];

    /** The meta data a type can take from its fields (`settings.public.meta`) and the field types each may use. */
    public const META_TYPES = [
        'title' => ['string'],
        'description' => ['text', 'richtext'],
        'image' => ['image', 'youtube'],
    ];

    public function __construct(private readonly NodeListSettings $list) {}

    /**
     * The added fields among the fields that hold node values, in the order of the form.
     *
     * @param  Collection<string, ContentField>  $fields  The fields that hold node values, by key.
     * @return Collection<string, ContentField>
     */
    public function addedFields(Collection $fields): Collection
    {
        return $fields
            ->reject(fn (ContentField $f) => $f->is_system || in_array($f->key, ContentField::RESERVED_KEYS, true))
            ->sortBy('weight');
    }

    /**
     * The stored settings with every reference to a missing field removed, and nothing but the stored keys.
     *
     * Keys a kind does not have are dropped; field lists keep only added fields, in the stored order; `sort`
     * keeps a sortable added field or a sortable service key; `per_page` must be within `[0, max_per_page]`; `meta`
     * keeps the chosen fields that still exist, are added and still have an allowed type (none for a block).
     *
     * @param  Collection<string, ContentField>  $fields  The fields that hold node values, by key.
     * @return array<string, mixed>
     *
     * @example
     *   // settings.public = ['list_fields' => ['price', 'gone'], 'per_page' => 500] and no field `gone` → ['list_fields' => ['price']]
     *   // settings.public = ['meta' => ['description' => 'price']] and `price` is an integer → no `meta`
     */
    public function clean(ContentType $type, Collection $fields): array
    {
        $public = $type->settings['public'] ?? [];
        $added = $this->addedFields($fields);
        $keep = fn (mixed $keys) => collect(is_array($keys) ? $keys : [])
            ->filter(fn ($key) => is_string($key) && $added->has($key))->unique()->values()->all();
        $clean = [];

        if (is_array($public['page_fields'] ?? null)) {
            $clean['page_fields'] = $keep($public['page_fields']);
        }

        if ($type->kind !== 'block' && $meta = $this->cleanMeta($public['meta'] ?? null, $added)) {
            $clean['meta'] = $meta;
        }

        if ($type->kind !== 'content') {
            return $clean;
        }

        if (is_array($public['list_fields'] ?? null)) {
            $clean['list_fields'] = $keep($public['list_fields']);
        }

        if ($sort = $this->cleanSort($public['sort'] ?? null, $added)) {
            $clean['sort'] = $sort;
        }

        if ($archive = $this->cleanArchive($public['archive'] ?? null, $added)) {
            $clean['archive'] = $archive;
        }

        $perPage = $public['per_page'] ?? null;

        if (is_int($perPage) && $perPage >= 0 && $perPage <= config('content.max_per_page')) {
            $clean['per_page'] = $perPage;
        }

        if (is_bool($public['pagination'] ?? null)) {
            $clean['pagination'] = $public['pagination'];
        }

        return $clean;
    }

    /**
     * A stored sort when it still names a sortable service key or added field; the direction defaults to `desc`.
     *
     * @param  Collection<string, ContentField>  $added
     * @return array{field: string, direction: string}|null
     */
    private function cleanSort(mixed $sort, Collection $added): ?array
    {
        $field = is_array($sort) && is_string($sort['field'] ?? null) ? $sort['field'] : null;

        if ($field === null || ! (
            in_array($field, ContentType::LIST_SORT_SERVICE_KEYS, true)
            || ($added->has($field) && $this->list->sortable($added[$field]))
        )) {
            return null;
        }

        return ['field' => $field, 'direction' => ($sort['direction'] ?? null) === 'asc' ? 'asc' : 'desc'];
    }

    /**
     * The stored archive: dropped as a whole when its `field` is gone or is no longer a date; a stale `sort` is
     * dropped alone (the archive then sorts like the main list).
     *
     * @param  Collection<string, ContentField>  $added
     * @return array{field: string, sort?: array{field: string, direction: string}}|null
     */
    private function cleanArchive(mixed $archive, Collection $added): ?array
    {
        $field = is_array($archive) && is_string($archive['field'] ?? null) ? $archive['field'] : null;

        if ($field === null || ! (
            in_array($field, self::ARCHIVE_SERVICE_KEYS, true)
            || ($added->has($field) && in_array($added[$field]->type, self::ARCHIVE_TYPES, true))
        )) {
            return null;
        }

        $sort = $this->cleanSort($archive['sort'] ?? null, $added);

        return ['field' => $field, ...($sort ? ['sort' => $sort] : [])];
    }

    /**
     * The chosen meta fields that are still valid: added fields whose type the meta data allows.
     *
     * @param  Collection<string, ContentField>  $added
     * @return array<string, string> Meta name (`title`, `description`, `image`) => field key.
     */
    private function cleanMeta(mixed $meta, Collection $added): array
    {
        $clean = [];

        foreach (self::META_TYPES as $name => $types) {
            $key = is_array($meta) ? ($meta[$name] ?? null) : null;

            if (is_string($key) && $added->has($key) && in_array($added[$key]->type, $types, true)) {
                $clean[$name] = $key;
            }
        }

        return $clean;
    }

    /**
     * What a template needs: the cleaned settings with the defaults applied.
     *
     * - `page_fields`: the stored list, or every added field when the key is missing.
     * - `list_fields`: `title` first, then the stored fields (kind `content` only).
     * - `meta`: the chosen meta fields by name, only when at least one is chosen (never for a block).
     * - `archive`: `{field, sort}` when the type has a valid archive (kind `content` only); an absent archive sort is
     *   the effective main sort. The key is missing without an archive.
     * - `sort`, `per_page`, `pagination`: the stored values or `published_at desc`, `0` (all) and `true`; the
     *   effective `pagination` is `false` while `per_page` is `0`, whatever is stored (kind `content` only).
     *
     * @param  Collection<string, ContentField>  $fields  The fields that hold node values, by key.
     * @return array{page_fields: list<string>, meta?: array<string, string>, list_fields?: list<string>, sort?: array{field: string, direction: string}, archive?: array{field: string, sort: array{field: string, direction: string}}, per_page?: int, pagination?: bool}
     *
     * @example
     *   // no settings.public, fields price + color → page_fields ['price', 'color'], list_fields ['title'], per_page 0, pagination false
     *   // settings.public = ['per_page' => 10] → per_page 10, pagination true
     */
    public function resolve(ContentType $type, Collection $fields): array
    {
        $clean = $this->clean($type, $fields);
        $resolved = ['page_fields' => $clean['page_fields'] ?? $this->addedFields($fields)->keys()->all()];

        if (isset($clean['meta'])) {
            $resolved['meta'] = $clean['meta'];
        }

        if ($type->kind !== 'content') {
            return $resolved;
        }

        $perPage = $clean['per_page'] ?? 0;

        $sort = $clean['sort'] ?? self::DEFAULT_SORT;

        return [
            ...$resolved,
            'list_fields' => ['title', ...($clean['list_fields'] ?? [])],
            'sort' => $sort,
            ...(isset($clean['archive']) ? ['archive' => ['field' => $clean['archive']['field'], 'sort' => $clean['archive']['sort'] ?? $sort]] : []),
            'per_page' => $perPage,
            'pagination' => $perPage > 0 && ($clean['pagination'] ?? true),
        ];
    }

    /**
     * What the type form's "Public display" section offers and its current state.
     *
     * - `fields`: the added fields, `{key, name}`.
     * - `sortOptions`: the sortable service keys (an English translation key as `name`) and added fields,
     *   `{key, name, service}`; empty for kinds without a list.
     * - `current`: the stored state with the defaults resolved for the form: `all_page_fields` (the key is missing),
     *   `page_fields`, and for kind `content` `list_fields`, `sort`, `per_page`, and the stored `pagination` (not
     *   the effective one, so that returning to `per_page > 0` restores the choice).
     * - `archiveOptions`: the dates the list can be split by, `{key, name, service}`: the service dates and the added
     *   `date` / `datetime` fields; empty for kinds without a list. `current.archive` is `{field, sort}` (the stored
     *   `sort`, else the main one) or `null` while there is no archive.
     * - `maxPerPage`: the upper bound of `per_page`.
     * The meta data choice is not part of it: it lives on the "SEO" tab ({@see self::metaOptions()}).
     *
     * @param  Collection<string, ContentField>  $fields  The fields that hold node values, by key.
     * @return array<string, mixed>
     */
    public function formOptions(ContentType $type, Collection $fields): array
    {
        $clean = $this->clean($type, $fields);
        $added = $this->addedFields($fields);
        $field = fn (ContentField $f) => ['key' => $f->key, 'name' => $f->name, 'service' => false];
        $service = fn (string $key) => ['key' => $key, 'name' => NodeListSettings::SERVICE_COLUMNS[$key][0], 'service' => true];
        $isList = $type->kind === 'content';

        $current = [
            'all_page_fields' => ! array_key_exists('page_fields', $clean),
            'page_fields' => $clean['page_fields'] ?? $added->keys()->all(),
        ];

        if ($isList) {
            $sort = $clean['sort'] ?? self::DEFAULT_SORT;
            $current += [
                'list_fields' => $clean['list_fields'] ?? [],
                'sort' => $sort,
                'per_page' => $clean['per_page'] ?? 0,
                'pagination' => $clean['pagination'] ?? true,
                'archive' => isset($clean['archive'])
                    ? ['field' => $clean['archive']['field'], 'sort' => $clean['archive']['sort'] ?? $sort]
                    : null,
            ];
        }

        return [
            'fields' => $added->map($field)->values()->all(),
            'sortOptions' => $isList ? [
                ...array_map($service, ContentType::LIST_SORT_SERVICE_KEYS),
                ...$added->filter(fn (ContentField $f) => $this->list->sortable($f))->map($field)->values()->all(),
            ] : [],
            'archiveOptions' => $isList ? [
                ...array_map($service, self::ARCHIVE_SERVICE_KEYS),
                ...$added->filter(fn (ContentField $f) => in_array($f->type, self::ARCHIVE_TYPES, true))->map($field)->values()->all(),
            ] : [],
            'current' => $current,
            'maxPerPage' => config('content.max_per_page'),
        ];
    }

    /**
     * What the "Meta data" block of the "SEO" tab offers and its current state (kinds `content` and `page`).
     *
     * - `metaFields`: per meta name, the added fields whose type it allows, `{key, name, service}`.
     * - `current`: the chosen keys by name (an object, empty when nothing is chosen).
     *
     * @param  Collection<string, ContentField>  $fields  The fields that hold node values, by key.
     * @return array{metaFields: array<string, list<array{key: string, name: string, service: bool}>>, current: object}
     *
     * @example
     *   // an added string field `headline`
     *   // metaFields.title → [['key' => 'headline', 'name' => 'Headline', 'service' => false]]
     */
    public function metaOptions(ContentType $type, Collection $fields): array
    {
        $added = $this->addedFields($fields);
        $metaFields = collect(self::META_TYPES)->map(
            fn (array $types, string $name) => [
                ...$added->filter(fn (ContentField $f) => in_array($f->type, $types, true))
                    ->map(fn (ContentField $f) => ['key' => $f->key, 'name' => $f->name, 'service' => false])->values()->all(),
            ]
        )->all();

        return [
            'metaFields' => $metaFields,
            'current' => (object) ($this->clean($type, $fields)['meta'] ?? []),
        ];
    }
}
