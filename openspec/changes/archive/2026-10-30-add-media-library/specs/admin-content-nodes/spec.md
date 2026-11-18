# Spec Delta

## ADDED Requirements

### Requirement: Fields "image" and "file" in the form and the list of records
For a field of the type `image` or `file` the record form SHALL show a file upload widget with the profile of the field type (`image` — images, `file` — documents) instead of a separate choice from the media library: the user uploads a file, and the form stores the value `{uuid}.{extension}`. The widget SHALL take the allowed types and the maximum size from the settings of the field, reject an unsuitable file before uploading and show the reason; the server SHALL check them independently of the widget. The widget `image` SHALL show an image preview, the widget `file` — an icon and the file name. The edit form SHALL show the stored file and allow replacing or clearing it. A multiple field SHALL be edited as a list of widgets by the general rules of multiple fields. In the table of records the value of an `image` or `file` field SHALL be output as the file name (the original one, and if it is absent — the stored value). Saving a record SHALL bind the chosen files to the record, and clearing or replacing SHALL release them without deleting the files from the storage.

#### Scenario: Upload in the form
- **WHEN** the user in the record form selects a file in the field `file` (or an image in the field `image`) and saves the record
- **THEN** the record is saved with the value `{uuid}.{extension}`, and the file in the media library has one use

#### Scenario: File outside the field settings
- **WHEN** the field `image` allows only PNG, and the user selects a JPEG
- **THEN** the widget shows an error, the file does not become a value of the form, and if the request bypasses the widget, the server rejects the file

#### Scenario: Showing a stored image
- **WHEN** the user opens the edit form of a record with a file in the field `image`
- **THEN** they see an image preview and the file name

#### Scenario: Showing a stored file
- **WHEN** the user opens the edit form of a record with a file in the field `file`
- **THEN** they see an icon and the file name without an image preview

#### Scenario: Replacing a file
- **WHEN** the user replaces the file in the field `file` with another one and saves the record
- **THEN** the record references the new file, and the old file remains in the media library with 0 uses

#### Scenario: Clearing
- **WHEN** the user clears the field `file` or `image` and saves the record
- **THEN** the value is absent, and the file remains in the media library

#### Scenario: File in the table of records
- **WHEN** the field `file` is visible in the list, and the record has a file
- **THEN** the cell shows the file name

#### Scenario: Deleting a record
- **WHEN** a record with a file is deleted
- **THEN** the file remains in the media library, and its number of uses decreases
