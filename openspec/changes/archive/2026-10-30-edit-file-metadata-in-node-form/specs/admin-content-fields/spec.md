## ADDED Requirements

### Requirement: Field description
A content type field SHALL have an optional text description — an instruction for the editor of the record. The create and edit field forms SHALL contain a multi-line "Description" field up to 1000 characters long; an empty value (also consisting only of spaces) is stored as an absent description. The description SHALL be changeable also for system fields. Exceeding the length SHALL be rejected with an error next to the "Description" field without losing the entered text. The description does not affect the value of the field, its validation and storage. The record forms show the description after the label of the field.

#### Scenario: Creating a field with a description
- **WHEN** the user creates a field and enters a description
- **THEN** the field is saved with the description, and the edit form of the field shows it

#### Scenario: Clearing the description
- **WHEN** the user erases the description in the edit form of the field and saves
- **THEN** the field has no description

#### Scenario: Too long description
- **WHEN** the user enters a description longer than 1000 characters and saves
- **THEN** the save is rejected, an error is shown next to the "Description" field, and the entered text is kept

#### Scenario: System field
- **WHEN** the user edits the system field `title` and sets a description
- **THEN** the description is saved, and the other restrictions of the system field remain in force

#### Scenario: Field without a description
- **WHEN** the field is created without a description
- **THEN** it is saved without a description, and the record form shows no helper text
