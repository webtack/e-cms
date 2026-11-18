## MODIFIED Requirements

### Requirement: Fields "image" and "file" in the form and the list of records
For a field of the type `image` or `file` the record form SHALL show a file upload widget with the profile of the field type (`image` — images, `file` — documents) instead of a separate choice from the media library: the user uploads a file, and the form stores the value `{uuid}.{extension}`. The widget SHALL take the allowed types and the maximum size from the settings of the field, reject an unsuitable file before uploading and show the reason; the server SHALL check them independently of the widget. The widget `image` SHALL show an image preview, the widget `file` — an icon and the file name. The edit form SHALL show the stored file and allow replacing or clearing it. A multiple field SHALL be edited as a list of widgets by the general rules of multiple fields; the file block in a row of a multiple field SHALL NOT have a separate button for removing the file — the file is removed by deleting the row. The file block of a single field SHALL keep the button for removing the file. In the table of records the value of an `image` or `file` field SHALL be output as the file name (the original one, and if it is absent — the stored value). Saving a record SHALL bind the chosen files to the record, and clearing or replacing SHALL release them without deleting the files from the storage.

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

#### Scenario: Removing a file from a multiple field
- **WHEN** the user removes a row with a file in the multiple field `image`
- **THEN** the row disappears from the list, and inside the file block there is no separate button for removing the file

#### Scenario: Clearing a single field
- **WHEN** the user opens the record form with a file in the single field `file`
- **THEN** the file block has a button for removing the file, and it clears the field

#### Scenario: File in the table of records
- **WHEN** the field `file` is visible in the list, and the record has a file
- **THEN** the cell shows the file name

#### Scenario: Deleting a record
- **WHEN** a record with a file is deleted
- **THEN** the file remains in the media library, and its number of uses decreases

### Requirement: Multiple upload in multiple fields "image" and "file"
A multiple field of the type `image` or `file` SHALL allow choosing or dragging several files at once and SHALL NOT have an "Add item" button: new items appear only by uploading files. Each selected file SHALL be uploaded by a separate request, and each successfully uploaded file SHALL be appended to the end of the list as a new item with the value `{uuid}.{extension}`. The files SHALL be checked against the settings of the field (the allowed types, the maximum size) in the same way as a single file; a rejected file does not become an item of the list, and the reason is shown with its name. Items added this way SHALL be edited by the general rules of multiple fields: removal, changing the order. Other multiple fields (string, number, etc.) and single fields do not change their behaviour; the "Add item" button in the non-file multiple fields remains.

#### Scenario: Several files at once
- **WHEN** the user in the multiple field `image` selects three allowed images and saves the record
- **THEN** three items with previews appear in the list in the order of selection, and the record is saved with three values, and each file in the media library has one use

#### Scenario: Adding to existing ones
- **WHEN** the multiple field `file` already has two files, and the user selects two more
- **THEN** the list has four items, the new ones at the end

#### Scenario: Part of the files rejected
- **WHEN** the field allows only PNG, and the user selects two PNGs and one JPEG
- **THEN** two items appear in the list, and the error is named by the JPEG file's name

#### Scenario: No "Add item" button
- **WHEN** the user opens the record form with a multiple field `image` or `file`
- **THEN** there is no "Add item" button in this field, and the zone for selecting several files is present

#### Scenario: Another multiple field
- **WHEN** the user opens the record form with a multiple string field
- **THEN** the "Add item" button is present and adds an empty item

#### Scenario: Single field
- **WHEN** the field `file` is not multiple
- **THEN** the widget accepts one file, as before

## ADDED Requirements

### Requirement: Name and description of a file in the record form
The create and edit record forms SHALL, under each saved file of the field `image` or `file` (in a single field and in each row of a multiple one), show the editable fields "Name" (the original file name) and "Description". Saving the record SHALL write the changed name and description to the file together with the record of values: either everything is saved, or nothing. The name SHALL be a non-empty string up to 255 characters; the description — an optional text, an empty description is stored as absent. A validation error SHALL be shown next to the corresponding file field without losing the entered text. The change SHALL concern only the files referenced by the values of this record; the extension of the saved file and the file on the disk itself do not change. The name and the description belong to the file: after saving they are the same everywhere the file is used.

#### Scenario: Changing the name
- **WHEN** the user in the edit form changes the name of the file from `IMG_0042.jpg` to `Cover` and saves the record
- **THEN** the record is saved, reopening the form and the table of records show the name `Cover`, and the extension of the file on the disk remains `jpg`

#### Scenario: Adding a description
- **WHEN** the user enters a description of the file and saves the record
- **THEN** after reopening the form the description is shown in the "Description" field

#### Scenario: Clearing the description
- **WHEN** the user erases the description and saves the record
- **THEN** the file has no description, and the "Description" field is empty

#### Scenario: Empty name
- **WHEN** the user erases the name of the file and saves the record
- **THEN** the save is rejected, an error is shown next to the "Name" field, and nothing (neither the value of the record nor the metadata) is changed

#### Scenario: File in another record
- **WHEN** the file is used in another record as well, and the user changes its name
- **THEN** the other record and the media library show the new name

#### Scenario: File not in the values of the record
- **WHEN** the save request contains the metadata of a file that is not referenced by the values of this record
- **THEN** the metadata of this file does not change

#### Scenario: Name and description on creation
- **WHEN** the user in the creation form uploads a file, changes its name, enters a description and saves the record
- **THEN** the record is created, and the file has the new name and description

#### Scenario: Error on creation
- **WHEN** the user in the creation form erases the name of the uploaded file and saves the record
- **THEN** the record is not created, an error is shown next to the "Name" field, and the entered data is kept

### Requirement: Field description in the record form
If a field of a content type has a description, the create and edit record forms SHALL show it right after the label (legend) of the field, inside the field container, as helper text. This applies to single, multiple and boolean fields. A field without a description SHALL have no empty line under the label.

#### Scenario: Field with a description
- **WHEN** the field `title` has the description "Short name up to 60 characters", and the user opens the record form
- **THEN** this description is shown under the label of the field

#### Scenario: Field without a description
- **WHEN** the field has no description
- **THEN** nothing additional is shown under the label of the field

#### Scenario: Boolean field
- **WHEN** a boolean field has a description
- **THEN** the description is shown inside the frame of this field next to the checkbox and the label
