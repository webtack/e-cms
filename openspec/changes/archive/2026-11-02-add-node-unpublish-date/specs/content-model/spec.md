# Spec Delta

## MODIFIED Requirements

### Requirement: Службові атрибути запису
Each content record SHALL have service attributes: `published_at` (the date and time of publication, required), `unpublished_at` (the date and time of unpublishing, optional), `is_active` (boolean, by default `true`) and `user_id` (the author, required, a reference to a user). `published_at`, `unpublished_at` and `is_active` SHALL be editable. The author SHALL be set by the system from the user who creates the record, SHALL NOT accept a value from the request and SHALL NOT change after creation. A user who is the author of a record SHALL NOT be deleted. If `published_at` is not specified on creation, the system SHALL substitute the current moment; clearing `published_at` of an existing record SHALL be impossible. Past and future values of `published_at` SHALL be valid. An empty `unpublished_at` SHALL mean «no unpublish date», and clearing it of an existing record SHALL be possible. When `unpublished_at` is set, it SHALL be later than `published_at` (equality is not valid); this SHALL apply also when `published_at` is substituted by the system. A past date of `unpublished_at` SHALL be valid. Disabling a record (`is_active = false`) SHALL NOT change `published_at` and `unpublished_at`. For records that existed before these attributes appeared, `published_at` SHALL equal the date of their creation, `unpublished_at` SHALL be empty, and the author SHALL be the administrator from `ADMIN_EMAIL`.

#### Scenario: Default values
- **WHEN** a record is created without `published_at`, `unpublished_at` and `is_active`
- **THEN** `is_active` equals `true`, `published_at` — the moment of creation, and `unpublished_at` is empty

#### Scenario: Author from the current user
- **WHEN** an authenticated user creates a record
- **THEN** this user becomes the author of the record

#### Scenario: Author does not change
- **WHEN** the update request of a record contains a different `user_id`
- **THEN** the author of the record remains the previous one, there is no error

#### Scenario: Author is not accepted on creation
- **WHEN** the creation request contains the `user_id` of another user
- **THEN** the current user becomes the author

#### Scenario: Record without an author is impossible
- **WHEN** a record is created without a user (neither an explicit author, nor the current user)
- **THEN** the creation is rejected, the record is not saved

#### Scenario: Deleting the author
- **WHEN** an attempt is made to delete a user who is the author of at least one record
- **THEN** the user is not deleted

#### Scenario: Disabling keeps the dates
- **WHEN** a record with `published_at` in the future and a set `unpublished_at` is disabled (`is_active = false`)
- **THEN** `published_at` and `unpublished_at` do not change

#### Scenario: Unpublish date later than the publication date
- **WHEN** a record is saved with `published_at` 2026-12-01 10:00 and `unpublished_at` 2026-12-31 10:00
- **THEN** the record is saved with both dates

#### Scenario: Unpublish date not later than the publication date
- **WHEN** a record is saved with `unpublished_at` that equals or precedes `published_at`
- **THEN** the saving is rejected with a validation error for the field `unpublished_at`, the record is not changed

#### Scenario: Unpublish date against the substituted publication date
- **WHEN** a record is created without `published_at`, and `unpublished_at` is in the past relative to the moment of creation
- **THEN** the creation is rejected with a validation error for the field `unpublished_at`

#### Scenario: Clearing the unpublish date
- **WHEN** the unpublish date of a record with `unpublished_at` is cleared and saved
- **THEN** `unpublished_at` is empty, the record is again without an unpublish date

#### Scenario: Migration of existing records
- **WHEN** the migration runs over a table with existing records
- **THEN** each record has `published_at` = the creation date, `unpublished_at` is empty, `is_active = true`, and the author — the administrator from `ADMIN_EMAIL`

#### Scenario: No administrator for existing records
- **WHEN** the migration runs over a non-empty table of records, and there is no user with `ADMIN_EMAIL`
- **THEN** the request is rejected with a validation error
