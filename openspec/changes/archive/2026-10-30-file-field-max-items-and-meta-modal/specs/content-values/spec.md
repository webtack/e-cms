# Spec Delta

## ADDED Requirements

### Requirement: Item limit of a multiple field
Writing the values of a multiple field that has a maximum number of items greater than 0 SHALL reject a list in which (after discarding the empty items) there are more items than allowed; the error SHALL be addressed to the field itself. The limit `0` SHALL mean no limit. The limit SHALL be checked regardless of the form (by the shared writing mechanism); a field without a limit and non-multiple fields do not change their behaviour.

#### Scenario: Within the limit
- **WHEN** a field with a limit of 3 receives three values
- **THEN** the values are saved

#### Scenario: Above the limit
- **WHEN** a field with a limit of 3 receives four values
- **THEN** the record is rejected with an error of the field, nothing is changed

#### Scenario: No limit
- **WHEN** a field with a limit of 0 receives twenty values
- **THEN** all values are saved

#### Scenario: Empty items are not counted
- **WHEN** a field with a limit of 2 receives two values and one empty item
- **THEN** the values are saved
