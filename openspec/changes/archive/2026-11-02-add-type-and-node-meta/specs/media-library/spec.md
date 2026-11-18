# Spec Delta

## ADDED Requirements

### Requirement: Зв'язки файлів значень типу
The system SHALL account for files in the values of the type itself (`meta_image`, `section_cover`) in the same way as in the values of records: by a link whose owner is the type (its identifier), with a role equal to the key of the field, and the language of the value. The links of a type SHALL be updated in the same transaction as the record of the value, and SHALL be removed when the type is deleted; the files themselves remain. The page «Де використовується файл» («Where the file is used») SHALL show the usage in a value of a type, with the name of the type and a link to the page of its settings.

#### Scenario: Attaching a file to a type
- **WHEN** a file is saved in `section_cover` of the type `news`
- **THEN** a link of this file with the type `news` exists, with the role `section_cover`, the language of the value, and the count of usages of the file has increased

#### Scenario: Replacing a file in a value of a type
- **WHEN** the file A is replaced by the file B in `meta_image` of a type
- **THEN** the link with A is removed, a link with B is created, the files remain

#### Scenario: Deleting a type
- **WHEN** a type that uses a file in `section_cover` is deleted
- **THEN** the links of this type are removed, the count of usages of the file has decreased, the file remains in the storage

#### Scenario: Usage on the media library page
- **WHEN** the user opens «Де використовується» for a file selected as `section_cover` of the type `news`
- **THEN** they see the type `news` with a link to the page of its settings
