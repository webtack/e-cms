# Spec Delta

## ADDED Requirements

### Requirement: Значення типу в спільних таблицях значень
The system SHALL store the values of the type itself in the same value tables as the values of records. A value row SHALL have an owner: a record (a reference to the record and its `uuid`) or a type (no record, and `content_type` equals the key of this type). The owner of a type SHALL have values only for the fields of this type that, by the requirement «Owners of field values», belong to the type. For a type the same languages, weights, validation, normalization and translatability rules apply as for a record; a value of a type in a language that is not the base one is not substituted from the base one when read.

#### Scenario: Writing a value of a type
- **WHEN** `section_title` «Новини» in the language `default` is written for the type `news`
- **THEN** the value is saved without a record, and `content_type` equals `news`

#### Scenario: Translation of a value of a type
- **WHEN** `section_title` «News» in the language `en` is written for the type `news`
- **THEN** there are two independent values (`default`, `en`), replacing one does not change the other

#### Scenario: Reading values of a type
- **WHEN** the values of the type `news` are read for the language `en`, and `section_description` is not filled in English
- **THEN** `section_description` equals `null`, the base value is not substituted

#### Scenario: Owner does not match the value
- **WHEN** a row of a record owner without a record, or a type owner with a record, is inserted directly into the DB
- **THEN** the saving is rejected at the DB level

#### Scenario: Uniqueness of a value of a type
- **WHEN** for one type, field, language and weight a value of the type is written twice
- **THEN** the second saving is rejected at the DB level

#### Scenario: Value of a type does not get into the selection of records
- **WHEN** a selection, filtering, search or sorting of records of the type `news` is performed
- **THEN** the values of the type itself do not affect any of the results

#### Scenario: Filled state of a field
- **WHEN** a field has only values of the type, and no record has values
- **THEN** the field is considered as having values (it cannot be deleted), but it is not considered filled in the records

## MODIFIED Requirements

### Requirement: Спільний контракт таблиць значень
Each value table SHALL contain the owner of the value (for a record: `content_node_id` and `content_node_uuid`; or a type without a record), the owner flag (`entity`: `node` or `type`), the link to the field by the pair `key` of the type (`content_type`) and the `key` of the field (`field_key`), the language (`lang`), the weight of the value (`weight`), the typed value and an optional JSON for additional settings; the combination (owner, type, field key, language, weight) SHALL be unique for both owners. For a record owner the type in the value SHALL match the type of its record, and `content_node_uuid` — the `uuid` of this record; for a type owner the record and its `uuid` SHALL be empty.

#### Scenario: Uniqueness of the weight
- **WHEN** for one record, field and language a value with the same weight is written twice
- **THEN** the second saving is rejected at the DB level

#### Scenario: Value of a field of another type
- **WHEN** a value of a field of the type `product` is written to a record of the type `article` (directly in the DB)
- **THEN** the saving is rejected at the DB level, because the type in the value does not match the type of the record

#### Scenario: Value of a non-existent field
- **WHEN** a value with the pair (`content_type`, `field_key`) that is not among the fields is written
- **THEN** the saving is rejected at the DB level

#### Scenario: Uuid of a record in a value
- **WHEN** a value of a record is written
- **THEN** `content_node_uuid` matches the `uuid` of the record and is not changed separately from it

#### Scenario: Owner flag
- **WHEN** a value of a record or a value of a type is written
- **THEN** `entity` equals `node` or `type` respectively, and a contradictory combination with the presence of a record is rejected

### Requirement: Читання значень записів
The system SHALL return the values of a record by field keys for the specified language (by default `default`; other languages are allowed when multilingualism is enabled and the language is in the list of content languages, including an inactive one), and SHALL load the values of a set of records without a request for each record (the number of requests does not depend on the number of records). In the same way the system SHALL return the values of the type itself by the keys of its fields for the specified language; the values of a type are not mixed with the values of records.

#### Scenario: Reading a set
- **WHEN** the values of 50 records are read
- **THEN** the number of DB requests does not exceed a constant bound that depends only on the number of the value types used

#### Scenario: Reading values of a type
- **WHEN** the values of the type `news` are read by the keys `section_title` and `meta_image`
- **THEN** the values of these fields for the specified language are returned, and a missing value is `null`

#### Scenario: Unknown key of a value of a type
- **WHEN** a value of a type is read by a key that the type does not have, or that does not belong to the owner-type
- **THEN** the reading is rejected with a validation error
