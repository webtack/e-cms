# Spec Delta

## MODIFIED Requirements

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
