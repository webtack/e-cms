# Node querying

`Webtack\Ecms\Content\ContentNodeQuery` returns a page of nodes of **one content type** with filters, search, and sorting by dynamic fields. Everything is executed in the database, not in PHP. The value schema is described in [content-values.md](content-values.md), languages in [content-languages.md](content-languages.md).

```php
use Webtack\Ecms\Content\ContentNodeQuery;
use Webtack\Ecms\Content\NodeQueryParams;

$page = app(ContentNodeQuery::class)->paginate($articleType, new NodeQueryParams(
    fields: ['title', 'visits'],
    lang: 'en',
    search: 'lamp',
    searchFields: ['title', 'body'],
    filters: [['field' => 'visits', 'operator' => 'between', 'value' => [10, 20]]],
    sort: ['field' => 'visits', 'direction' => 'desc'],
    page: 2,
    perPage: 10,
));

$page->total();                       // total count
$page->items()[0]->values['title'];   // values of visible fields in the `values` relation
```

The result is a `LengthAwarePaginator` of `ContentNode` models; the values of visible fields are in `$node->values` (`[field key => value | list | null]`) in the order given in `fields`.

## Parameters

| Parameter | Default value |
| --- | --- |
| `fields` | the type's `settings.list.visible`, otherwise `['title']` |
| `lang` | `default`; other languages — by the reading rules ([content-languages.md](content-languages.md#language-validity-rules)) |
| `search` + `searchFields` | search fields: `settings.list.search`, otherwise all text fields of the type; the node `slug` is searched too unless `searchFields` is passed explicitly (then exactly that set) |
| `filters` | none; not more than `config('content.max_filters')` (10), conditions are combined with AND |
| `sort` | `settings.list.sort`, otherwise by `id` |
| `page`, `perPage` | `1`, `config('content.per_page')` (15), not more than `config('content.max_per_page')` (100); `perPage = 0` — [all nodes](#all-nodes-perpage--0) |
| `onlyVisible` | `false` (the admin panel sees all nodes); `true` — only [visible](#node-visibility) ones |
| `period` | none; `['field', 'part', 'since']` — the [not-past or past nodes](#period-selection) by one date |

Query parameters override the type's settings. Errors are `ValidationException` with the keys `fields`, `lang`, `filters.N.field|operator|value`, `searchFields`, `sort.field|direction`, `period.field|part`.

## All nodes (`perPage = 0`)

`perPage = 0` returns all nodes of the type on one page without splitting: `total()` equals the number of returned nodes, `lastPage()` is 1. The upper bound is `config('content.max_all')` (1000), separate from `max_per_page`: exceeding it is silently truncated in the given sort order. Filters, search, sorting, visibility, and language work the same way. For `perPage > 0` the behavior does not change (the `max_per_page` limit).

## Node visibility

A node is visible to the site when `is_active = true`, `published_at <= now()`, and (`unpublished_at` is empty or `unpublished_at > now()`; at the moment of `unpublished_at` the node is already hidden) ([content-model.md](content-model.md#node-service-attributes)). The parameter `onlyVisible: true` adds this condition to the query and to the page counter (`ContentNode::scopeVisible()`); it is disabled by default, so the admin panel sees everything — scheduled, expired, and disabled ones too. `ContentNode::findBySlug($slug, onlyVisible: true)` takes the same into account. The condition is combined with filters, search, and sorting through AND.

```php
app(ContentNodeQuery::class)->paginate($type, new NodeQueryParams(onlyVisible: true))->total(); // only visible
ContentNode::query()->visible()->count();                                                       // the same scope
```

## Period selection

The parameter `period` keeps the **not-past** (`part = current`) or the **past** (`part = past`) nodes by one date, relative to the boundary moment `since` given by the caller (the public list passes the start of the current UTC day, see [public-site.md](public-site.md)). It is one condition for the page and the counter and combines through AND with filters, search, [visibility](#node-visibility), and [sorting](#sorting). It is not a generic filter: it fixes the meaning of an empty value and of a multiple field, and it supports the service dates, which filters do not. The two parts are exact complements: every node of the type is in exactly one of them.

| Date (`period.field`) | Not past (`current`) | Past (`past`) |
| --- | --- | --- |
| an added `date` / `datetime` field | **any** value (in the requested language, no fallback to `default`) is on or after `since`; a `date` field is compared as a date | no value is on or after `since`, including a node **without a value** |
| `created_at` | `created_at >= since` | `created_at < since` |
| `unpublished_at` | empty ("no end of display") or `>= since` | not empty and `< since` |

For a multiple field "any value" keeps an event that has begun but not ended (begin behind, end ahead). The boundary itself is not past. Any other field (a `string` field, a field of another type, an unknown key, `published_at`) is a `ValidationException` under `period.field`; a part other than `current` / `past` is one under `period.part`.

```php
$since = now()->startOfDay();
new NodeQueryParams(period: ['field' => 'date', 'part' => 'current', 'since' => $since]); // upcoming
new NodeQueryParams(period: ['field' => 'date', 'part' => 'past', 'since' => $since]);    // archive
```

Sorting is independent of the period: the sort of the page is set separately ([Sorting](#sorting), [Default list settings](#default-list-settings)). Visibility is a separate condition: `period` does not hide a disabled or scheduled node by itself.

## Type isolation

Only nodes of the specified type are returned, and only its fields are allowed in `fields`, filters, search, and sorting; the key of a foreign or unknown field is a validation error.

**Owner isolation.** Type values (`entity = 'type'`: `meta_*`, `section_*`, see [content-values.md](content-values.md#value-owner)) do not get into querying: queries join with values by `content_node_id`, and reading nodes selects `entity = 'node'`. Fields that belong only to the type (`section_*`), like in `fields`, filters, search, and sorting, and in `settings.list`, are considered unknown: nodes have no values for them. The `meta_*` field is a regular node field: its node value is used as for any other field, and the type value is ignored. Any new query "all values of a field" not tied to a node must explicitly choose the owner.

## Language

Visible values, filters, search, and sorting work with the values of the specified language. **There is no fallback to `default`**: a node without a translation has `null` in the corresponding fields, does not get into a filter by this language, and goes to the end of sorting. With `NodeQueryParams(fallback: true)` (the public site) returned values fall back to the base language per field and sorting orders a node without a value by its base value, see below. Filters and search still look at the requested language.

## Operators by type

| Field type | Operators |
| --- | --- |
| `string`, `text`, `richtext` | `eq`, `in`, `contains` |
| `integer`, `decimal`, `date`, `datetime` | `eq`, `in`, `gt`, `gte`, `lt`, `lte`, `between` (inclusive range, `value` = `[from, to]`) |
| `boolean` | `eq` (`true`/`false`) |
| `youtube` | `contains` |
| `term` | `eq`, `in` — including descendants, [below](#term-filter) |

`in` accepts a non-empty array: a node matches if the field value equals **any** of the list (`whereIn` on the `value` column). Each element is checked by the type's rules, as for `eq`; an empty array, a non-array, or an invalid element → an error `filters.N.value`. There is no upper limit on the number of elements. `boolean`, `youtube`, and file fields do not support `in`.

An operator outside this set → an error. The filter value is checked by the type's rules (without the `min`/`max` limits from the field's `settings`). For a multiple field, a node matches if **at least one** value matches (`EXISTS`, without `DISTINCT`); a single-value field works the same way. Several filters on one field are executed independently (AND between filters), so each may match a different value of the node: `tags = a` and `tags = b` together find a node that has both tags. There is no operator "both conditions with one value".

### Term filter

For a `term` field, each specified term means **this term and all its descendants** at any depth: `ContentNodeQuery` expands the values via `ExpandsFilterValues` (`TermTree::descendantUuids`, a traversal in PHP by levels) and executes `in` over the expanded set (`eq` also becomes `in`). For a multiple field one match is enough; the node is counted once. The value is the `uuid` of an existing term (otherwise `filters.N.value`); operators other than `eq`/`in` — `filters.N.operator`. Example: [taxonomy.md](taxonomy.md#term-filter).

## Search

Search is `OR` across text fields (`LIKE '%…%'`) in the specified language; a non-text field in `searchFields` is an error. The characters `%` and `_` in the search string are escaped, so they are searched literally. Without an explicit `searchFields`, the node `slug` (`content_nodes.slug`) is an extra `OR` branch with the same escaping and case-insensitivity, so a type without text fields is searched by the slug alone. An explicit `searchFields` is used exactly as given, with no implicit slug; `searchFields: []` returns nothing. `LIKE '%x%'` does not use an index — full-text search is out of scope.

This search works inside **one content type**. The public search page looks through all visible `page` and `content` nodes of the site with the same escaping and `LIKE`, but with its own service, `Webtack\Ecms\Content\SiteSearch`: it ignores `settings.list.search`, always searches the title and every `string`, `text` and `richtext` field of the node's type, and returns plain results for a template, not nodes ([public-site.md](public-site.md#site-search)).

## Sorting

Sorting joins (`LEFT JOIN`) the **first value** of the field — the row with `weight = 0` in the specified language — and compares it by type: numbers numerically (`100, 10, 9`), dates chronologically, text lexicographically, `false` before `true`. This rule is the same for a multiple and a single-value field: the key of a multiple field is its first value (the order of values is set by the editor), the other values do not take part in sorting and, unlike the filter, do not depend on filters. No more than one row is joined per node, so the query and the page counter contain no duplicates. For a poster with several dates this means that the node is placed by its first date.

Querying sorts by a field of any type, including file and `youtube` fields, but by the stored string (`{uuid}.{ext}`, the URL), which gives no meaningful order; the admin panel does not offer such sorting ([admin-content-nodes.md](admin-content-nodes.md#search-sorting-pagination)).

The fields `type_ref` and `node_ref` are sorted not by the stored `key`/`uuid` but by the target's label: the `name` of the type is additionally joined to the first value for `type_ref`, or the `title` of the target's `default` language for `node_ref`; a target that does not exist gives an empty label (the node goes to the end).

Nodes without a value are always at the end, regardless of direction. For explicit sorting (by a field, `published_at`, `status`, and `is_active`) the secondary order is `title` ascending (for both directions of the main key): nodes without `title` in the specified language go after those that have it (there is no fallback to `default`), and then by the node's `id`. Without explicit sorting the order does not change: `settings.list.sort` or the creation order.

**Sorting with fallback.** With `fallback: true` and an additional language, sorting by a field uses `COALESCE(value in the language, base value)` (one more join per sorted field and, for the secondary `title` order, one more), so a node without a translation is ordered by its base value among the translated ones and does not go to the end. Only the public list enables it; the index of the value table cannot be used for such a sort, which is bounded by pagination.

### Sorting by service attributes

`sort.field` also accepts node service attributes (`ContentNodeQuery::SORTABLE_SERVICE_KEYS`): `created_at`, `updated_at`, `published_at`, `unpublished_at`, `status`, `is_active`, and `views`. The `slug` column is not sortable. The direction is checked the same way as for fields (`asc`/`desc`, otherwise a `ValidationException` with the key `sort.direction`).

| Key | Order | Secondary values |
| --- | --- | --- |
| `created_at`, `updated_at` | chronological | by `id` in the direction of the main sorting, **without** `title` |
| `unpublished_at` | chronological; nodes without a date are always at the end in both directions | by `id` in the direction of the main sorting, without `title` |
| `published_at` | chronological | `title` ascending, then `id` |
| `is_active` | `false` before `true` ascending | `title` ascending, then `id` |
| `views` | numeric (an integer column, so `9 < 10 < 100`); `0` is a value and comes first ascending | `title` ascending, then `id` |
| `status` | "Published", "Scheduled", "Expired", "Disabled" (reversed for descending) | `title` ascending, then `id` |

The status is computed by an SQL `CASE` expression (`ContentNodeQuery::statusOrderExpression()`) by the same rules as `ContentNode::status()`: disabled → 3; `published_at` in the future → 1; `unpublished_at` ≤ now → 2; otherwise 0. A test compares the order with `status()` for all four states, so the rule does not diverge.

## Default list settings

In `content_types.settings`:

```json
{ "list": { "visible": ["title", "price"], "search": ["title"], "sort": { "field": "price", "direction": "desc" } } }
```

The keys are checked when the type is saved (`ContentType::assertValidListSettings()`):

- `visible` — the key `title` (the position of the `title` column: it is always shown, and without this key it comes first), the keys of the type's fields and the service keys `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, `views`, `author` (`ContentType::LIST_SERVICE_KEYS`; `author` is a column only, not a sort key); the order of the array is the order of columns;
  Without `visible` (or with only `title`) the list shows `title` and `NodeListSettings::DEFAULT_SERVICE_COLUMNS`: `slug`, `status`, `published_at`, `author`.
- `search` — only keys of the type's fields;
- `sort.field` — a key of a type's field or a service key except `slug` (`ContentType::LIST_SORT_SERVICE_KEYS`); `direction` — `asc` or `desc`.

This is the list configuration, not translations. It is edited in the admin panel: [admin-content-types.md](admin-content-types.md). How the node list reads these settings: [admin-content-nodes.md](admin-content-nodes.md).

## Public type settings

For the site template, `Webtack\Ecms\Content\PublicDisplaySettings` returns the effective `settings.public` settings ([content-model.md](content-model.md#type-settings-settings)):

```php
$fields = ValueOwner::forNodesOfType($type->key)->fields($type->fields->keyBy('key'));
app(PublicDisplaySettings::class)->resolve($type, $fields);
// ['page_fields' => [...], 'list_fields' => ['title', ...], 'sort' => ['field' => 'published_at', 'direction' => 'desc'],
//  'per_page' => 0, 'pagination' => false]
// with an archive: 'archive' => ['field' => 'date', 'sort' => ['field' => 'date', 'direction' => 'desc']]
```

- `page_fields` — the saved list or, when the key is absent, all added fields of the type;
- `list_fields` — `title` and the saved added fields (only the `content` kind);
- `meta` — the fields selected for the page metadata by name (`title`, `description`, `image`); the key is present only when something is selected (and never for `block`); the automatic node `title` field cannot be selected. The selection is edited on the type's "SEO" tab. They are read by `Webtack\Ecms\Theme\PageMeta` ([public-site.md](public-site.md#page-meta-data)); they do not concern the template's `$fields`;
- `sort`, `per_page` — values from the settings or `published_at desc` and `0`; they are passed to `NodeQueryParams` (`sort`, `perPage`), and `per_page = 0` enables the [all nodes mode](#all-nodes-perpage--0);
- `pagination` — whether to output the paginator; with `per_page = 0` always `false`;
- `archive` — `{field, sort}` when the type splits its list by a date (the `content` kind only; the key is absent otherwise). `field` is an added `date` / `datetime` field, `created_at`, or `unpublished_at`; `sort` is the sort of the archive list and equals the effective main `sort` when none is stored. A stale `field` (deleted, or no longer a date) drops the whole archive, a stale `sort` only itself. The two lists are read with the [period selection](#period-selection): `part = current` with `sort`, `part = past` with `archive.sort`; the sorts are independent.

References to deleted or non-sortable fields are dropped without an error. There are no public routes or templates yet: the class only reads the settings.

## Node lookup by slug

```php
ContentNode::findBySlug('pro-nas')?->contentType->kind;   // the node with its type and kind, or null
ContentNode::findBySlug('pro-nas', onlyVisible: true);    // null for a disabled or scheduled node
ContentType::ofKind('page')->get();                       // types by kind
ContentType::childrenOf('article')->get();                // child types
```
