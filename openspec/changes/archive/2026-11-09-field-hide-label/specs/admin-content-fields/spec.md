## ADDED Requirements

### Requirement: Hiding the label of a field
A content type field SHALL have the setting "Hide label" — a boolean, off by default. The create and edit forms of a field SHALL contain the checkbox "Hide label" with a hint that it only affects the public page: when it is on, the public templates do not print the name of the field as a label. The setting SHALL be available for every value type, including system fields, and SHALL be changeable for a field that has values. The setting does not affect the value of the field, its validation, or its storage, and the node forms in the admin panel SHALL keep showing the label of the field.

#### Scenario: Default setting
- **WHEN** the user creates a field and does not touch "Hide label"
- **THEN** the field is saved with the label shown, and the edit form shows the checkbox off

#### Scenario: Hiding the label of a new field
- **WHEN** the user creates a field with "Hide label" on
- **THEN** the field is saved with the label hidden, and the edit form shows the checkbox on

#### Scenario: Showing the label again
- **WHEN** the user turns "Hide label" off in the edit form of a field and saves
- **THEN** the field is saved with the label shown

#### Scenario: System field
- **WHEN** the user turns "Hide label" on for the system field `title`
- **THEN** the setting is saved, and the other restrictions of the system field remain in force

#### Scenario: The node form keeps the label
- **WHEN** a field has "Hide label" on and its node is edited in the admin panel
- **THEN** the node form shows the label of the field as for any other field
