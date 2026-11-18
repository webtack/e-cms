# Spec Delta

## Purpose

Provides a single shared mechanism for storing files: it accepts a file, checks it against an upload profile, saves it to a disk, keeps the metadata records and gives a link, so that consumers (fields, avatar, settings) store only a reference to the file and have no own logic for working with the disk.

## ADDED Requirements

### Requirement: File records
The system SHALL store each uploaded file as a separate record with its own identity: a stable numeric identifier, an immutable `uuid`, the upload profile, the disk, the path on the disk, the original name, the detected type (mime), the size in bytes and the content hash; for images also the width and height in pixels. Consumers SHALL refer to a file by its identifier.

#### Scenario: Document metadata
- **WHEN** the PDF file `report.pdf` of 1 MB is uploaded
- **THEN** a record is created with the original name `report.pdf`, the type `application/pdf`, the size in bytes, the content hash, the profile, the disk and the path, and the width and height are absent

#### Scenario: Image metadata
- **WHEN** a PNG image of 640×480 is uploaded
- **THEN** the record contains the type `image/png`, the width 640 and the height 480

#### Scenario: Identical content
- **WHEN** a file with identical content is uploaded twice
- **THEN** two separate records with different identifiers and an identical hash are created

### Requirement: Upload profiles
The system SHALL describe upload rules as named profiles in the developer's configuration: each profile defines the disk, the directory, the allowed types and the maximum size. The user SHALL NOT choose the disk and SHALL NOT set the rules during the upload. At the start there SHALL be the profiles `image` (images JPEG, PNG, WebP, GIF, up to 5 MB) and `document` (PDF, DOCX, XLSX, up to 20 MB).

#### Scenario: Disk from the profile
- **WHEN** a file is uploaded by the profile `image`
- **THEN** the file is stored on the disk specified in the configuration of this profile, in its directory, and the record captures this disk

#### Scenario: Unknown profile
- **WHEN** an upload is requested by a profile that is not in the configuration
- **THEN** the upload is rejected with a validation error, and the file is not stored

#### Scenario: New profile without code changes
- **WHEN** the developer adds a profile to the configuration
- **THEN** it is available for uploads without changes to other parts of the system

### Requirement: Checking a file against the profile
The system SHALL reject a file before it is saved to the disk if its type is not among the types allowed by the profile or its size exceeds the profile's maximum. The type SHALL be determined by the content of the file, not by the extension or the header sent by the client.

#### Scenario: Allowed file
- **WHEN** a genuine JPEG image within the limit is uploaded by the profile `image`
- **THEN** the file is accepted

#### Scenario: Disallowed type
- **WHEN** a PDF file is uploaded by the profile `image`
- **THEN** the upload is rejected with a validation error, and nothing is created on the disk

#### Scenario: Faked extension
- **WHEN** a text or executable file is named `photo.jpg` and uploaded by the profile `image`
- **THEN** the upload is rejected, because the content is not an allowed image

#### Scenario: Size exceeded
- **WHEN** an image larger than 5 MB is uploaded by the profile `image`
- **THEN** the upload is rejected with a validation error, and nothing is created on the disk

### Requirement: Security of stored files
The system SHALL reject executable files (in particular `php`, `phtml`, `sh`, `exe`) and SVG regardless of the profile, SHALL name the file on the disk with a generated name that does not depend on the original one, and SHALL keep the original name only as metadata.

#### Scenario: Executable file
- **WHEN** the file `shell.php` is uploaded under any profile, including a profile that allows arbitrary types
- **THEN** the upload is rejected

#### Scenario: SVG
- **WHEN** an SVG image is uploaded by the profile `image`
- **THEN** the upload is rejected

#### Scenario: Name on the disk
- **WHEN** a file with the original name `../../evil name.png` is uploaded
- **THEN** on the disk the file lies under a generated name in the directory of the profile, and the original name is stored only as metadata

### Requirement: Uploading a file in a separate request
The system SHALL accept a file in a separate admin request (multipart) with the specified profile only from an authenticated user with a confirmed email, and SHALL return in the response the metadata of the created record: the identifier, `uuid`, profile, original name, type, size, width and height (for images) and the link to the file. Forms that use a file SHALL pass on only its identifier.

#### Scenario: Successful upload
- **WHEN** an authorized administrator sends a file and the profile `image`
- **THEN** the response contains the identifier and the metadata of the record and the link by which the file is available

#### Scenario: Guest
- **WHEN** an upload request arrives without authentication
- **THEN** the file is not accepted, and no record is created

#### Scenario: No file
- **WHEN** the request is sent without a file or without a profile
- **THEN** the response contains validation errors, and no record is created

### Requirement: Link to a file
The system SHALL provide a single way to obtain a link to a file by its record, which takes into account the disk of the file, so that consumers do not depend on where the file actually lies.

#### Scenario: Public disk
- **WHEN** a link to a file stored on the public disk is requested
- **THEN** an address is returned by which the file is served by the web server

#### Scenario: Independence from the consumer
- **WHEN** the disk of a profile is changed in the configuration for new files
- **THEN** links to previously uploaded files continue to be built by the disk stored in their records

### Requirement: Validation of a file reference in consumers
The system SHALL provide consumers with a validation rule that accepts a value only when it is the identifier of an existing file uploaded under the specified profile.

#### Scenario: File of the required profile
- **WHEN** the value is the identifier of a file uploaded under the profile `image`, and the consumer requires the profile `image`
- **THEN** the check passes

#### Scenario: File of another profile
- **WHEN** the value is the identifier of a file of the profile `document`, and the consumer requires the profile `image`
- **THEN** the check fails with a validation error

#### Scenario: Non-existing file
- **WHEN** the value matches no file
- **THEN** the check fails with a validation error

### Requirement: Protection of files in use
The system SHALL not allow the deletion of a file record that is referenced by any consumer, and a consumer SHALL release a file only by changing or clearing its own reference, without deleting the file itself.

#### Scenario: Attempt to delete a file in use
- **WHEN** the record of a file, which a field value references, is deleted
- **THEN** the deletion is rejected, the record and the file on the disk remain

#### Scenario: Replacement of a file by a consumer
- **WHEN** a consumer replaces the reference to a file with another one
- **THEN** the old record and the file on the disk remain until the next cleanup

### Requirement: Cleanup of unused files
The system SHALL provide a cleanup command that deletes files (the record and the file on the disk) which no one references and which were created more than 24 hours ago, and SHALL run it on a schedule. The list of places that contain references to files SHALL be specified in the configuration. The command SHALL NOT delete files that have references and files younger than 24 hours, and an error in deleting one file SHALL NOT interrupt the cleanup of the rest.

#### Scenario: Orphaned file
- **WHEN** a file was created more than 24 hours ago and no consumer references it
- **THEN** the command deletes the record and the file on the disk

#### Scenario: Fresh file
- **WHEN** a file was uploaded an hour ago and is not yet attached to a consumer
- **THEN** the command does not delete it

#### Scenario: File in use
- **WHEN** a consumer references a file created more than 24 hours ago
- **THEN** the command does not delete it

#### Scenario: File missing on the disk
- **WHEN** a file is unused but already missing on the disk
- **THEN** the command deletes the record and finishes without an error

#### Scenario: Summary
- **WHEN** the command finishes
- **THEN** it reports how many files were deleted

### Requirement: Upload widget in the admin
The admin SHALL provide a reusable file upload component: file selection or drag-and-drop, an indication of the upload, display of validation errors, preview of an uploaded image, clearing of the selection and passing the file identifier to the form. The component SHALL upload the file in a separate request to the form.

#### Scenario: Successful upload
- **WHEN** the user selects an allowed file
- **THEN** the component shows the progress, and after completion — the file name (for an image — its preview) and passes the file identifier to the form

#### Scenario: Rejected file
- **WHEN** the user selects a file of a disallowed type or one that is too large
- **THEN** the component shows an error message, and the file identifier is not passed to the form

#### Scenario: Clearing
- **WHEN** the user clears the selected file
- **THEN** the component removes the value from the form

#### Scenario: Demo page
- **WHEN** a developer opens the demo page of the component outside production
- **THEN** it shows the component for the profiles `image` and `document`; in production the page responds 404
