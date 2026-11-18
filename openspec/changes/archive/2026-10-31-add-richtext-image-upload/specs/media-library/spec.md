# Spec Delta

## ADDED Requirements

### Requirement: Зв'язки зображень форматованого тексту
The system SHALL maintain the links of files for the images in the field `richtext` in the same transaction as the record of the value: after a write, the set of links of the owner for the role (the field key) and the language SHALL match the set of files referenced by the images of the saved value. Removing an image from the text SHALL remove the link, and the file remains in the storage. A write error SHALL NOT leave partially updated links.

#### Scenario: Binding
- **WHEN** a value with two different images is saved in the field `richtext` of a record
- **THEN** there are two links of this record with the role equal to the field key

#### Scenario: The same image twice
- **WHEN** the same image is inserted into the text twice
- **THEN** there is one link

#### Scenario: Removing an image from the text
- **WHEN** the record is saved after an image was removed from the text
- **THEN** the link with this file is removed, the file and its record remain

#### Scenario: Separate languages
- **WHEN** an image is inserted into the translation `uk`, and it is not in the main language
- **THEN** the link exists only for the language `uk`

#### Scenario: Deleting a record or a field
- **WHEN** a record or a field `richtext` with images is deleted
- **THEN** the links are removed, the files remain in the storage
