# Spec Delta

## Purpose

Defines the typed storage of values of fields of content nodes, translations through the value's language, the registry of value types and the single mechanism of reading, writing and validating values according to the field configuration.

## ADDED Requirements

### Requirement: Initial value types
The system SHALL support the field types: short text, long text, integer, decimal number, boolean, date, date and time; the value of each type SHALL be stored in a column of the corresponding DB type and read back in the corresponding type.

#### Scenario: Storing and reading types
- **WHEN** the node is written the integer `42`, the decimal `12.50`, the boolean `true`, the date `2026-10-29` and the date and time `2026-10-29 14:30:00`
- **THEN** on reading values of the same types and with the same values are returned (integer, decimal without loss of precision, boolean, date, date and time)

### Requirement: Shared contract of value tables
Each value table SHALL contain the link to the content node (`content_node_id`) and the `uuid` of this node (`content_node_uuid`), the link to the field by the pair of the type `key` (`content_type`) and the field `key` (`field_key`), the language (`lang`), the value weight (`weight`), the typed value and an optional JSON for additional settings; the combination (node, type, field key, language, weight) SHALL be unique. The type in the value SHALL match the type of its node, and `content_node_uuid` — the `uuid` of this node.

#### Scenario: Weight uniqueness
- **WHEN** for one node, field and language a value with the same weight is written twice
- **THEN** the second save is rejected at the DB level

#### Scenario: Value of a field of another type
- **WHEN** a value of a field of the type `product` is written to a node of the type `article` (directly in the DB)
- **THEN** the save is rejected at the DB level, because the type in the value does not match the type of the node

#### Scenario: Value of a non-existent field
- **WHEN** a value with the pair (`content_type`, `field_key`) that is not among the fields is written
- **THEN** the save is rejected at the DB level

#### Scenario: Uuid of the node in the value
- **WHEN** a value of a node is written
- **THEN** `content_node_uuid` matches the `uuid` of the node and does not change separately from it

### Requirement: Translations as separate values
The system SHALL store a translation of a value as a separate value row with the corresponding `lang`; the default language SHALL be `default`; translations SHALL NOT be stored in JSON columns. Values in different languages SHALL be independent: a write in one language does not change the others.

#### Scenario: Default value
- **WHEN** a value is written without specifying a language
- **THEN** it is stored with `lang = default`

#### Scenario: Translation
- **WHEN** the translatable field `title` is written the value "Hello" with `lang = en`, and the value "Привіт" already exists with `lang = default`
- **THEN** two independent values exist; reading from `default` returns "Привіт", reading from `en` — "Hello"

#### Scenario: Missing translation
- **WHEN** an allowed language is read for which no value has been written
- **THEN** `null` (or an empty list for a multiple field) is returned, without substituting the `default` value

### Requirement: Translatability of a field
The system SHALL allow writing values with a language other than `default` only for fields with the translatability flag, when multilingualism is enabled and the language is an active content language (see `content-languages`), and SHALL reject such a write in all other cases.

#### Scenario: Non-translatable field
- **WHEN** a value with `lang = en` is written to a non-translatable field
- **THEN** the write is rejected with an error for this field

#### Scenario: Unknown or inactive language
- **WHEN** a write is performed with a language that is not in the list of content languages or that is inactive
- **THEN** the write is rejected with a validation error

#### Scenario: Disabled multilingualism
- **WHEN** multilingualism is disabled and a write is performed with `lang = en`
- **THEN** the write is rejected with a validation error

### Requirement: Single writing mechanism
The system SHALL write the values of a node through a single mechanism by the keys of the fields of the content type and the language, in a transaction, replacing the existing values of the specified fields in the specified language and not changing the values of other fields and other languages.

#### Scenario: Partial update
- **WHEN** only the field `title` of a node that also has the field `views` is updated
- **THEN** the value of `title` is replaced, the value of `views` remains unchanged

#### Scenario: Error during writing
- **WHEN** writing values fails on the validation of any field
- **THEN** none of the values of this write is saved

#### Scenario: Unknown field
- **WHEN** the request contains a key that is not among the fields of the content type of the node
- **THEN** the write is rejected with a validation error

### Requirement: Validation by field configuration
The system SHALL validate values by the type of the field and its settings (in particular requiredness and boundary values, such as length or minimum/maximum) and SHALL return errors by the field keys. Requiredness SHALL be checked for the language `default` when a node is created (in particular `title`); for other languages requiredness is not required.

#### Scenario: Required field
- **WHEN** a node is created without a value of a required field (in particular `title`) in the language `default`
- **THEN** the write is rejected with an error for this field

#### Scenario: Invalid type
- **WHEN** the string `abc` is passed to an "integer" field
- **THEN** the write is rejected with an error for this field

#### Scenario: Exceeding a boundary value
- **WHEN** a value goes beyond the bounds set in the settings of the field
- **THEN** the write is rejected with an error for this field

#### Scenario: Excessive number of digits after the decimal point
- **WHEN** a value with more than 6 digits after the decimal point (`1.1234567`) is passed to a "decimal number" field
- **THEN** the write is rejected with an error for this field, the value is not rounded

### Requirement: Multiple values
For a field with the multiplicity flag the system SHALL store an ordered list of values with the weight `weight` (within a language) and return it in the same order; for a single-valued field — at most one value per language.

#### Scenario: Order of multiple values
- **WHEN** `["b", "a", "c"]` is written to a multiple field
- **THEN** on reading `["b", "a", "c"]` is returned

#### Scenario: Array in a single-valued field
- **WHEN** an array of several values is passed to a single-valued field
- **THEN** the write is rejected with an error for this field

#### Scenario: Clearing a value
- **WHEN** `null` or an empty list is passed to a field
- **THEN** all its values in the specified language are deleted, and on reading `null` (or an empty list for a multiple field) is returned

### Requirement: Reading node values
The system SHALL return the values of a node by field keys for the specified language (by default `default`; other languages are allowed when multilingualism is enabled and the language is in the list of content languages, including inactive ones) and SHALL load the values of a set of nodes without a query per node (the number of queries does not depend on the number of nodes).

#### Scenario: Reading a set
- **WHEN** the values of 50 nodes are read
- **THEN** the number of DB queries does not exceed a constant bound that depends only on the number of value types used

### Requirement: Extensibility with field types
The system SHALL allow adding a new field type by registering its description (value table, validation, type casting, supported filtering and sorting operations) without changes to the common mechanism of writing, reading and selection.

#### Scenario: New type
- **WHEN** a new field type with its own value table is added to the registry
- **THEN** fields of this type can be created, written, read, filtered and sorted without changes to the code of the common mechanism
