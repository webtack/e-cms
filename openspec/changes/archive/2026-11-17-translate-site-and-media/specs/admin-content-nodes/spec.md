# Spec Delta

## ADDED Requirements

### Requirement: File names and descriptions in the translation mode of a node
In the translation mode of a node, every `image` or `file` field SHALL let the user edit the display name and the description of each file in its value in the current language, whether or not the field itself is translatable. A name and a description SHALL be prefilled with the translated value or, when none exists, with the base one (the original name, the base description). For a field that is not translatable the file itself SHALL be shown but not changeable; for a translatable field the file may also be changed as for any translatable field. Saving SHALL store only names and descriptions that differ from the base, SHALL remove one that is emptied, and SHALL NOT change the original name or the base description. The same file in several nodes SHALL show the same translated name and description everywhere.

#### Scenario: Description of a non-translatable image
- **WHEN** a node has a non-translatable `image` field and the user opens the English mode
- **THEN** the image is shown with an editable name and description prefilled from the base ones, and the image cannot be replaced

#### Scenario: Saving a translated description
- **WHEN** the user changes the prefilled description to "Front cover" and the name to "Cover" and saves
- **THEN** the English name and description of that file are stored and the original name and the base description are unchanged

#### Scenario: Shared file
- **WHEN** the same file is used in another node
- **THEN** that node's English mode shows the same English name and description

#### Scenario: Original name is kept
- **WHEN** the user saves a translated name
- **THEN** the original name of the file is unchanged and the base mode still shows it
