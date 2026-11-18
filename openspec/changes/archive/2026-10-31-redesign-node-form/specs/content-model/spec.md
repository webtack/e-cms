## MODIFIED Requirements

### Requirement: Поле визначається рядком у БД
The system SHALL allow adding to a content type a field with a key (unique within the type), a name, a value type from the registry, flags of multiplicity, required status and translatability, a weight (`weight`) and optional settings (JSON). The key of the field SHALL match the format `^[a-z][a-z0-9_]*$` (up to 64 characters), SHALL NOT coincide with a reserved key (`uuid`, `slug`, `status`, `published_at` — the keys of the service columns of the table of records) and SHALL NOT change after creation; the name, the weight and the settings may be changed. The prohibition of reserved keys SHALL apply only when a field is created: fields that already have such a key remain valid and are edited as before, but are not offered as columns in the table of records.

#### Scenario: Adding a field
- **WHEN** the field `views` of the type «ціле число» is added to the type `article`
- **THEN** the field is saved, and the records of this type can have values for it

#### Scenario: Unknown value type
- **WHEN** a field with a type that is not in the registry of field types is added
- **THEN** the system rejects saving the field with a validation error

#### Scenario: Duplicate key within the type
- **WHEN** a second field with an existing key is added to the same type
- **THEN** the saving is rejected; the same key in another content type is allowed

#### Scenario: Invalid format of a field key
- **WHEN** a field with the key `My Field` or `1price` is added to a type
- **THEN** the saving is rejected with a validation error

#### Scenario: Reserved field key
- **WHEN** a field with the key `slug`, `uuid`, `status` or `published_at` is added to a type
- **THEN** the saving is rejected with a validation error next to the key, and the field is not created

#### Scenario: Existing field with a reserved key
- **WHEN** the name or the weight of a field is updated, whose key was created before the reservation was introduced (for example, `status`)
- **THEN** the change is saved without an error

#### Scenario: Attempt to change the key of a field
- **WHEN** the `key` of an existing field is changed
- **THEN** the change is rejected, the key remains the previous one; changing the name is allowed

## ADDED Requirements

### Requirement: Службові атрибути запису
Each content record SHALL have service attributes: `published_at` (the date and time of publication, required), `is_active` (boolean, by default `true`) and `user_id` (the author, required, a reference to a user). `published_at` and `is_active` SHALL be editable. The author SHALL be set by the system from the user who creates the record, SHALL NOT accept a value from the request and SHALL NOT change after creation. A user who is the author of a record SHALL NOT be deleted. If `published_at` is not specified on creation, the system SHALL substitute the current moment; clearing `published_at` of an existing record SHALL be impossible. Past and future values of `published_at` SHALL be valid. Disabling a record (`is_active = false`) SHALL NOT change `published_at`. For records that existed before these attributes appeared, `published_at` SHALL equal the date of their creation, and the author SHALL be the administrator from `ADMIN_EMAIL`.

#### Scenario: Default values
- **WHEN** a record is created without `published_at` and `is_active`
- **THEN** `is_active` equals `true`, and `published_at` — the moment of creation

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

#### Scenario: Disabling keeps the date
- **WHEN** a record with `published_at` in the future is disabled (`is_active = false`)
- **THEN** `published_at` does not change

#### Scenario: Migration of existing records
- **WHEN** the migration runs over a table with existing records
- **THEN** each record has `published_at` = the creation date, `is_active = true`, and the author — the administrator from `ADMIN_EMAIL`

#### Scenario: No administrator for existing records
- **WHEN** the migration runs over a non-empty table of records, and there is no user with `ADMIN_EMAIL`
- **THEN** the migration ends with an explicit error and changes nothing
