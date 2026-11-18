# Spec Delta

## ADDED Requirements

### Requirement: Item limit of a multiple field in the record form
The record form SHALL respect the maximum number of items of a multiple field if it is greater than 0. A counter "count / limit" SHALL be shown next to the field. When there are as many items as allowed, the "Add item" button (in non-file fields) SHALL be unavailable, and the zone of multiple file upload (`image`, `file`) SHALL be unavailable with a hint about the reached limit. If the user selects more files than there are free places, only the first files in the order of selection SHALL be uploaded within the free places, and the rest SHALL be rejected with a message that names the limit. A field without a limit (`0`) SHALL behave as before. The server SHALL reject the excess regardless of the form, and the error SHALL be shown next to the field.

#### Scenario: Counter
- **WHEN** the user opens the record form with a multiple field with a limit of 3 and two items
- **THEN** "2 / 3" is visible next to the field

#### Scenario: Limit reached in a string field
- **WHEN** a multiple string field with a limit of 2 already has two items
- **THEN** the "Add item" button is unavailable

#### Scenario: Limit reached in an image field
- **WHEN** a multiple field `image` with a limit of 2 already has two images
- **THEN** the upload zone is unavailable and explains that the limit is reached

#### Scenario: More files selected than places
- **WHEN** the field `image` with a limit of 3 already has one image, and the user selects four allowed files
- **THEN** only the first two are uploaded, the list has three items, and the rest is named in the limit message

#### Scenario: Removal frees a place
- **WHEN** the limit is reached, and the user removes one item
- **THEN** adding is available again

#### Scenario: No limit
- **WHEN** the field has a limit of 0
- **THEN** there is no counter with a limit, and items can be added without restriction

#### Scenario: Server rejects the excess
- **WHEN** a save request bypasses the form and sends more items than the limit
- **THEN** the save is rejected, and an error is shown next to the field

## MODIFIED Requirements

### Requirement: Name and description of a file in the record form
The create and edit record forms SHALL, for each saved file of the field `image` or `file` (in a single field and in each row of a multiple one), show in the file block a button "Edit file data", which opens a modal window with the editable fields "Name" (the original file name) and "Description". The fields themselves SHALL NOT be displayed permanently in the file block. In a row of a multiple field the button SHALL stand in the place where the inner button for removing the file used to be (the file block in a row has no own removal); in a single field the button SHALL stand next to the button for removing the file. The window SHALL have the buttons "Done" (apply the entered data to the form and close) and "Cancel" (close, discarding the entered data; also Escape and closing from outside). What is applied SHALL be saved to the file together with the record of values: either everything is saved, or nothing; until "Save" of the record is pressed nothing is saved. The name SHALL be a non-empty string up to 255 characters; the description — an optional text, an empty description is stored as absent. A validation error SHALL be shown next to the file block (and in the modal window next to the corresponding field) without losing the entered data. The change SHALL concern only the files referenced by the values of this record; the extension of the saved file and the file on the disk itself do not change. The name and the description belong to the file: after saving they are the same everywhere the file is used.

#### Scenario: Fields do not take space in the block
- **WHEN** the user opens the record form with a file in the field `image`
- **THEN** the file block has no fields "Name" and "Description", only the button "Edit file data"

#### Scenario: Button in a row of a multiple field
- **WHEN** the user looks at a row of the multiple field `image`
- **THEN** the file block has no inner × button, and the button "Edit file data" stands in its place

#### Scenario: Opening the window
- **WHEN** the user presses "Edit file data"
- **THEN** a modal window opens with the fields "Name" and "Description" filled with the current values

#### Scenario: Changing the name
- **WHEN** the user in the window changes the name of the file from `IMG_0042.jpg` to `Обкладинка`, presses "Done", and then saves the record
- **THEN** the record is saved, reopening the form and the table of records show the name `Обкладинка`, and the extension of the file on the disk remains `jpg`

#### Scenario: Adding a description
- **WHEN** the user enters a description of the file in the window, presses "Done" and saves the record
- **THEN** after reopening the window the description is shown in the "Description" field

#### Scenario: Clearing the description
- **WHEN** the user erases the description in the window, presses "Done" and saves the record
- **THEN** the file has no description, and the "Description" field is empty

#### Scenario: Cancellation
- **WHEN** the user changes the name in the window and presses "Cancel"
- **THEN** the window closes, the name in the form has not changed, and it does not change with saving the record

#### Scenario: Empty name
- **WHEN** the user erases the name of the file in the window, presses "Done" and saves the record
- **THEN** the save is rejected, an error is shown next to the file block (and in the window next to the "Name" field), and nothing (neither the value of the record nor the metadata) is changed

#### Scenario: File in another record
- **WHEN** the file is used in another record as well, and the user changes its name
- **THEN** the other record and the media library show the new name

#### Scenario: File not in the values of the record
- **WHEN** the save request contains the metadata of a file that is not referenced by the values of this record
- **THEN** the metadata of this file does not change

#### Scenario: Name and description on creation
- **WHEN** the user in the creation form uploads a file, changes its name in the window, enters a description, presses "Done" and saves the record
- **THEN** the record is created, and the file has the new name and description

#### Scenario: Error on creation
- **WHEN** the user in the creation form erases the name of the uploaded file in the window and saves the record
- **THEN** the record is not created, an error is shown next to the file block, and the entered data is kept
