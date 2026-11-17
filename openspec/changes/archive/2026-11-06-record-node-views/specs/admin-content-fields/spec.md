# Spec Delta

## MODIFIED Requirements

### Requirement: Saving a new field
Submitting the creation form SHALL create a field in this type with the given values. The key SHALL have the format of field keys (lowercase Latin letters, digits, and `_`, starting with a letter, up to 64 characters), not match a reserved key (`uuid`, `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, `views`, and also the keys of the system metadata and section fields: `meta_title`, `meta_description`, `meta_image`, `section_title`, `section_description`, `section_cover`), and be unique within the type (the same key in another type is admissible); the name — up to 255 characters; the value type SHALL be registered; the weight SHALL be an integer from 0 to 65535. Extra spaces at the edges of the name and key are trimmed. The created field SHALL NOT have the system flag, even if the request contains it. After success, the user SHALL land on the list of fields of the type with a success message, and the new field SHALL be in the table.

#### Scenario: Successful creation
- **WHEN** the user fills in the key `visits`, the name "Visits", the type "integer", and saves
- **THEN** the field is created, the user is returned to the list of fields with a success message, and `visits` is in the table

#### Scenario: Required values
- **WHEN** the user saves the form without a key or a name
- **THEN** the field is not created, and an error is shown next to the corresponding field

#### Scenario: Invalid key
- **WHEN** the user enters the key `My Field` or `1price`
- **THEN** the field is not created, and an error is shown next to the key field

#### Scenario: Reserved key
- **WHEN** the user enters the key `slug`, `uuid`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, `views`, `meta_title`, or `section_cover`
- **THEN** the field is not created, and an error is shown next to the key field that the key is reserved

#### Scenario: Duplicate key within a type
- **WHEN** the user enters a key that already exists among the fields of this type (in particular `title`)
- **THEN** the field is not created, and an error is shown next to the key field

#### Scenario: Unregistered value type
- **WHEN** the request contains a value type that is not in the registry
- **THEN** the field is not created, and an error is shown next to the type field

#### Scenario: Invalid weight
- **WHEN** the user enters the weight -1 or 70000
- **THEN** the field is not created, and an error is shown next to the weight field

#### Scenario: Attempt to create a system field
- **WHEN** the creation request contains the system field flag
- **THEN** the field is created as non-system

### Requirement: Form of editing a field
The admin panel SHALL have the page `/admin/content-types/{key}/fields/{fieldKey}/edit` with the same form filled with the current values of the field. The key SHALL be read-only with a hint about immutability. The value type SHALL be blocked for every field with a hint about the immutability of the type; for a system field additionally "multiple" and "required" SHALL be blocked. For a field key that is not in this type, the page SHALL respond with 404.

#### Scenario: Opening the form
- **WHEN** the user selects "Edit" for the field `visits` of the type `news`
- **THEN** `/admin/content-types/news/fields/visits/edit` opens with the current values, and the key and the value type cannot be changed

#### Scenario: System field
- **WHEN** the user opens the form of the field `title`
- **THEN** the value type, "multiple", and "required" are blocked, and the name, "translatable", and the weight are available

#### Scenario: Field with values
- **WHEN** the user opens the form of a field that has values
- **THEN** the value type is blocked, and the other fields are available

#### Scenario: Field without values
- **WHEN** the user opens the form of a field that has no values
- **THEN** the value type is likewise blocked, and the other fields (name, weight, "multiple", "required", "translatable", settings) are available

#### Scenario: Unknown field
- **WHEN** the user opens the form for a key that is absent from the type
- **THEN** the server responds with 404

### Requirement: Saving changes of a field
Submitting the edit form SHALL update the name, the weight, and the flags of the field; the key, the owning type, and the value type SHALL NOT change. The values of the key and the owning type from the request are ignored. A value type with a value different from the field's SHALL be rejected with an error next to the type field regardless of the state of the form and of the presence of saved values; a request with the same type SHALL not be considered an error. The server SHALL also reject a change of multiplicity and requiredness of a system field. The validation of the name and the weight matches the validation of creation. After success, the user SHALL remain on the edit form of the field with a success message.

#### Scenario: Successful change
- **WHEN** the user changes the name and the weight of the field `visits` and saves (the form sends the same type)
- **THEN** the changes are saved, the user remains on the edit form of the field with a success message, and the form shows the new values

#### Scenario: Type of a field with values
- **WHEN** a request changes the value type of a field that has saved values
- **THEN** the changes are not saved, an error is shown next to the type field, and the values do not change

#### Scenario: Type of a field without values
- **WHEN** a request changes the value type of a field without saved values
- **THEN** the changes are not saved, an error is shown next to the type field, and the type remains the previous one

#### Scenario: Forbidden changes of title
- **WHEN** a request changes the multiplicity or requiredness of the field `title`
- **THEN** the changes are not saved, an error is shown, and the field remains the previous one

#### Scenario: Key in the request
- **WHEN** an edit request contains a different field key
- **THEN** the key does not change
