# Spec Delta

## ADDED Requirements

### Requirement: Системні поля метаданих і розділу
The system SHALL create system fields automatically when a type is created, by a list that depends on the kind of the type, and SHALL have one list of system fields (the key, the value type, the name, the kinds, the owners of values, the translatability) as the single source of truth for the creation, the backfill, the reserved keys and the definition of the owner of a field. All system fields of metadata and section SHALL be optional, non-multiple, translatable and `is_system`; they cannot be deleted, their `type`, `is_multiple`, `is_required` and key cannot be changed; the name, the weight, the description and the settings may be changed.

The list:

| key | value type | `content` | `page` | `block` |
| --- | --- | --- | --- | --- |
| `meta_title` | «короткий текст» («short text») | record and type | record | none |
| `meta_description` | «текст» («text») | record and type | record | none |
| `meta_image` | «зображення» («image») | record and type | record | none |
| `section_title` | «короткий текст» («short text») | type | none | none |
| `section_description` | «форматований текст» («formatted text») | type | none | none |
| `section_cover` | «зображення» («image») | type | none | none |

«Record» means that the field has values in the records of this type; «type» means that the field has values of the type itself. A field that is absent from the column of the kind is not created for the types of this kind. A non-system field SHALL belong only to records.

#### Scenario: Content type
- **WHEN** a type of the kind `content` is created
- **THEN** it has the fields `title`, `meta_title`, `meta_description`, `meta_image`, `section_title`, `section_description`, `section_cover`

#### Scenario: Page type
- **WHEN** a type of the kind `page` is created
- **THEN** it has the fields `title`, `meta_title`, `meta_description`, `meta_image`, and does not have `section_*` fields

#### Scenario: Block type
- **WHEN** a type of the kind `block` is created
- **THEN** it has only the field `title`

#### Scenario: Forbidden changes of a system field
- **WHEN** the value type, the multiplicity or the requiredness of a system field of metadata is changed, or it is deleted
- **THEN** the change is rejected, the field and its values remain

#### Scenario: Backfill of existing types
- **WHEN** the migration is applied to a database with existing types `content` and `page`
- **THEN** each type receives the missing system fields by the list of its kind, and the existing fields and values do not change

#### Scenario: Key conflict during backfill
- **WHEN** an existing type already has a user field with the key of a system metadata field
- **THEN** the migration fails before any changes, with a list of the conflicting types and keys

### Requirement: Власники значень поля
A field value SHALL have an owner: a record or a type. The system SHALL define the owners of a field from the list of system fields, not from a column: a system field belongs to the owners that are listed for the kind of its type, any other field belongs only to records. An attempt to write a value of a field to an owner it does not belong to SHALL be rejected.

#### Scenario: Value of a type for a field of a record
- **WHEN** a value of a type is written for a user field or for `title`
- **THEN** the record is rejected with a validation error, the value is not saved

#### Scenario: Value of a record for a section field
- **WHEN** a value of a record is written for `section_title`
- **THEN** the record is rejected with a validation error

#### Scenario: Metafield of both owners
- **WHEN** in a type of the kind `content` `meta_title` is written for a record and for the type itself
- **THEN** both values are saved separately and independently

## MODIFIED Requirements

### Requirement: Обов'язкове системне поле title
The system SHALL automatically create in any content type, when it is created, a field `title` of the type «короткий текст» («short text») (required, non-multiple, translatable, of the smallest weight (`weight = 0`)) and SHALL forbid deleting this field, changing its type, multiplicity and requiredness (the key is immutable for all fields); the name, the weight and the settings may be changed. The field `title` belongs only to records. Additional system fields, depending on the kind, are defined by the requirement «System fields of metadata and section».

#### Scenario: Automatic creation
- **WHEN** a new content type is created
- **THEN** it immediately contains the field `title` of the type «короткий текст» («short text»)

#### Scenario: Attempt to delete title
- **WHEN** the deletion of the field `title` is performed
- **THEN** the deletion is rejected, the field and its values remain

#### Scenario: Forbidden changes of title
- **WHEN** the type, the multiplicity or the requiredness of the field `title` is changed
- **THEN** the change is rejected

#### Scenario: Deleting a type
- **WHEN** a content type is deleted
- **THEN** it is deleted together with the field `title` (the prohibition on deleting `title` does not block deleting the whole type)

### Requirement: Поле визначається рядком у БД
The system SHALL allow adding to a content type a field with a key (unique within the type), a name, a value type from the registry, the flags of multiplicity, requiredness and translatability, a weight (`weight`) and optional settings (JSON). The key of a field SHALL match the format `^[a-z][a-z0-9_]*$` (up to 64 characters), SHALL NOT match a reserved key and SHALL NOT change after creation; the name, the weight and the settings may be changed. Reserved keys: the keys of the service columns of the table of records (`uuid`, `slug`, `status`, `published_at`) and the keys of the system fields of metadata and section (`meta_title`, `meta_description`, `meta_image`, `section_title`, `section_description`, `section_cover`), which only the system creates. The prohibition of reserved keys SHALL apply only when a field is created by a user: fields that already have such a key remain valid and are edited as before, and the keys of the service columns of the table of records are not offered as columns.

#### Scenario: Adding a field
- **WHEN** a field `views` of the type «ціле число» («integer») is added to the type `article`
- **THEN** the field is saved, and records of this type can have values for it

#### Scenario: Unknown value type
- **WHEN** a field with a type that is not in the registry of field types is added
- **THEN** the system rejects saving the field with a validation error

#### Scenario: Duplicate key within the type
- **WHEN** a second field with an existing key is added to the same type
- **THEN** the saving is rejected; the same key in another content type is allowed

#### Scenario: Invalid format of the field key
- **WHEN** a field with the key `My Field` or `1price` is added to a type
- **THEN** the saving is rejected with a validation error

#### Scenario: Reserved field key
- **WHEN** a field with the key `slug`, `uuid`, `status` or `published_at` is added to a type
- **THEN** the saving is rejected with a validation error next to the key, and the field is not created

#### Scenario: Key of a system metadata field
- **WHEN** the user adds to a type a field with the key `meta_title` or `section_cover`
- **THEN** the saving is rejected with a validation error next to the key, and the field is not created

#### Scenario: Existing field with a reserved key
- **WHEN** the name or the weight of a field is updated, whose key was created before the reservation was introduced (for example, `status`)
- **THEN** the change is saved without an error

#### Scenario: Attempt to change the key of a field
- **WHEN** the `key` of an existing field is changed
- **THEN** the change is rejected, the key remains the previous one; the change of the name is allowed

### Requirement: Каскадне видалення конфігурації
The system SHALL forbid deleting a field that has saved values (of records or of the type) for any path of deletion (the admin, code, import), leaving the field and the values unchanged; a field without values SHALL be deleted. When a content type is deleted, the system SHALL delete its fields, records, values of records and values of the type itself, as well as the file links of this type.

#### Scenario: Deleting a field
- **WHEN** a non-system field without saved values is deleted
- **THEN** the field is deleted, the values of the other fields are saved

#### Scenario: Deleting a field with values
- **WHEN** a field is deleted for which saved values exist (in at least one record or language, or the values of the type itself)
- **THEN** the deletion is rejected, the field and all its values remain unchanged

#### Scenario: Deleting a content type
- **WHEN** a content type whose fields have values is deleted
- **THEN** its fields, records and their values are deleted

#### Scenario: Deleting a type with section values
- **WHEN** a type `content` that has values of `section_*` and `meta_*` and a selected image is deleted
- **THEN** the values of the type and the links of its files are deleted, and the files themselves remain
