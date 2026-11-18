## ADDED Requirements

### Requirement: Режим «усі записи» у вибірці
The selection of records SHALL support `per_page = 0` as «all records of the type without splitting into pages» with an upper limit `content.max_all` (separate from `content.max_per_page`, which applies to `per_page > 0`). The result SHALL have the same shape as the paginated page (the elements, the total number), as one page; the filters, the search, the sorting, the visibility and the language act in the same way.

#### Scenario: All records
- **WHEN** `per_page = 0` is requested for a type with 150 visible records and `max_all` = 1000
- **THEN** 150 records are returned as one page

#### Scenario: Ceiling
- **WHEN** `per_page = 0` is requested, and there are more records than `max_all`
- **THEN** no more than `max_all` records are returned in the specified sorting

#### Scenario: Usual number
- **WHEN** `per_page = 20` is requested
- **THEN** the behaviour does not change: a page with no more than 20 records, the limit `max_per_page`

### Requirement: Читання публічних налаштувань типу
The system SHALL provide for a type the effective public settings — the set of fields of the card (always with `title`), the set of fields of the page (an absent `page_fields` expands to all added fields), the sorting, `per_page` and `pagination` — with the references to absent fields and non-sortable keys discarded and the default values substituted. With `per_page = 0` the effective `pagination` SHALL be «no».

#### Scenario: Expansion of «all»
- **WHEN** a type has no `page_fields`, and there are three added fields
- **THEN** the effective set of fields of the page contains these three keys

#### Scenario: Paginator with «all»
- **WHEN** `per_page = 0`, `pagination = true`
- **THEN** the effective `pagination` is «no»

#### Scenario: Default sorting
- **WHEN** `sort` is not set or refers to a deleted field
- **THEN** the list is sorted by `published_at` descending
