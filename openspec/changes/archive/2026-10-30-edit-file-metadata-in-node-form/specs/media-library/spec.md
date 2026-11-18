## ADDED Requirements

### Requirement: Description of a file
A storage file SHALL have an optional text description, which the user sets in the edit form of the record. The file details panel in the media library SHALL show the description if there is one, and SHALL NOT show an empty line if there is none. The original name, changed in the record form, SHALL be displayed in the media library (the column "Name", the title of the details panel) in the same way as any other name.

#### Scenario: Description in the details
- **WHEN** a file has a description, and the user opens its details in the media library
- **THEN** the panel shows this description

#### Scenario: File without a description
- **WHEN** a file has no description
- **THEN** the details panel shows no description line

#### Scenario: Renamed file
- **WHEN** the user changed the name of a file in the edit form of a record
- **THEN** the media library shows the new name in the table and in the title of the details panel, and searching by name finds the file by the new name
