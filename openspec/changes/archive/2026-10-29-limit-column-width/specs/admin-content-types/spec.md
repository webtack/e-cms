## ADDED Requirements

### Requirement: Limiting the width of the name column
The name column of the types table SHALL have a limited width: a long name and the description under it SHALL each be truncated to one line with an ellipsis, without stretching the table or pushing the remaining columns out. The full text of the description SHALL be available in the hint.

#### Scenario: Long description
- **WHEN** a type has a description longer than the width of the name column
- **THEN** the description takes one line and ends with an ellipsis, and the remaining columns stay in place

#### Scenario: Long name
- **WHEN** the name of a type is longer than the width of the column
- **THEN** the name takes one line and ends with an ellipsis

#### Scenario: Full description in the hint
- **WHEN** the user hovers over the truncated description
- **THEN** they see the full text of the description
