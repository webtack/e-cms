## MODIFIED Requirements

### Requirement: Immutability of the value type of a field
The system SHALL forbid changing the value type of a field after its creation, regardless of the presence of stored values, and SHALL allow changing its name, weight and settings. Saving a field with the same type SHALL NOT be considered a change of type.

#### Scenario: Attempt to change the type
- **WHEN** the type of a field that has stored values is changed
- **THEN** the change is rejected, and the existing values do not change

#### Scenario: Attempt to change the type of a field without values
- **WHEN** the type of a field for which there are no stored values is changed
- **THEN** the change is rejected, and the type of the field remains the previous one

#### Scenario: Change of other properties
- **WHEN** the name, weight or settings of a field change, and the type remains the same
- **THEN** the changes are saved

### Requirement: Cascade deletion of configuration
The system SHALL forbid deleting a field that has stored values for any deletion path (admin, code, import), leaving the field and the values unchanged; a field without values SHALL be deleted. When a content type is deleted, the system SHALL delete its fields, records and their values.

#### Scenario: Deleting a field
- **WHEN** a non-system field without stored values is deleted
- **THEN** the field is deleted, and the values of the other fields are kept

#### Scenario: Deleting a field with values
- **WHEN** a field is deleted for which stored values exist (in at least one record or language)
- **THEN** the deletion is rejected, and the field and all its values remain unchanged

#### Scenario: Deleting a content type
- **WHEN** a content type whose fields have values is deleted
- **THEN** its fields, records and their values are deleted
