# Spec Delta

## ADDED Requirements

### Requirement: Maximum number of items of a multiple field
A multiple field of a content type SHALL have the setting "Maximum number of items" — an integer from 0 to 65535; `0` (the default) means no limit. The create and edit field forms SHALL show this setting only when the checkbox "Multiple" is enabled, with the hint that `0` means no limit. For a non-multiple field the setting SHALL be ignored and stored as `0`. An invalid value (negative, not a number, greater than 65535) SHALL be rejected with an error next to the field without losing the entered text. The setting SHALL be the same for all value types, including `image` and `file`.

#### Scenario: Default setting
- **WHEN** the user creates a multiple field and does not change "Maximum number of items"
- **THEN** the field is saved with `0`, and the edit form shows `0`

#### Scenario: Setting a limit
- **WHEN** the user creates a multiple field `image` with a limit of 5
- **THEN** the field is saved with the limit 5, and the edit form shows 5

#### Scenario: Setting is hidden for a single field
- **WHEN** the checkbox "Multiple" is disabled
- **THEN** the field "Maximum number of items" is not shown

#### Scenario: Reset when multiplicity is disabled
- **WHEN** the user disables "Multiple" in a field with a limit of 5 and saves
- **THEN** the field is saved without a limit (`0`)

#### Scenario: Invalid value
- **WHEN** the user enters a negative number and saves
- **THEN** the save is rejected, an error is shown next to the field, and the entered value is kept
