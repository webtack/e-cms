## ADDED Requirements

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
