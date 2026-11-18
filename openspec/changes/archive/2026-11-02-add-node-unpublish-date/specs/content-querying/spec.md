# Spec Delta

## MODIFIED Requirements

### Requirement: Видимість записів для публічної вибірки
The selection SHALL have a mode «only visible», in which it returns only the records with `is_active = true`, `published_at` not later than the current moment (inclusive), and an empty `unpublished_at` or `unpublished_at` later than the current moment (strictly): at the moment of `unpublished_at` the record is already invisible. The mode SHALL apply to the list, to the counter of the total number, and to the search by slug (for the public consumer), SHALL combine with filters, search and sorting, and SHALL be disabled by default, so that the admin sees all records. A disabled record SHALL be invisible regardless of the dates; a record with `published_at` in the future SHALL be invisible until this moment arrives; a record with `unpublished_at` not later than the current moment SHALL be invisible. The visibility SHALL be computed at the moment of the request without background tasks: the record disappears from the selection by itself when `unpublished_at` arrives.

#### Scenario: Published record
- **WHEN** a request in the mode «only visible» and the record has `is_active = true`, `published_at` in the past and an empty `unpublished_at`
- **THEN** the record is included in the result

#### Scenario: Deferred publication
- **WHEN** a request in the mode «only visible» and the record has `published_at` in the future
- **THEN** the record is not included in the result and is not counted in the total number

#### Scenario: Disabled record
- **WHEN** a request in the mode «only visible» and the record has `is_active = false` and `published_at` in the past
- **THEN** the record is not included in the result

#### Scenario: Record within the display interval
- **WHEN** a request in the mode «only visible», the record is active, `published_at` is in the past, and `unpublished_at` is in the future
- **THEN** the record is included in the result

#### Scenario: Record unpublished
- **WHEN** a request in the mode «only visible», the record is active, `published_at` is in the past, and `unpublished_at` is in the past
- **THEN** the record is not included in the result and is not counted in the total number

#### Scenario: Boundary of the unpublish date
- **WHEN** a request in the mode «only visible» is executed exactly at the moment `unpublished_at` of the record
- **THEN** the record is not included in the result

#### Scenario: Search by slug
- **WHEN** a public consumer searches by slug for a disabled, not yet published or already unpublished record
- **THEN** it is reported that the record was not found

#### Scenario: The admin sees everything
- **WHEN** the selection is executed without the mode «only visible»
- **THEN** records in any state are returned, including those unpublished
