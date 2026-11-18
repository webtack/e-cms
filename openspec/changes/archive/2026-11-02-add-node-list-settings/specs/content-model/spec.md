## MODIFIED Requirements

### Requirement: Поле визначається рядком у БД
The system SHALL allow adding to a content type a field with a key (unique within the type), a name, a value type from the registry, flags of multiplicity, required status and translatability, a weight (`weight`) and optional settings (JSON). The key of the field SHALL match the format `^[a-z][a-z0-9_]*$` (up to 64 characters), SHALL NOT coincide with a reserved key and SHALL NOT change after creation; the name, the weight and the settings may be changed. Reserved keys: the keys of the service columns of the table of records (`uuid`, `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`) and the keys of the system fields of metadata and section (`meta_title`, `meta_description`, `meta_image`, `section_title`, `section_description`, `section_cover`), which only the system creates. The prohibition of reserved keys SHALL apply only when a field is created by the user: fields that already have such a key remain valid and are edited as before, and the keys of the service columns of the table of records are not offered as columns.

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
- **WHEN** a field with the key `slug`, `uuid`, `status`, `is_active`, `created_at`, `updated_at`, `published_at` or `unpublished_at` is added to a type
- **THEN** the saving is rejected with a validation error next to the key, and the field is not created

#### Scenario: Key of a system metadata field
- **WHEN** the user adds to a type a field with the key `meta_title` or `section_cover`
- **THEN** the saving is rejected with a validation error next to the key, and the field is not created

#### Scenario: Existing field with a reserved key
- **WHEN** the name or the weight of a field is updated, whose key was created before the reservation was introduced (for example, `status`)
- **THEN** the change is saved without an error

#### Scenario: Attempt to change the key of a field
- **WHEN** the `key` of an existing field is changed
- **THEN** the change is rejected, the key remains the previous one; changing the name is allowed
