# Spec Delta

## ADDED Requirements

### Requirement: Translated field labels
A field SHALL be able to hold translations of its label (the base label is its `name`). Translations SHALL be stored in the field's `settings` under the key `labels`, an object that maps a normalized language code to the translated text (up to 255 characters). The key SHALL be optional; an empty object SHALL NOT be stored. A label SHALL be readable for a language without any fallback (a missing label reads as missing). Cloning a type SHALL copy the translated labels of its fields. A language for which a translated label exists SHALL NOT be deletable, as with any other stored translation.

#### Scenario: Stored shape
- **WHEN** the labels `en` = "Opening hours" and `pl` = "Godziny otwarcia" are saved for a field
- **THEN** the field's settings hold both under `labels` keyed by normalized codes

#### Scenario: Missing label
- **WHEN** the label of a field is read in a language with no stored label
- **THEN** it reads as missing

#### Scenario: Cloning
- **WHEN** a type whose field has translated labels is cloned
- **THEN** the field of the clone has the same translated labels

#### Scenario: Deleting a language with a label
- **WHEN** a language that has a translated label of a field is deleted
- **THEN** the deletion is rejected

#### Scenario: Deleting a field
- **WHEN** a field with translated labels is deleted
- **THEN** its labels are deleted with it
