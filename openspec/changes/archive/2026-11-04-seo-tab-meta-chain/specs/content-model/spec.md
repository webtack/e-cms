# Spec Delta

## MODIFIED Requirements

### Requirement: Системні поля метаданих і розділу
The system SHALL create system fields automatically when a type is created, according to a list that depends on the kind of the type, and SHALL have one list of system fields (key, value type, name, kinds, owners of values, translatability) as the single source of truth for creation, backfill, reserved keys and the definition of the owner of a field. All system metadata and section fields SHALL be optional, non-multiple, translatable and `is_system`; they cannot be deleted, their `type`, `is_multiple`, `is_required` and key cannot be changed; the name, weight, description and settings may be changed.

The list:

| key | value type | `content` | `page` | `block` |
| --- | --- | --- | --- | --- |
| `meta_title` | «короткий текст» ("short text") | record and type | record and type | none |
| `meta_description` | «текст» ("text") | record and type | record and type | none |
| `meta_image` | «зображення» ("image") | record and type | record and type | none |
| `section_title` | «короткий текст» ("short text") | type | none | none |
| `section_description` | «форматований текст» ("formatted text") | type | none | none |
| `section_cover` | «зображення» ("image") | type | none | none |

«Запис» ("Record") means that the field has a value in the records of this type; «тип» ("type") means that the field has a value of the type itself. A field that is absent from the column of the kind is not created for types of this kind. A non-system field SHALL belong only to records. A change of owners needs no new fields: the types `page` already have `meta_*`.

#### Scenario: Content type
- **WHEN** a type of kind `content` is created
- **THEN** it has the fields `title`, `meta_title`, `meta_description`, `meta_image`, `section_title`, `section_description`, `section_cover`

#### Scenario: Page types
- **WHEN** a type of kind `page` is created
- **THEN** it has the fields `title`, `meta_title`, `meta_description`, `meta_image` and has no fields `section_*`

#### Scenario: Block types
- **WHEN** a type of kind `block` is created
- **THEN** it has only the field `title`

#### Scenario: Forbidden changes of a system field
- **WHEN** the value type, the multiplicity or the requiredness of a system metadata field is changed, or the field is deleted
- **THEN** the change is rejected, the field and its values remain

#### Scenario: Backfill of existing types
- **WHEN** the migration is applied to a database with existing types `content` and `page`
- **THEN** each type receives the missing system fields according to the list of its kind, and the existing fields and values are not changed

#### Scenario: Key conflict during backfill
- **WHEN** an existing type already has a user field with the key of a system metadata field
- **THEN** the migration finishes with an error listing the conflicting types and keys, before any changes

### Requirement: Власники значень поля
A value of a field SHALL have an owner: a record or a type. The system SHALL define the owners of a field from the list of system fields, not from a column: a system field belongs to those owners that are listed for the kind of its type in the list, any other field belongs only to records. An attempt to write a value of a field to an owner it does not belong to SHALL be rejected.

#### Scenario: Value of a type for a field of a record
- **WHEN** a value of a type is written for a user field or for `title`
- **THEN** the write is rejected with a validation error, the value is not saved

#### Scenario: Value of a record for a section field
- **WHEN** a value of a record is written for `section_title`
- **THEN** the write is rejected with a validation error

#### Scenario: Meta field of both owners
- **WHEN** in a type of kind `content` `meta_title` is written for a record and for the type itself
- **THEN** both values are saved separately and independently

#### Scenario: Meta field of a page type
- **WHEN** in a type of kind `page` `meta_description` is written for the type itself
- **THEN** the value is saved for the type and is not mixed with the values of the records of this type

#### Scenario: Meta field of a block type
- **WHEN** in a type of kind `block` `meta_title` is written for the type itself
- **THEN** the write is rejected with a validation error (the block has no such field)
