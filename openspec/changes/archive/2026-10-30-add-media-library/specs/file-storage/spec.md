# Spec Delta

## MODIFIED Requirements

### Requirement: File records
The system SHALL store each uploaded file as a separate record with its own identity: an internal numeric identifier, an immutable `uuid` without hyphens (32 characters) as the single external identifier, the upload profile, the disk, the path on the disk, the original name, the detected type (mime), the size in bytes and the content hash; for images also the width and height in pixels; the user who uploaded the file (empty if the user is unknown or deleted). Consumers SHALL refer to a file by its `uuid`.

#### Scenario: Document metadata
- **WHEN** the PDF file `report.pdf` of 1 MB is uploaded
- **THEN** a record is created with the original name `report.pdf`, the type `application/pdf`, the size in bytes, the content hash, the profile, the disk and the path, and the width and height are absent

#### Scenario: Image metadata
- **WHEN** a PNG image of 640×480 is uploaded
- **THEN** the record contains the type `image/png`, the width 640 and the height 480

#### Scenario: Format of the uuid
- **WHEN** a file record is created
- **THEN** its `uuid` consists of 32 characters `0-9a-f` without hyphens and does not change after creation

#### Scenario: User who uploaded
- **WHEN** an authenticated administrator uploads a file
- **THEN** the record captures this user as the uploader

#### Scenario: Deleting the user
- **WHEN** the user who uploaded a file is deleted
- **THEN** the file and its record remain, and the uploader becomes unknown

#### Scenario: Identical content
- **WHEN** a file with identical content is uploaded twice
- **THEN** two separate records with different `uuid` values and an identical hash are created

### Requirement: Upload profiles
The system SHALL describe upload rules as named profiles in the developer's configuration: each profile defines the disk, the allowed types and the maximum size. A profile SHALL NOT define a directory: the file is stored in the root of the disk, and the location is set by the standard setting of this disk. The user SHALL NOT choose the disk and SHALL NOT set the rules during the upload. At the start there SHALL be the profiles `image` (images JPEG, PNG, WebP, GIF, up to 5 MB) and `document` (PDF, DOCX, XLSX, up to 20 MB).

#### Scenario: Disk from the profile
- **WHEN** a file is uploaded by the profile `image`
- **THEN** the file is stored in the root of the disk specified in the configuration of this profile, and the record captures this disk

#### Scenario: Unknown profile
- **WHEN** an upload is requested by a profile that is not in the configuration
- **THEN** the upload is rejected with a validation error, and the file is not stored

#### Scenario: New profile without code changes
- **WHEN** the developer adds a profile to the configuration
- **THEN** it is available for uploads without changes to other parts of the system

#### Scenario: Different profiles on one disk
- **WHEN** files of the profiles `image` and `document` are stored on one disk
- **THEN** both lie in the root of the disk under the names `{uuid}.{extension}` and do not conflict

### Requirement: Security of stored files
The system SHALL reject executable files (in particular `php`, `phtml`, `sh`, `exe`) and SVG regardless of the profile, SHALL name the file on the disk `{uuid}.{extension}`, where `uuid` is the identifier of its record and the extension is defined by the allowed type, and SHALL keep the original name only as metadata.

#### Scenario: Executable file
- **WHEN** the file `shell.php` is uploaded under any profile, including a profile that allows arbitrary types
- **THEN** the upload is rejected

#### Scenario: SVG
- **WHEN** an SVG image is uploaded by the profile `image`
- **THEN** the upload is rejected

#### Scenario: Name on the disk
- **WHEN** a file with the original name `../../evil name.png` is uploaded
- **THEN** on the disk the file lies in the root of the disk of the profile under the name `{uuid}.png`, and the original name is stored only as metadata

### Requirement: Uploading a file in a separate request
The system SHALL accept a file in a separate admin request (multipart) only from an authenticated user with a confirmed email. The request SHALL specify either an upload profile or a field of a content type (its type and key), but not both at once. For a field the system SHALL take the profile from the type of the field and apply the settings of the field that narrow the rules of the profile (the allowed types and the maximum size); the rules SHALL NOT be wider than the profile. In the response the system SHALL return the metadata of the created record: `uuid`, profile, original name, type, size, width and height (for images) and the link to the file. Forms that use a file SHALL pass on only its `uuid`.

#### Scenario: Successful upload
- **WHEN** an authorized administrator sends a file and the profile `image`
- **THEN** the response contains the `uuid` and the metadata of the record and the link by which the file is available

#### Scenario: Upload by field
- **WHEN** an administrator sends a file and the field `image` that allows only `image/png`, and the file is a PNG within the size of the field
- **THEN** the file is accepted, and the profile of the record equals the profile of the type of the field

#### Scenario: Type outside the field settings
- **WHEN** an administrator sends a JPEG into the field that allows only `image/png`
- **THEN** the upload is rejected with a validation error, and nothing is created on the disk

#### Scenario: Size above the field settings
- **WHEN** an administrator sends a file larger than the `max_size` of the field, but within the profile
- **THEN** the upload is rejected with a validation error

#### Scenario: Profile and field at the same time
- **WHEN** the request contains both a profile and a field
- **THEN** the response contains a validation error, and no record is created

#### Scenario: Unknown field
- **WHEN** the request specifies a field that does not exist, or a field of another type (not `image`/`file`)
- **THEN** the response contains a validation error, and no record is created

#### Scenario: Guest
- **WHEN** an upload request arrives without authentication
- **THEN** the file is not accepted, and no record is created

#### Scenario: No file
- **WHEN** the request is sent without a file or without a profile or field
- **THEN** the response contains validation errors, and no record is created

### Requirement: Validation of a file reference in consumers
The system SHALL provide consumers with a validation rule that accepts a value only when it is the `uuid` of an existing file uploaded under the specified profile.

#### Scenario: File of the required profile
- **WHEN** the value is the `uuid` of a file uploaded under the profile `image`, and the consumer requires the profile `image`
- **THEN** the check passes

#### Scenario: File of another profile
- **WHEN** the value is the `uuid` of a file of the profile `document`, and the consumer requires the profile `image`
- **THEN** the check fails with a validation error

#### Scenario: Non-existing file
- **WHEN** the value matches no file
- **THEN** the check fails with a validation error

#### Scenario: Invalid format
- **WHEN** the value is not a string of 32 characters `0-9a-f`
- **THEN** the check fails with a validation error

### Requirement: Protection of files in use
The system SHALL not allow the deletion of a file record for which a usage relation exists (see `media-library`), and a consumer SHALL release a file only by changing or clearing its own value, without deleting the file itself.

#### Scenario: Attempt to delete a file in use
- **WHEN** the record of a file that has a usage relation is deleted
- **THEN** the deletion is rejected, the record and the file on the disk remain

#### Scenario: Replacement of a file by a consumer
- **WHEN** a consumer replaces a value from one file with another
- **THEN** the old record and the file on the disk remain in the storage

### Requirement: Upload widget in the admin
The admin SHALL provide a reusable file upload component: file selection or drag-and-drop, an indication of the upload, display of validation errors, preview of an uploaded image, clearing of the selection and passing the `uuid` of the file to the form. The component SHALL upload the file in a separate request to the form.

#### Scenario: Successful upload
- **WHEN** the user selects an allowed file
- **THEN** the component shows the progress, and after completion — the file name (for an image — its preview) and passes the `uuid` of the file to the form

#### Scenario: Rejected file
- **WHEN** the user selects a file of a disallowed type or one that is too large
- **THEN** the component shows an error message, and the `uuid` is not passed to the form

#### Scenario: Clearing
- **WHEN** the user clears the selected file
- **THEN** the component removes the value from the form

#### Scenario: Demo page
- **WHEN** a developer opens the demo page of the component outside production
- **THEN** it shows the component for the profiles `image` and `document`; in production the page responds 404

## REMOVED Requirements

### Requirement: Cleanup of unused files
**Reason**: Unused files are now deleted manually by the administrator in the media library, where the number of uses of each file is visible; automatic cleanup on a schedule (and the configuration of the list of reference places) is no longer needed.
**Migration**: Deleting unused files — on the media library page (see `media-library`, "Manual deletion of unused files"). The command `files:prune`, its schedule and the settings `references` and `prune_after_hours` are removed.
