# Spec Delta

## ADDED Requirements

### Requirement: Тип поля «термін»
The system SHALL support the field type «термін», whose value is the `uuid` of a taxonomy term, and the vocabulary is set by the setting `vocabulary` of the field (the key of the vocabulary). The field belongs to the content type, and the values — to the records. The value SHALL be stored in the existing table of string values (`content_values_string`, with the shared contract: language, weight) without requiring a separate table, and read as a string — the `uuid` of the term. The field SHALL support multiplicity and the limit of elements in the same way as other types. The record SHALL accept only the `uuid` of an existing term of exactly the vocabulary specified in the settings of the field; anything else is a validation error under the key of the field. The translatability of the field (`is_translatable`) acts by the general rules: a translation of the value is a separate value row in another language, which also references the term.

#### Scenario: Saving and reading
- **WHEN** the `uuid` of an existing term of the vocabulary `categories` is written into the field `category` of a record
- **THEN** on reading this `uuid` is returned

#### Scenario: Multiple field
- **WHEN** a list of three `uuid` of terms of the vocabulary `tags` is written into the multiple field `tags`
- **THEN** on reading a list of three values in the saved order is returned

#### Scenario: Term of another vocabulary
- **WHEN** the `uuid` of a term of the vocabulary `tags` is written into a field with the vocabulary `categories`
- **THEN** the record is rejected with a validation error under the key of the field

#### Scenario: Non-existent term
- **WHEN** a `uuid` that is not among the terms is written
- **THEN** the record is rejected with a validation error under the key of the field

#### Scenario: Not a uuid
- **WHEN** a value that is not a UUID is written into the field (for example, the `slug` of a term or a number)
- **THEN** the record is rejected with a validation error under the key of the field

#### Scenario: Deleting a used term
- **WHEN** a term is deleted that is referenced by the value of a field of a record
- **THEN** the deletion is rejected, the value remains (see the requirements of the deletion of a term in `taxonomy`)

#### Scenario: Writing against a simultaneous deletion
- **WHEN** a value with a reference to a term is written simultaneously with the deletion of this term
- **THEN** only one of the operations succeeds: either the term is deleted (and the record is rejected as a reference to a non-existent term), or the value is saved (and the deletion is rejected as a usage)

### Requirement: Налаштування поля «термін»
The system SHALL require for a field of the type «термін» the setting `vocabulary` with the key of an existing vocabulary. A field without it or with the key of a non-existent vocabulary SHALL not be saved. The vocabulary of the field SHALL be immutable if the field already has values.

#### Scenario: Field without a vocabulary
- **WHEN** a field of the type «термін» is created without the setting `vocabulary`
- **THEN** the saving is rejected with a validation error

#### Scenario: Non-existent vocabulary
- **WHEN** the setting specifies a key of a vocabulary that does not exist
- **THEN** the saving is rejected with a validation error

#### Scenario: Changing the vocabulary of a field with values
- **WHEN** the `vocabulary` of a field that already has values is changed
- **THEN** the change is rejected with a validation error

#### Scenario: Changing the vocabulary of an empty field
- **WHEN** the `vocabulary` of a field without values is changed to the key of an existing vocabulary
- **THEN** the change is saved
