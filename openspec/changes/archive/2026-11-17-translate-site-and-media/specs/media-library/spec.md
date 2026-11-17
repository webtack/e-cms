# Spec Delta

## ADDED Requirements

### Requirement: Translated file names and descriptions
A storage file SHALL be able to have a display name and a description per additional language besides its base name and description. A translation belongs to the file and is shared by every place that uses the file. The translated name is for display only: the original name (`original_name`), the path and the extension of the file SHALL never change. A translated name or description SHALL be stored only when it differs from the base one and SHALL be removed when emptied; a missing translation reads as missing (the original name or the base description is shown instead, and the public fallback is a separate rule). Deleting a file SHALL delete its translations. A language that has a translated name or description SHALL NOT be deletable. The details panel of the media library SHALL show, besides the base name and description, the translations with their language names, and SHALL show no row for a file without any translation or description.

#### Scenario: Stored translation
- **WHEN** the English description "Front cover" and the English name "Cover" are saved for a file whose base description is "Лицьова обкладинка"
- **THEN** the file has both descriptions and both names, and the original name, the path and the base description are unchanged

#### Scenario: Equal to the base
- **WHEN** an English name or description equal to the base one is saved
- **THEN** nothing is stored for English

#### Scenario: Details panel
- **WHEN** a file has a base description and an English description and the user opens its details
- **THEN** the panel shows the base description and the English name and description labelled with the language

#### Scenario: Deleting the file
- **WHEN** an unused file with translations is deleted
- **THEN** its translations are deleted too

#### Scenario: Deleting a language
- **WHEN** a language that has a translated file name or description is deleted
- **THEN** the deletion is rejected
