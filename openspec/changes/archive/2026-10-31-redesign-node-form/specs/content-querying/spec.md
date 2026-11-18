## ADDED Requirements

### Requirement: Видимість записів для публічної вибірки
The selection SHALL have a mode «only visible», in which it returns only the records with `is_active = true` and `published_at` not later than the current moment (inclusive). The mode SHALL apply to the list, to the counter of the total number, and to the search by slug (for the public consumer), SHALL combine with filters, search and sorting, and SHALL be disabled by default, so that the admin sees all records. A disabled record SHALL be invisible regardless of `published_at`; a record with `published_at` in the future SHALL be invisible until this moment arrives.

#### Scenario: Published record
- **WHEN** a request in the mode «only visible» and the record has `is_active = true` and `published_at` in the past
- **THEN** the record is included in the result

#### Scenario: Deferred publication
- **WHEN** a request in the mode «only visible» and the record has `published_at` in the future
- **THEN** the record is not included in the result and is not counted in the total number

#### Scenario: Disabled record
- **WHEN** a request in the mode «only visible» and the record has `is_active = false` and `published_at` in the past
- **THEN** the record is not included in the result

#### Scenario: The admin sees everything
- **WHEN** the selection is executed without the mode «only visible»
- **THEN** records in any state are returned

#### Scenario: Search by slug
- **WHEN** a public consumer searches by slug for a disabled or not yet published record
- **THEN** it is reported that the record was not found
