# Spec Delta

## MODIFIED Requirements

### Requirement: Збереження нового поля
Submitting the creation form SHALL create a field in this type with the provided values. The key SHALL have the format of field keys (lowercase Latin letters, digits and `_`, starting with a letter, up to 64 characters), SHALL NOT coincide with a reserved key (`uuid`, `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, as well as the keys of the system fields of metadata and section: `meta_title`, `meta_description`, `meta_image`, `section_title`, `section_description`, `section_cover`) and SHALL be unique within the type (the same key in another type is allowed); the name — up to 255 characters; the value type SHALL be registered; the weight SHALL be an integer from 0 to 65535. Extra spaces at the edges of the name and the key are trimmed. The created field SHALL NOT have the system flag, even if the request contains it. After success the user SHALL land on the list of fields of the type with a success message, and the new field SHALL be in the table.

#### Scenario: Successful creation
- **WHEN** the user fills in the key `views`, the name «Перегляди», the type «integer» and saves
- **THEN** the field is created, the user is returned to the list of fields with a success message, and `views` is in the table

#### Scenario: Required values
- **WHEN** the user saves the form without a key or a name
- **THEN** the field is not created, an error is shown next to the corresponding field

#### Scenario: Invalid key
- **WHEN** the user enters the key `My Field` or `1price`
- **THEN** the field is not created, an error is shown next to the key field

#### Scenario: Reserved key
- **WHEN** the user enters the key `slug`, `uuid`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, `meta_title` or `section_cover`
- **THEN** the field is not created, an error is shown next to the key field that the key is reserved

#### Scenario: Duplicate key in the type
- **WHEN** the user enters a key that is already among the fields of this type (in particular `title`)
- **THEN** the field is not created, an error is shown next to the key field

#### Scenario: Unregistered value type
- **WHEN** the request contains a value type that is not in the registry
- **THEN** the field is not created, an error is shown next to the type field

#### Scenario: Invalid weight
- **WHEN** the user enters the weight -1 or 70000
- **THEN** the field is not created, an error is shown next to the weight field

#### Scenario: Attempt to create a system field
- **WHEN** the creation request contains the sign of a system field
- **THEN** the field is created as a non-system one
