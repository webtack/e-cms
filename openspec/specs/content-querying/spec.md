# content-querying Specification

## Purpose
Describes the querying of content nodes of a content type with configurable visible fields, search, filters, and sorting by dynamic fields, taking into account their types and language.

## Requirements

### Requirement: Querying nodes with visible fields
The system SHALL return a page of nodes of the given content type with the values of only the requested visible fields (by default — from the list settings in the type's `settings`) in the specified language (by default `default`; other languages are admissible under the same conditions as for reading values), together with the pagination data.

#### Scenario: Visible fields
- **WHEN** a list of nodes is requested with the visible fields `title` and `price`
- **THEN** each node contains values of only these fields

#### Scenario: Default settings
- **WHEN** no visible fields are specified, and the list settings are set in the `settings` of the type
- **THEN** the visible fields and the sorting from these settings are used

#### Scenario: Pagination
- **WHEN** the second page with the size 10 is requested
- **THEN** the nodes 11–20 are returned in the specified order, together with the total count

#### Scenario: Invalid language
- **WHEN** a list is requested with `lang = en`, and multilingual mode is disabled or the language `en` is not in the list
- **THEN** the request is rejected with a validation error

#### Scenario: Query language
- **WHEN** a list is requested with `lang = en`
- **THEN** the visible values are taken from the language `en`; nodes without this translation are returned with `null` in the corresponding fields

### Requirement: Filtering by field value
The system SHALL filter nodes by a field, an operator, and a value in the specified language, and the set of allowed operators SHALL be determined by the field type: equality for all types; comparison (greater/less/range) for numeric, date, and date-time fields; substring inclusion for text fields; the values `true`/`false` for boolean; membership in a list (`in`) for string, text, numeric, date, and date-time fields. A filter SHALL work the same way for a multiple and a single-value field: a node matches the filter if at least one value of the field matches. The operator `in` SHALL accept a non-empty array of values, each of which is checked by the rules of the field type, and SHALL consider a node matching if any of its values equals any of the passed ones. Several filters SHALL be combined by AND, and each filter SHALL be checked independently of the others, including several filters on one field.

#### Scenario: Numeric range
- **WHEN** the filter `price` is between `10` and `20`
- **THEN** only the nodes whose `price` is in this range inclusive are returned

#### Scenario: Multiple field
- **WHEN** the filter `tags` equals `news`, and a node has the values `["a", "news"]`
- **THEN** this node is included in the result

#### Scenario: Membership in a list
- **WHEN** the filter `city` with the operator `in` and the value `["Kyiv", "Lviv"]`
- **THEN** the nodes whose `city` equals `Kyiv` or `Lviv` are returned

#### Scenario: Membership in a list for a multiple field
- **WHEN** the filter `tags` with the operator `in` and the value `["news", "sale"]`, and a node has the values `["a", "sale"]`
- **THEN** this node is included in the result

#### Scenario: Invalid value for `in`
- **WHEN** the operator `in` with an empty array or a non-array
- **THEN** the request is rejected with a validation error `filters.N.value`

#### Scenario: Several filters on one multiple field
- **WHEN** the filters `dates >= 2026-11-10` and `dates <= 2026-11-15`, and a node has the values `2026-11-01` and `2026-12-20`
- **THEN** the node is included in the result, because each filter is executed separately by different values

#### Scenario: Filter by language
- **WHEN** the filter `title` contains "Hello" with `lang = en`
- **THEN** only the nodes whose English `title` contains "Hello" are returned, regardless of the value of `default`

#### Scenario: Unsupported operator
- **WHEN** the operator "contains" or `in` is applied to a boolean field
- **THEN** the request is rejected with a validation error

#### Scenario: Unknown field
- **WHEN** a filter refers to a key that does not exist in the content type
- **THEN** the request is rejected with a validation error

### Requirement: Search
The system SHALL support text search by a list of fields (from the request or from the list settings) in the specified language, returning nodes in which at least one of these fields contains the search string; the search SHALL allow only text fields.

#### Scenario: Search across several fields
- **WHEN** the search "lamp" is run on the fields `title` and `description`
- **THEN** the nodes where "lamp" occurs in `title` or in `description` are returned

#### Scenario: Non-text field in the search
- **WHEN** an integer is among the search fields
- **THEN** the request is rejected with a validation error

### Requirement: Sorting by field type
The system SHALL sort nodes by the value of a field in the specified language in ascending or descending direction according to the type of the value (numbers — numerically, dates — chronologically, text — lexicographically, boolean — false before true), SHALL place nodes without a value at the end regardless of the direction, and SHALL apply a deterministic secondary order: by the value of the field `title` in the specified language in ascending order regardless of the direction of the main sorting, and nodes without `title` in this language go after those that have it, and then by the node identifier. The secondary order by `title` SHALL be applied to the explicit sorting (by a field and by the publication date); without an explicit sorting the order does not change. The fallback of `title` to the language `default` SHALL NOT be applied. It SHALL be possible to sort by any field of a content type, including a multiple field and a field of any type; the sort key of a node SHALL be its first value of the field (with the lowest weight) in the specified language, regardless of the direction and of the filters of the request.

#### Scenario: Numeric sorting
- **WHEN** sorting by `visits` in descending order, with the values `9`, `10`, `100`
- **THEN** the order is `100`, `10`, `9`

#### Scenario: Nodes without a value
- **WHEN** some nodes have no value in the sorting field (in the specified language)
- **THEN** they are placed after the nodes with a value in any direction

#### Scenario: Sorting by a multiple field
- **WHEN** sorting ascending by the multiple field `dates`, node A has the values `2026-12-20` (first) and `2026-10-01`, node B has `2026-11-05`
- **THEN** the order is B (key `2026-11-05`), A (key `2026-12-20`)

#### Scenario: Multiple field in descending order
- **WHEN** sorting descending by the multiple field `dates`, node A has the values `2026-12-20` (first) and `2026-10-01`, node B has `2026-11-05`
- **THEN** the order is A (key `2026-12-20`), B (key `2026-11-05`)

#### Scenario: Filters do not change the key
- **WHEN** the filter `dates >= 2026-11-01`, sorting ascending by `dates`, node A has the values `2026-10-01` (first) and `2026-12-20`
- **THEN** node A is included in the selection, and its key remains `2026-10-01`

#### Scenario: Node without values of a multiple field
- **WHEN** a node has no value of the multiple sorting field in the specified language
- **THEN** it is placed after the nodes with a key in any direction

#### Scenario: Equal keys ordered by title
- **WHEN** sorting by `visits` in descending order, nodes with `title` "Beta" (id 1) and "Alpha" (id 2) have the same value of `visits`
- **THEN** "Alpha" comes before "Beta", because the secondary order by `title` is ascending

#### Scenario: Secondary order does not depend on the direction
- **WHEN** sorting by `visits` in ascending and in descending order, nodes with equal `visits` have `title` "Alpha" and "Beta"
- **THEN** in both directions "Alpha" comes before "Beta"

#### Scenario: Node without a title in the specified language
- **WHEN** sorting by `visits`, and a node with an equal key has no `title` in the specified language, although it has one in `default`
- **THEN** it goes after the nodes with `title` in the specified language, then by identifier; the `title` of `default` is not substituted

#### Scenario: Identical key and title
- **WHEN** two nodes have identical values of the main key and `title`
- **THEN** their order is determined by the node identifier

#### Scenario: Node is not duplicated
- **WHEN** sorting by a multiple field, and the node has five values
- **THEN** the node appears in the selection and in the page counter once

#### Scenario: Sorting by a field of any type
- **WHEN** sorting by a field of type `image` or `youtube`
- **THEN** the selection is not rejected with an error, and the nodes are ordered by the stored string of the first value

### Requirement: Search of a node by slug
The system SHALL find a node by its slug regardless of the type and kind, and return it with its type and kind, or report its absence.

#### Scenario: Found node
- **WHEN** the node with the slug `pro-nas` is requested
- **THEN** this node is returned together with its content type and its kind

#### Scenario: Missing node
- **WHEN** a slug that does not exist is requested
- **THEN** it is reported that the node was not found

### Requirement: List of types by kind
The system SHALL allow obtaining content types filtered by the kind `kind` (and by the parent type), in order to work separately with regular content, pages, and blocks.

#### Scenario: Filtering types by kind
- **WHEN** types with the kind `page` are requested
- **THEN** only the types of this kind are returned

### Requirement: Isolation by content type
The selection SHALL return only nodes of the specified content type and SHALL allow only the fields of this type.

#### Scenario: Field of another type
- **WHEN** in a request to the type `article` a field key is specified that exists only in the type `product`
- **THEN** the request is rejected with a validation error

### Requirement: Visibility of nodes for public selection
The selection SHALL have a "visible only" mode, in which it returns only the nodes with `is_active = true`, `published_at` not later than the current moment (inclusive), and an empty `unpublished_at` or an `unpublished_at` later than the current moment (strictly): at the moment of `unpublished_at` the node is already invisible. The mode SHALL apply to the list, the total count counter, and the search by slug (for the public consumer), SHALL be combined with filters, search, and sorting, and SHALL be disabled by default, so that the admin panel sees all nodes. A disabled node SHALL be invisible regardless of the dates; a node with `published_at` in the future SHALL be invisible until this moment arrives; a node with `unpublished_at` not later than the current moment SHALL be invisible. The visibility SHALL be computed at the moment of the request without background jobs: a node disappears from the selection by itself when `unpublished_at` arrives.

#### Scenario: Published node
- **WHEN** a request in the "visible only" mode, and the node has `is_active = true`, `published_at` in the past, and an empty `unpublished_at`
- **THEN** the node is included in the result

#### Scenario: Deferred publication
- **WHEN** a request in the "visible only" mode, and the node has `published_at` in the future
- **THEN** the node is not included in the result, and is not counted in the total count

#### Scenario: Disabled node
- **WHEN** a request in the "visible only" mode, and the node has `is_active = false` and `published_at` in the past
- **THEN** the node is not included in the result

#### Scenario: Node in the display interval
- **WHEN** a request in the "visible only" mode, the node is active, `published_at` is in the past, and `unpublished_at` is in the future
- **THEN** the node is included in the result

#### Scenario: Node unpublished
- **WHEN** a request in the "visible only" mode, and the node is active, `published_at` is in the past, and `unpublished_at` is in the past
- **THEN** the node is not included in the result, and is not counted in the total count

#### Scenario: The unpublishing boundary
- **WHEN** a request in the "visible only" mode is executed exactly at the moment of the node's `unpublished_at`
- **THEN** the node is not included in the result

#### Scenario: Search by slug
- **WHEN** a public consumer searches by the slug of a disabled, not yet published, or already unpublished node
- **THEN** it is reported that the node was not found

#### Scenario: The admin panel sees everything
- **WHEN** the selection is executed without the "visible only" mode
- **THEN** nodes in any state are returned, including the unpublished ones

### Requirement: Filter by a "term" field includes descendants
The system SHALL support for a `term` type field the operators `eq` and `in`, and SHALL interpret each specified term as this term together with all its descendants at any depth. A node matches the filter if its field value (for a multiple field — at least one) equals any term from the expanded set. The specified term SHALL be the `uuid` of an existing term; anything else is a validation error `filters.N.value`. Other operators for this type SHALL be rejected as unsupported.

#### Scenario: Node in a subcategory
- **WHEN** the filter `category` equals "Clothing", and a node has `category` = "Winter", a descendant of "Clothing" through "Jackets"
- **THEN** this node is included in the result

#### Scenario: Node directly in the term
- **WHEN** the filter `category` equals "Clothing", and a node has `category` = "Clothing"
- **THEN** this node is included in the result

#### Scenario: Node in another branch
- **WHEN** the filter `category` equals "Clothing", and a node has `category` = "Electronics"
- **THEN** this node is not included in the result

#### Scenario: List of terms
- **WHEN** the filter `category` with the operator `in` and the value "Clothing" and "Electronics"
- **THEN** the nodes with any of these terms or their descendants are returned, each node once

#### Scenario: Multiple field
- **WHEN** the filter `tags` equals "News", and a node has `tags` with the values "Sport" and "Football", where "Football" is a descendant of "News"
- **THEN** the node is included in the result

#### Scenario: Non-existent term in the filter
- **WHEN** the filter specifies a `uuid` that does not exist
- **THEN** the request is rejected with a validation error `filters.N.value`

#### Scenario: Unsupported operator
- **WHEN** the operator `gt` is specified for a field of type "term"
- **THEN** the request is rejected with a validation error `filters.N.operator`

### Requirement: Sorting by service attributes of a node
The system SHALL support sorting of the nodes of a type by the service attributes of the node in ascending or descending direction: the dates `created_at`, `updated_at`, and `unpublished_at`, as well as `status` (the derived state), `is_active`, and the view counter `views` (numeric). Nodes without a value (in particular without `unpublished_at`) SHALL go at the end regardless of the direction. For equal values of `created_at`, `updated_at`, and `unpublished_at` the order SHALL be determined by the node identifier in the direction of the main sorting; the secondary order by `title` SHALL NOT be applied to these sortings. Sorting by `status`, `is_active`, and `views` SHALL, as with `published_at`, apply for equal values the secondary order by `title`, then by the identifier. The state for sorting SHALL be ordered as: "Published", "Scheduled", "Expired", "Disabled" (reversed for descending), `is_active` — `false` before `true`. The same SHALL apply to `sort.field` in `settings.list`.

#### Scenario: From the newest
- **WHEN** sorting by `created_at` in descending order, the nodes were created in the order A, B, C
- **THEN** the order is C, B, A

#### Scenario: From the oldest
- **WHEN** sorting by `created_at` in ascending order
- **THEN** the order is A, B, C

#### Scenario: Identical creation date
- **WHEN** two nodes have an identical value of `created_at` and different `title`
- **THEN** their order is determined by the node identifier, not by `title`

#### Scenario: Sorting by the update date
- **WHEN** sorting by `updated_at` in descending order
- **THEN** the node updated last comes first

#### Scenario: Nodes without an unpublishing date
- **WHEN** sorting by `unpublished_at` in descending order, some nodes do not have it
- **THEN** the nodes without an unpublishing date come last

#### Scenario: Sorting by state
- **WHEN** sorting by `status` in ascending order, and there are nodes in all four states
- **THEN** the order is "Published", "Scheduled", "Expired", "Disabled"; nodes of one state — by `title`

#### Scenario: Sorting by activity
- **WHEN** sorting by `is_active` in ascending order
- **THEN** the disabled nodes come before the active ones

#### Scenario: Sorting by views
- **WHEN** sorting by `views` in descending order, and the nodes have the counters 9, 10, and 100
- **THEN** the order is 100, 10, 9 (numeric, not textual); nodes with equal counters go by `title`, then by the identifier

#### Scenario: Nodes without views
- **WHEN** sorting by `views` in ascending order, and some nodes have the counter 0
- **THEN** they come first (a zero counter is a value, not an absent one)

#### Scenario: Invalid direction
- **WHEN** sorting by a service attribute with a direction other than `asc` and `desc`
- **THEN** the request is rejected with a validation error

### Requirement: List settings of a type
The list settings of nodes SHALL be stored in the `settings.list` of the type with the keys `visible` (the column keys in display order), `search` (the keys of the text search fields), and `sort` (`field` and `direction`). `visible` SHALL allow the key `title`, the keys of the type's fields that have values in the nodes, and the service keys `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, `views`; `search` — the keys of the type's fields; `sort.field` — a key of a type's field or a service key except `slug`; `direction` — `asc` or `desc`. Saving a type with a reference to an unknown key or an invalid direction SHALL be rejected with a validation error. The key `title` in `visible` SHALL set the position of the `title` column; the `title` column always remains present, and without its key in `visible` it stands first. The selection of nodes without an explicit `fields`, `search`, and `sort` SHALL be taken from these settings.

#### Scenario: Service key in visible
- **WHEN** a type is saved with `settings.list.visible` = `["status", "is_active", "unpublished_at"]`
- **THEN** the saving is successful

#### Scenario: Views in visible and in sort
- **WHEN** a type is saved with `settings.list.visible` = `["title", "views"]` and `settings.list.sort` = `{field: "views", direction: "desc"}`
- **THEN** the saving is successful, and the selection without an explicit `sort` returns the nodes from the most viewed

#### Scenario: Key title in visible
- **WHEN** a type is saved with `settings.list.visible` = `["price", "title"]`
- **THEN** the saving is successful

#### Scenario: Unknown key
- **WHEN** a type is saved with `settings.list.visible` = `["missing"]`, and the type has no field `missing`
- **THEN** saving is rejected with a validation error indicating the key

#### Scenario: Invalid search key
- **WHEN** a type is saved with `settings.list.search` = `["status"]`
- **THEN** saving is rejected with a validation error

#### Scenario: Sorting by a service field
- **WHEN** a type is saved with `settings.list.sort` = `{field: "created_at", direction: "desc"}`
- **THEN** the saving is successful, and the selection without an explicit `sort` returns the nodes from the newest

#### Scenario: Invalid direction
- **WHEN** a type is saved with `settings.list.sort.direction` = `up`
- **THEN** saving is rejected with a validation error

### Requirement: "All nodes" mode in the selection
The selection of nodes SHALL support `per_page = 0` as "all nodes of the type without splitting into pages" with the upper bound `content.max_all` (separate from `content.max_per_page`, which applies for `per_page > 0`). The result SHALL have the same form as a pagination page (the elements, the total count), as one page; the filters, search, sorting, visibility, and language work in the same way.

#### Scenario: All nodes
- **WHEN** `per_page = 0` is requested for a type with 150 visible nodes and `max_all` = 1000
- **THEN** 150 nodes are returned on one page

#### Scenario: Ceiling
- **WHEN** `per_page = 0` is requested, and there are more nodes than `max_all`
- **THEN** no more than `max_all` nodes are returned in the specified sorting

#### Scenario: Ordinary number
- **WHEN** `per_page = 20` is requested
- **THEN** the behavior does not change: a page with no more than 20 nodes, the limit `max_per_page`

### Requirement: Reading the public settings of a type
The system SHALL provide for a type the effective public settings — the set of card fields (always with `title`), the set of page fields (an absent `page_fields` expands to all added fields), the sorting, `per_page`, and `pagination` — with references to absent fields and non-sortable keys dropped, and with the default values substituted. With `per_page = 0` the effective `pagination` SHALL be "no".

#### Scenario: Expanding "all"
- **WHEN** the type has no `page_fields`, and there are three added fields
- **THEN** the effective set of page fields contains these three keys

#### Scenario: Paginator with "all"
- **WHEN** `per_page = 0`, `pagination = true`
- **THEN** the effective `pagination` is "no"

#### Scenario: Default sorting
- **WHEN** `sort` is not set or refers to a deleted field
- **THEN** the list is sorted by `published_at` in descending order

### Requirement: Period selection of nodes by a date
The system SHALL select the nodes of a type that are **not past** or that are **past** by one date, relative to a boundary moment given by the caller. The date SHALL be one of: an added field of type `date` or `datetime`, the service date `created_at`, or the service date `unpublished_at`. The two selections SHALL be exact complements: every node of the type belongs to exactly one of them, and the selection SHALL combine with filters, search, visibility, sorting, and paging through AND like any other condition (one condition for the page and the counter). The date of a field SHALL be read in the requested language, without a fallback to `default`.

For a field, a node is not past when **at least one** of its values is on or after the boundary (for a multiple field this keeps a node whose begin value is behind and whose end value is ahead); a node with no value in the field is past. For `created_at`, a node is not past when the date is on or after the boundary. For `unpublished_at`, a node is not past when the date is empty ("no end of display") or on or after the boundary. A field of another type, a key of another type, or an unknown key SHALL be a validation error under the key `period.field`.

#### Scenario: Not past by a single date
- **WHEN** a type has nodes with `date` 2026-11-10, 2026-11-11, and 2026-11-12, the boundary is 2026-11-11 00:00, and the not-past selection is requested
- **THEN** the nodes with 2026-11-11 and 2026-11-12 are returned and the node with 2026-11-10 is not

#### Scenario: Past by a single date
- **WHEN** the same data and the past selection are requested
- **THEN** only the node with 2026-11-10 is returned

#### Scenario: Multiple field keeps a running node
- **WHEN** a node has the `date` values 2026-11-09 18:00 (begin) and 2026-11-12 20:00 (end), and the boundary is 2026-11-11 00:00
- **THEN** the node is in the not-past selection and not in the past selection

#### Scenario: Node without a value
- **WHEN** a node has no value in the chosen field
- **THEN** it is in the past selection and not in the not-past selection

#### Scenario: Empty `unpublished_at`
- **WHEN** the date is `unpublished_at` and a node has no `unpublished_at`
- **THEN** the node is in the not-past selection and not in the past selection

#### Scenario: Passed `unpublished_at`
- **WHEN** the date is `unpublished_at` and a node has `unpublished_at` before the boundary
- **THEN** the node is in the past selection

#### Scenario: Complement
- **WHEN** both selections are run over the same type with the same boundary
- **THEN** their node sets do not intersect and together equal the nodes of the type

#### Scenario: Unsupported field
- **WHEN** the date is a `string` field, a field of another type, or an unknown key
- **THEN** a validation error under `period.field` is returned and nothing is selected

### Requirement: Archive in the public settings of a type
The effective public settings of a type of kind `content` SHALL include an optional archive: the date by which the public list is split (`field`) and the sort of the archive list (`sort`). `field` SHALL be an added field of type `date` or `datetime`, or the service date `created_at` or `unpublished_at`; a reference to a deleted field or to a field whose type changed SHALL be dropped, and with it the whole archive. `sort` SHALL accept the same fields and directions as the main public sort; an absent or invalid archive `sort` SHALL fall back to the main effective sort. A type without a valid `field` SHALL have no archive.

#### Scenario: Archive configured
- **WHEN** a type has `archive.field = date` and `archive.sort = {date, desc}`, and the main sort is `{date, asc}`
- **THEN** the effective settings hold the archive with the field `date` and the sort `{date, desc}`, and the main sort `{date, asc}` is unchanged

#### Scenario: Archive sort falls back
- **WHEN** a type has `archive.field = date` and no archive sort
- **THEN** the effective archive sort equals the effective main sort

#### Scenario: Deleted field
- **WHEN** `archive.field` refers to a field that was deleted
- **THEN** the effective settings have no archive

#### Scenario: Changed field type
- **WHEN** `archive.field` refers to a field that became a `string` field
- **THEN** the effective settings have no archive

#### Scenario: Not a list type
- **WHEN** a type of kind `page` or `block` has an archive in its settings
- **THEN** the settings are rejected on saving, and a stored one is ignored when reading

### Requirement: Sorting by a translated value with fallback
When a selection is made in the fallback mode for the public site, sorting by a field SHALL use, for each node, the value in the requested language and, when the node has none, its base-language value, so that nodes without a translation are ordered by their base value among the translated ones and do not all go to the end. Without the fallback mode the existing rule SHALL stay: nodes without a value in the requested language go after the others.

#### Scenario: Untranslated node among translated ones
- **WHEN** a list is sorted by title in English with fallback, node A has the English title "Beta", node B has no English title and the base title "Alpha"
- **THEN** B goes before A

#### Scenario: Without the fallback mode
- **WHEN** the same list is sorted without the fallback mode
- **THEN** B goes after A, as before
