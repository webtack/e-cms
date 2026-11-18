# Spec Delta

## ADDED Requirements

### Requirement: Short labels of types in the settings of the file field
The list of allowed file types in the settings of the fields "image" and "file" SHALL show each type by a short extension label (for example, `pdf`, `docx`, `xlsx`) instead of the full MIME type. The saved settings of the field SHALL still contain the full MIME types, and already saved fields SHALL be displayed with the same labelled types without changing the data.

#### Scenario: List of types
- **WHEN** the user opens the form of a field of the type `file`
- **THEN** the list of allowed types shows `pdf`, `docx` and `xlsx`, without long MIME strings

#### Scenario: Saving the choice
- **WHEN** the user selects the mark `docx` and saves the field
- **THEN** the full MIME type of the `docx` document is stored in the settings of the field, and after reopening the form the mark `docx` is checked
