# Spec Delta

## ADDED Requirements

### Requirement: Short label of a file type
The admin SHALL show the file type by a short extension label (`pdf`, `docx`, `xlsx`, `jpg`, `png`) instead of the full MIME type in all places where the type is output. The label SHALL match the extension that the upload profiles set for this type. For a type that is not in any profile, the part of the MIME after `/` SHALL be shown. The stored value of the file type (the full MIME) and the file validation rules SHALL remain unchanged.

#### Scenario: Type from a profile
- **WHEN** the admin outputs the file type `application/vnd.openxmlformats-officedocument.wordprocessingml.document`
- **THEN** `docx` is shown

#### Scenario: Extension of stored files
- **WHEN** the admin outputs the type `image/jpeg`
- **THEN** `jpg` is shown, as in the extension of the stored file

#### Scenario: Type outside the profiles
- **WHEN** a file has the type `text/plain`, which is no longer in any profile
- **THEN** `plain` is shown, and the page does not fail

### Requirement: Uploading several files with the widget
The upload widget SHALL have a multiple selection mode: the user selects or drags several files at once. Each file SHALL be uploaded by a separate request to the same upload endpoint as a single file, one after another, not by one shared request. Each file SHALL pass a quick check of type and size before the request. The widget SHALL notify the form about each successfully uploaded file; an error of one file (a quick rejection, a server response, a network failure) SHALL be shown with the name of this file and SHALL NOT cancel the upload of the rest. During the upload the widget SHALL show the progress (how many files out of how many are processed) and SHALL NOT accept a new selection until the queue is finished.

#### Scenario: Several allowed files
- **WHEN** the user selects three allowed files
- **THEN** the server receives three separate upload requests, and the form receives three files in the order of selection

#### Scenario: One file rejected
- **WHEN** the user selects three files, and one has a disallowed type
- **THEN** the two allowed files are uploaded and passed to the form, and the widget shows an error with the name of the third file

#### Scenario: Failure during the queue
- **WHEN** the upload request of the second of three files ends with a network error
- **THEN** the first and the third files are uploaded, and the error is named by the name of the second

#### Scenario: Progress
- **WHEN** the upload of a queue of three files is in progress
- **THEN** the widget shows how many files are processed, and does not allow starting a new selection
