# Spec Delta

## ADDED Requirements

### Requirement: Сортування за службовими атрибутами запису
The system SHALL support sorting the records of a type by the service attributes of the record in ascending or descending direction: the dates `created_at`, `updated_at` and `unpublished_at`, and also `status` (the derived state) and `is_active`. Records without a value (in particular without `unpublished_at`) SHALL go last regardless of the direction. For equal values of `created_at`, `updated_at` and `unpublished_at` the order SHALL be determined by the identifier of the record in the direction of the main sorting; the secondary order by `title` SHALL NOT be applied to these sortings. Sorting by `status` and `is_active` SHALL, as for `published_at`, apply for equal values the secondary order by `title`, then by the identifier. The state for sorting SHALL be ordered as follows: «Опубліковано», «Заплановано», «Завершено», «Вимкнено» (in descending order the reverse), `is_active` — `false` before `true`. The same SHALL apply to `sort.field` in `settings.list`.

#### Scenario: From the newest
- **WHEN** sorting by `created_at` in descending order, and the records were created in the order A, B, C
- **THEN** the order is C, B, A

#### Scenario: From the oldest
- **WHEN** sorting by `created_at` in ascending order
- **THEN** the order is A, B, C

#### Scenario: Equal creation date
- **WHEN** two records have the same value of `created_at` and different `title`
- **THEN** their order is determined by the identifier of the record, not by `title`

#### Scenario: Sorting by the update date
- **WHEN** sorting by `updated_at` in descending order
- **THEN** the record updated last goes first

#### Scenario: Records without an unpublishing date
- **WHEN** sorting by `unpublished_at` in descending order, and some records do not have it
- **THEN** the records without an unpublishing date go last

#### Scenario: Sorting by state
- **WHEN** sorting by `status` in ascending order, and there are records in all four states
- **THEN** the order is «Опубліковано», «Заплановано», «Завершено», «Вимкнено»; records of one state — by `title`

#### Scenario: Sorting by activity
- **WHEN** sorting by `is_active` in ascending order
- **THEN** the disabled records go before the active ones

#### Scenario: Incorrect direction
- **WHEN** sorting by a service attribute with a direction other than `asc` and `desc`
- **THEN** the request is rejected with a validation error

### Requirement: Налаштування списку типу
The settings of the list of records SHALL be stored in `settings.list` of the type with the keys `visible` (the keys of the columns in the order of display), `search` (the keys of the text search fields) and `sort` (`field` and `direction`). `visible` SHALL allow the key `title`, the keys of the fields of the type that have values in the records, and the service keys `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`; `search` — the keys of the fields of the type; `sort.field` — the key of a field of the type or a service key, except `slug`; `direction` — `asc` or `desc`. Saving a type with a reference to an unknown key or an invalid direction SHALL be rejected with a validation error. The key `title` in `visible` SHALL set the position of the column `title`; the column `title` remains always present, and without its key in `visible` it stands first. A selection of records without explicit `fields`, `search` and `sort` SHALL be taken from these settings.

#### Scenario: Service key in visible
- **WHEN** a type is saved with `settings.list.visible` = `["status", "is_active", "unpublished_at"]`
- **THEN** the saving succeeds

#### Scenario: Key title in visible
- **WHEN** a type is saved with `settings.list.visible` = `["price", "title"]`
- **THEN** the saving succeeds

#### Scenario: Unknown key
- **WHEN** a type is saved with `settings.list.visible` = `["missing"]`, and the type has no field `missing`
- **THEN** the saving is rejected with a validation error that names the key

#### Scenario: Invalid search key
- **WHEN** a type is saved with `settings.list.search` = `["status"]`
- **THEN** the saving is rejected with a validation error

#### Scenario: Sorting by a service field
- **WHEN** a type is saved with `settings.list.sort` = `{field: "created_at", direction: "desc"}`
- **THEN** the saving succeeds, and the selection without an explicit `sort` returns the records from the newest

#### Scenario: Invalid direction
- **WHEN** a type is saved with `settings.list.sort.direction` = `up`
- **THEN** the saving is rejected with a validation error
