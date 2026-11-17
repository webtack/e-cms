# Spec Delta

## ADDED Requirements

### Requirement: Saving one language of a node or a type
The system SHALL provide one operation that saves the translated values of a node, or of a type's own values, in one additional language. The operation SHALL accept only fields that are translatable and belong to the owner, SHALL apply the validation rules of each submitted value but SHALL NOT enforce required fields, SHALL write the language through the usual language rules (multilingual mode on, the language in the list and active), SHALL NOT store a value that equals the base value of the same field (compared as the stored value of the field type, a list as a whole in order), SHALL remove the translation of a field submitted empty, and SHALL leave every other field, every other language and the base values untouched. The whole operation SHALL be atomic. A node or type has a translation in a language when at least one value of the owner exists in it.

#### Scenario: Only differing values are stored
- **WHEN** the operation saves `title` equal to the base title and `body` different from the base body in `en`
- **THEN** only the English body is stored

#### Scenario: Non-translatable field
- **WHEN** the operation receives a field that is not translatable
- **THEN** it is rejected with a validation error under that key and nothing is stored

#### Scenario: Required field is not enforced
- **WHEN** the operation receives an empty value for a required translatable field
- **THEN** the translation of that field is removed and no required error is raised

#### Scenario: Invalid language
- **WHEN** the operation targets an inactive language or multilingual mode is off
- **THEN** it is rejected with a validation error under `lang`

#### Scenario: Atomicity
- **WHEN** one submitted value is invalid
- **THEN** none of the submitted values is stored

#### Scenario: Translated language
- **WHEN** a node has one stored English value and none in Polish
- **THEN** the node counts as translated into English and not into Polish
