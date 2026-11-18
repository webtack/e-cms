# Spec Delta

## ADDED Requirements

### Requirement: Container of each field of the record form
The create and edit record forms SHALL place the slug field and each field of a type in its own container with a frame, whose caption (the legend) is the name of the field with the required mark, styled in the same way as the block of the file settings in the field form. A boolean field SHALL show the checkbox with its label inside the frame. Hints and error messages SHALL remain inside the container of their own field. The buttons "Save" and "Cancel" SHALL remain outside the containers, under them. The containers do not change the order of the fields, the required marks and the behaviour of the multiple fields.

#### Scenario: Each field in a frame
- **WHEN** the user opens the creation form of a record of a type with the fields `title` and `body`
- **THEN** the slug, `title` and `body` are each in their own container with a frame and a caption-name, and the save buttons are under them

#### Scenario: Errors inside the container
- **WHEN** the user saves the form with an empty required field
- **THEN** the error message is shown inside the container of this field

#### Scenario: Boolean field
- **WHEN** the type has a boolean field
- **THEN** its checkbox and label are in one line inside the frame

### Requirement: Multiple upload in multiple fields "image" and "file"
A multiple field of the type `image` or `file` SHALL, besides adding one item, allow selecting or dragging several files at once. Each selected file SHALL be uploaded by a separate request, and each successfully uploaded file SHALL be appended to the end of the list as a new item with the value `{uuid}.{extension}`. The files SHALL be checked against the settings of the field (the allowed types, the maximum size) in the same way as a single file; a rejected file does not become an item of the list, and the reason is shown with its name. Items added this way SHALL be edited by the general rules of multiple fields: removal, changing the order, replacing the file. For single (non-multiple) fields the behaviour does not change.

#### Scenario: Several files at once
- **WHEN** the user in the multiple field `image` selects three allowed images and saves the record
- **THEN** three items with previews appear in the list in the order of selection, and the record is saved with three values, and each file in the media library has one use

#### Scenario: Adding to existing ones
- **WHEN** the multiple field `file` already has two files, and the user selects two more
- **THEN** the list has four items, the new ones at the end

#### Scenario: Part of the files rejected
- **WHEN** the field allows only PNG, and the user selects two PNGs and one JPEG
- **THEN** two items appear in the list, and the error is named by the JPEG file's name

#### Scenario: Single field
- **WHEN** the field `file` is not multiple
- **THEN** the widget accepts one file, as before
