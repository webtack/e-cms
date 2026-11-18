## ADDED Requirements

### Requirement: Тип поля «посилання на тип контенту»
The system SHALL support the field type `type_ref`. Its value is the `key` of an existing type, stored in the table of string values. The setting `settings.kind` SHALL be required and have one of the values `content`, `page`, `block`; the value SHALL point to an existing type of this kind. The field SHALL support multiplicity and translations by the general rules; the filters — `eq` and `in`. An empty value SHALL clear the field. After the values appear, `settings.kind` SHALL NOT change. Writing a value SHALL block the references from deletion for the time of the transaction. A type that is referenced by any value of a field `type_ref` (except the own values of this type) SHALL NOT be deleted.

#### Scenario: Saving and reading
- **WHEN** the `key` of an existing block type is written into a field `type_ref` with `kind = block`
- **THEN** on reading the same `key` is returned

#### Scenario: Type of another kind
- **WHEN** the `key` of a type of the kind `page` is written into a field with `kind = block`
- **THEN** the record is rejected with an error next to the field

#### Scenario: Non-existent type
- **WHEN** a `key` that does not exist is written
- **THEN** the record is rejected with an error next to the field

#### Scenario: Settings specified incorrectly
- **WHEN** `settings.kind` is absent, does not belong to `content`/`page`/`block`, or `settings` has other keys
- **THEN** the saving of the field is rejected with an error next to `settings.kind`

#### Scenario: Change of the kind after values appear
- **WHEN** `settings.kind` of a field that already has values is changed
- **THEN** the change is rejected

#### Scenario: Deleting a type that is referenced
- **WHEN** a type is deleted whose `key` is written in `type_ref` of any other record
- **THEN** the deletion is rejected with an explanation that the type is used

### Requirement: Тип поля «посилання на запис»
The system SHALL support the field type `node_ref`. Its value is the `uuid` of an existing record (lower case), stored in the table of string values. The setting `settings.content_type` SHALL be required and point to the `key` of an existing type; the value SHALL point to a record of this type. The support of multiplicity, translations, the filters `eq` and `in`, clearing by an empty value, the immutability of the setting after the values appear and the blocking of the references at the time of writing — as for `type_ref`. A record that is referenced by any value of a field `node_ref` SHALL NOT be deleted; a type whose records are specified in the `node_ref` values of other types SHALL NOT be deleted.

#### Scenario: Saving and reading
- **WHEN** the `uuid` of a record of this type is written into a field `node_ref` with the type `article`
- **THEN** on reading the same `uuid` (lower case) is returned

#### Scenario: Record of another type
- **WHEN** the `uuid` of a record of the type `news` is written into the field for `article`
- **THEN** the record is rejected with an error next to the field

#### Scenario: Non-existent record or not a uuid
- **WHEN** a non-existent `uuid` or a string that is not a uuid is written
- **THEN** the record is rejected with an error next to the field

#### Scenario: Settings specified incorrectly
- **WHEN** `settings.content_type` is absent, unknown, or `settings` has other keys
- **THEN** the saving of the field is rejected with an error next to `settings.content_type`

#### Scenario: Deleting a record that is referenced
- **WHEN** a record is deleted whose `uuid` is written in `node_ref` of another record
- **THEN** the deletion is rejected with an explanation that the record is used

#### Scenario: Filter
- **WHEN** the records are filtered by the field `node_ref` with the operator `eq` and a `uuid`
- **THEN** the records that reference this record are returned
