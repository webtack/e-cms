# file-storage Specification

## Purpose
Provides a single shared mechanism for saving files: it accepts a file, checks it against an upload profile, saves it to disk, keeps the metadata, and provides links, so that consumers (fields, avatars, settings) store only a reference to the file and have no logic of their own for working with the disk.

## Requirements

### Requirement: File records
The system SHALL store each uploaded file as a separate record with its own identity: an internal numeric identifier, an immutable `uuid` without hyphens (32 characters) as the only external identifier, the upload profile, the disk, the path on the disk, the original name, the determined type (mime), the size in bytes, and the content hash; for images also the width and height in pixels; the user who uploaded the file (empty if the user is unknown or deleted). Consumers SHALL refer to a file by its `uuid`.

#### Scenario: Document metadata
- **WHEN** a PDF file `report.pdf` of 1 MB is uploaded
- **THEN** a record is created with the original name `report.pdf`, the type `application/pdf`, the size in bytes, the content hash, the profile, the disk, and the path, and the width and height are absent

#### Scenario: Image metadata
- **WHEN** a PNG image 640×480 is uploaded
- **THEN** the record contains the type `image/png`, width 640, and height 480

#### Scenario: Format of uuid
- **WHEN** a file record is created
- **THEN** its `uuid` consists of 32 characters `0-9a-f` without hyphens and does not change after creation

#### Scenario: User who uploaded
- **WHEN** an authenticated administrator uploads a file
- **THEN** the record captures this user as the uploader

#### Scenario: Deleting a user
- **WHEN** the user who uploaded a file is deleted
- **THEN** the file and its record remain, and the uploader becomes unknown

#### Scenario: Identical content
- **WHEN** a file with identical content is uploaded twice
- **THEN** two separate records with different `uuid`s and the same hash are created

### Requirement: Upload profiles
The system SHALL describe the upload rules by named profiles in the developer's configuration: each profile defines the disk, the allowed types, and the maximum size, and an image profile may additionally define the maximum width and height in pixels (each separately; without a value the side is not limited). A profile SHALL NOT define a directory: the file is saved to the root of the disk, and the location is set by the standard setting of this disk. The user SHALL NOT choose the disk or set the rules during upload. At the start the profiles `image` (JPEG, PNG, WebP, GIF images, up to 30 MB, by default downscaled to 1920×1920 pixels; the limits and the other image processing parameters, as well as the maximum sizes of the profiles, the developer sets by the environment variables of `.env`) and `document` (PDF, DOC, DOCX, XLSX, up to 30 MB) SHALL exist.

#### Scenario: Disk from the profile
- **WHEN** a file is uploaded with the `image` profile
- **THEN** the file is saved to the root of the disk specified in the configuration of this profile, and the record captures this disk

#### Scenario: Unknown profile
- **WHEN** an upload is requested with a profile that is not in the configuration
- **THEN** the upload is rejected with a validation error, and the file is not saved

#### Scenario: New profile without code changes
- **WHEN** the developer adds a profile to the configuration
- **THEN** it is available for upload without changes to other parts of the system

#### Scenario: Different profiles on one disk
- **WHEN** files of the profiles `image` and `document` are saved on one disk
- **THEN** both lie at the root of the disk under the names `{uuid}.{extension}` and do not conflict

#### Scenario: Size limits of an image in a profile
- **WHEN** the developer sets for the profile `image` `max_width` 1200 and `max_height` 800
- **THEN** images uploaded with this profile are downscaled to these limits without changes to other parts of the system

#### Scenario: Limits from environment variables
- **WHEN** the developer sets in `.env` the maximum width and height of an image and reloads the configuration
- **THEN** the profile `image` downscales images to these limits without code changes

#### Scenario: Profile without size limits
- **WHEN** a profile does not set `max_width` and `max_height`
- **THEN** images with this profile are saved without downscaling

#### Scenario: Large image within the profile
- **WHEN** a 25 MB JPEG image is uploaded with the `image` profile
- **THEN** the upload is accepted and the image is downscaled to the profile's pixel limits

#### Scenario: Legacy Word document
- **WHEN** a `.doc` file (`application/msword`) is uploaded with the `document` profile
- **THEN** the upload is accepted and the file is stored with the extension `doc`

### Requirement: Checking a file against the profile
The system SHALL reject a file before it is saved to disk if its type is not among the types allowed by the profile or its size exceeds the maximum of the profile. The type SHALL be determined by the content of the file, not by the extension or the header sent by the client.

#### Scenario: Allowed file
- **WHEN** a genuine JPEG image within the limit is uploaded with the `image` profile
- **THEN** the file is accepted

#### Scenario: Disallowed type
- **WHEN** a PDF file is uploaded with the `image` profile
- **THEN** the upload is rejected with a validation error, and nothing is created on the disk

#### Scenario: Forged extension
- **WHEN** a text or executable file is named `photo.jpg` and uploaded with the `image` profile
- **THEN** the upload is rejected, because the content is not an allowed image

#### Scenario: Size exceeded
- **WHEN** an image larger than 30 MB is uploaded with the `image` profile
- **THEN** the upload is rejected with a validation error, and nothing is created on the disk

### Requirement: Security of stored files
The system SHALL reject executable files (in particular `php`, `phtml`, `sh`, `exe`) and SVG regardless of the profile; SHALL name the file on the disk `{uuid}.{extension}`, where `uuid` is the identifier of its record, and the extension is determined by the allowed type; and SHALL keep the original name only as metadata.

#### Scenario: Executable file
- **WHEN** a file `shell.php` is uploaded with any profile, including a profile that allows arbitrary types
- **THEN** the upload is rejected

#### Scenario: SVG
- **WHEN** an SVG image is uploaded with the `image` profile
- **THEN** the upload is rejected

#### Scenario: Name on the disk
- **WHEN** a file with the original name `../../evil name.png` is uploaded
- **THEN** on the disk the file lies at the root of the profile's disk under the name `{uuid}.png`, and the original name is kept only as metadata

### Requirement: Uploading a file in a separate request
The system SHALL accept a file in a separate admin request (multipart) only from an authenticated user with a verified email. The request SHALL specify either an upload profile or a content field (its type and key), but not both at once. For a field the system SHALL take the profile from the field's type and apply the field settings that narrow the profile rules (the allowed types, the maximum size, and for images the maximum width and height); the rules SHALL be not wider than the profile: for each bound the smaller value from the profile and the field applies. In the response the system SHALL return the metadata of the created record: `uuid`, profile, original name, type, size, width and height (for images), and the link to the file. Forms that use the file SHALL pass further only its `uuid`.

#### Scenario: Successful upload
- **WHEN** an authenticated administrator sends a file and the profile `image`
- **THEN** the response contains the `uuid` and the metadata of the record, and the link by which the file is available

#### Scenario: Upload by field
- **WHEN** an administrator sends a file and the field `image` that allows only `image/png`, and the file is a PNG within the field's size
- **THEN** the file is accepted, and the profile of the record equals the profile of the field's type

#### Scenario: Type outside the field settings
- **WHEN** an administrator sends a JPEG to a field that allows only `image/png`
- **THEN** the upload is rejected with a validation error, and nothing is created on the disk

#### Scenario: Size above the field settings
- **WHEN** an administrator sends a file larger than the field's `max_size` but within the profile
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

#### Scenario: Size limits of a field
- **WHEN** an administrator sends an image 2000×2000 to the field `image` with `max_width` 500 and `max_height` 500, for a profile with the limits 1920×1920
- **THEN** the file is accepted, the saved image has the size 500×500, and the response contains this width and height

#### Scenario: A field does not widen the profile limits
- **WHEN** the field has `max_width` 4000, and the profile sets `max_width` 1920
- **THEN** the image is downscaled to 1920 by width

#### Scenario: A field narrows a profile without limits
- **WHEN** the profile does not set `max_width`, and the field has `max_width` 600
- **THEN** an image wider than 600 is downscaled to 600 by width

### Requirement: Link to a file
The system SHALL provide a single way to obtain a link to a file by its record, which takes into account the disk of the file, so that consumers do not depend on where the file actually lies.

#### Scenario: Public disk
- **WHEN** a link to a file saved on the public disk is requested
- **THEN** the address by which the file is served by the web server is returned

#### Scenario: Independence from the consumer
- **WHEN** the disk of a profile is changed in the configuration for new files
- **THEN** the links to previously uploaded files continue to be built by the disk saved in their records

### Requirement: Validation of a file reference in consumers
The system SHALL provide consumers with a validation rule that accepts a value only when it is the `uuid` of an existing file uploaded with the specified profile.

#### Scenario: File of the required profile
- **WHEN** the value is the `uuid` of a file uploaded with the profile `image`, and the consumer requires the profile `image`
- **THEN** the check passes

#### Scenario: File of another profile
- **WHEN** the value is the `uuid` of a file of the profile `document`, and the consumer requires the profile `image`
- **THEN** the check fails with a validation error

#### Scenario: Non-existent file
- **WHEN** the value does not match any file
- **THEN** the check fails with a validation error

#### Scenario: Invalid format
- **WHEN** the value is not a string of 32 characters `0-9a-f`
- **THEN** the check fails with a validation error

### Requirement: Protection of files in use
The system SHALL NOT allow deleting the record of a file for which a usage relation exists (see `media-library`), and the consumer SHALL release a file only by changing or clearing its own value, without deleting the file itself.

#### Scenario: Attempt to delete a file in use
- **WHEN** the record of a file that has a usage relation is deleted
- **THEN** the deletion is rejected, and the record and the file on the disk remain

#### Scenario: Replacement of a file by a consumer
- **WHEN** a consumer replaces a value from one file with another
- **THEN** the old record and the file on the disk remain in the storage

### Requirement: Upload widget in the admin panel
The admin panel SHALL provide a reusable file upload component: choosing a file or dragging, the upload indication, showing validation errors, a preview of the uploaded image, clearing the choice, and passing the file's `uuid` to the form. The component SHALL upload the file in a separate request to the form.

#### Scenario: Successful upload
- **WHEN** the user chooses an allowed file
- **THEN** the component shows the progress, and after completion — the file name (for an image — its preview), and passes the `uuid` of the file to the form

#### Scenario: Rejected file
- **WHEN** the user chooses a file of a disallowed type or one that is too large
- **THEN** the component shows an error message, and the `uuid` is not passed to the form

#### Scenario: Clearing
- **WHEN** the user clears the chosen file
- **THEN** the component removes the value from the form

#### Scenario: Demo page
- **WHEN** the developer opens the demo page of the component outside production
- **THEN** it shows the component for the profiles `image` and `document`; in production the page responds with 404

### Requirement: Short type label of a file
The admin panel SHALL show the type of a file by the short extension label (`pdf`, `docx`, `xlsx`, `jpg`, `png`) instead of the full MIME type in all places where the type is output. The label SHALL match the extension that the upload profiles set for this type. For a type that is in no profile, the part of the MIME after `/` SHALL be shown. The stored value of the file type (the full MIME) and the file check rules SHALL remain unchanged.

#### Scenario: Type from a profile
- **WHEN** the admin panel outputs the file type `application/vnd.openxmlformats-officedocument.wordprocessingml.document`
- **THEN** `docx` is shown

#### Scenario: Extension of stored files
- **WHEN** the admin panel outputs the type `image/jpeg`
- **THEN** `jpg` is shown, as in the extension of the stored file

#### Scenario: Type outside the profiles
- **WHEN** a file has the type `text/plain`, which is no longer in any profile
- **THEN** `plain` is shown, and the page does not break

### Requirement: Uploading several files with the widget
The upload widget SHALL have a multiple selection mode: the user chooses or drags several files at once. Each file SHALL be uploaded in a separate request to the same upload endpoint as a single file, one after another, not by one shared request. Each file SHALL pass a quick check of type and size before the request. The widget SHALL notify the form about each successfully uploaded file; a failure of one file (a quick rejection, a server response, a network failure) SHALL be shown with the name of this file and SHALL not cancel the upload of the rest. During the upload the widget SHALL show the progress (how many files out of how many have been processed) and SHALL not accept a new choice until the queue is complete.

#### Scenario: Several allowed files
- **WHEN** the user chooses three allowed files
- **THEN** the server receives three separate upload requests, and the form receives three files in the order of selection

#### Scenario: One file rejected
- **WHEN** the user chooses three files, and one has a disallowed type
- **THEN** the two allowed files are uploaded and passed to the form, and the widget shows an error with the name of the third file

#### Scenario: Failure during the queue
- **WHEN** the request for the upload of the second of three files fails with a network error
- **THEN** the first and the third files are uploaded, and the error is named after the second file

#### Scenario: Progress
- **WHEN** the upload queue of three files is in progress
- **THEN** the widget shows how many files have been processed, and does not allow starting a new choice

### Requirement: Upload for a rich text field
An upload request by a field (a content type and a field key) SHALL also accept a field of type `richtext`: the profile SHALL be `image`, and the rules SHALL be the rules of the whole profile (a `richtext` field has no file settings). For a field that holds no files and is not `richtext`, the request SHALL be rejected with an error for the field.

#### Scenario: Upload by a richtext field
- **WHEN** an administrator sends a PNG and a field of type `richtext`
- **THEN** the file is accepted, the profile of the record is `image`, and the response contains a relative address of the form `/storage/{uuid}.png`

#### Scenario: Profile not `image`
- **WHEN** an administrator sends a PDF and a field of type `richtext`
- **THEN** the file is rejected by the rules of the `image` profile

#### Scenario: Field without files
- **WHEN** an administrator sends a file and a field of type `string`
- **THEN** the request is rejected with an error for the field

### Requirement: Downscaling an image to the profile limits
The system SHALL, on saving an image, downscale it if its width exceeds `max_width` or its height exceeds `max_height` of the effective rules. The downscaling SHALL preserve the proportions: the image is scaled so that the side that exceeds its limit the most equals its limit, and the other side does not exceed its own. A limit that does not exist (the profile does not set it and the field does not narrow it) SHALL NOT limit the side. The system SHALL NOT enlarge an image, and an image within both limits SHALL be saved without re-encoding, with the same content as uploaded. The image format (JPEG, PNG, WebP) SHALL remain the same, the transparency of PNG and WebP SHALL be preserved, and the EXIF orientation SHALL be taken into account so that the image does not change its appearance. Animated GIF and WebP SHALL be saved without changes, regardless of size. A GIF without animation SHALL be downscaled by the same rules as other images.

#### Scenario: Width exceeds the limit
- **WHEN** an image 4000×3000 is uploaded for the limits 1000×1000
- **THEN** the saved image has the size 1000×750

#### Scenario: Height exceeds the limit
- **WHEN** an image 3000×4000 is uploaded for the limits 1000×1000
- **THEN** the saved image has the size 750×1000

#### Scenario: Different limits for the sides
- **WHEN** an image 1600×1200 is uploaded for the limits `max_width` 800 and `max_height` 400
- **THEN** the saved image has the size 533×400, because the height exceeds its limit the most

#### Scenario: Only one limit
- **WHEN** only `max_width` 500 is set and an image 2000×3000 is uploaded
- **THEN** the saved image has the size 500×750

#### Scenario: Image within the limits
- **WHEN** an image 640×480 is uploaded for the limits 1000×1000
- **THEN** the saved file has the same content and the same hash as uploaded

#### Scenario: No enlargement
- **WHEN** an image 100×100 is uploaded for the limits 5000×5000
- **THEN** the saved image has the size 100×100

#### Scenario: Transparency
- **WHEN** a large PNG with a transparent background is uploaded
- **THEN** the saved downscaled PNG remains transparent

#### Scenario: Orientation
- **WHEN** a JPEG 4000×3000 is uploaded, which by its EXIF orientation is displayed rotated by 90°, and the limits are 1000×1000
- **THEN** the saved image looks the same as the viewer displays it, and has the size 750×1000

#### Scenario: Animated GIF
- **WHEN** an animated GIF 400×400 is uploaded for the limits 100×100
- **THEN** it is saved without changes, with the same content and sizes

#### Scenario: Format does not change
- **WHEN** a WebP image is downscaled
- **THEN** the saved file remains `image/webp` with the extension `webp`

### Requirement: Image metadata describes the saved file
The system SHALL record in the file's record the size in bytes, the content hash, and the width and height of the saved (not the uploaded) file. The maximum size limit SHALL still be checked against the uploaded file before downscaling. The response to an upload SHALL return the width, height, and size of the saved file.

#### Scenario: Metadata after downscaling
- **WHEN** a JPEG 4000×3000 of 4 MB is uploaded for the limits 1000×1000
- **THEN** the record contains the width 1000, the height 750, and the size and hash of the saved file, not of the uploaded one

#### Scenario: Size limit by the original
- **WHEN** an 8 MB image is uploaded with a profile whose limit is 5 MB, which after downscaling would become smaller than 5 MB
- **THEN** the upload is rejected with a validation error, and nothing is created on the disk

### Requirement: Protection against overly large images
The system SHALL reject an image whose number of pixels (width multiplied by height) exceeds the limit set in the developer's configuration, with a validation error under the key `file`. The check SHALL be performed before decoding the image, and nothing SHALL be created on the disk. The limit SHALL apply both to images within `max_size` and SHALL not depend on the field settings.

#### Scenario: Too many pixels
- **WHEN** a compressed image within `max_size` is uploaded whose number of pixels exceeds the limit
- **THEN** the upload is rejected with a validation error, not a server crash, and nothing is saved

#### Scenario: Pixels within the limit
- **WHEN** an image is uploaded whose number of pixels does not exceed the limit
- **THEN** it is processed by the usual rules

### Requirement: Image processing error
The system SHALL reject an upload with a validation error under the key `file` if the image could not be downscaled (corrupt content that passed the type check). In such a case the file SHALL not be saved, and the record SHALL not be created.

#### Scenario: Corrupt image
- **WHEN** a file is uploaded that has a JPEG signature, but its content cannot be decoded for downscaling
- **THEN** the upload is rejected with a validation error, and nothing is created on the disk or in the database

### Requirement: Creating image presets on save
The system SHALL, after saving an image, create its styles marked in the configuration for creation on upload (see `image-styles`). A failure of the style creation SHALL NOT reject the saving of the file.

#### Scenario: Saving an image
- **WHEN** an image is saved with the `image` profile
- **THEN** the original and the file of the preset `thumb` lie on the disk (depending on the style format setting — WebP or the format of the original)

#### Scenario: Saving a document
- **WHEN** a document is saved
- **THEN** styles are not created

### Requirement: Deleting a file removes its styles
The system SHALL, when a storage file is deleted, also delete from the disk all its created image styles (`styles/{style}/{uuid}.*`, regardless of the extension). Missing style files SHALL not be an error. The protection rules for files in use SHALL remain unchanged: if the deletion of the file is rejected, the styles are not deleted.

#### Scenario: Deleting a file with styles
- **WHEN** a file is deleted for which the styles `thumb` and `300x250` have been created
- **THEN** the original and the files of both styles are absent from the disk

#### Scenario: File without styles
- **WHEN** a file is deleted for which no styles were created
- **THEN** the deletion completes without an error

#### Scenario: Deletion rejected
- **WHEN** the deletion of a file in use is rejected
- **THEN** the original and its styles remain on the disk

### Requirement: Image thumbnail in the upload widget
For a selected image the upload widget SHALL show the style `thumb` (the `preview` field of the file's data; if it is absent — the address of the original), wrapped in a link to the original with `target="_blank"` and `rel="noopener noreferrer"`; the thumbnail SHALL have `cursor-pointer`. The file data that the server passes to the widget (the upload response and the values of the forms of the node, the term, the menu item, and the site settings) SHALL contain `preview`: the address of `thumb` for an image and `null` for another file. A file that is not an image SHALL be shown as an icon without a link.

#### Scenario: Just uploaded image
- **WHEN** the user uploads an image into the widget
- **THEN** the widget shows a thumbnail with the style `thumb`, whose link leads to the original and opens in a new tab

#### Scenario: Saved image in the edit form
- **WHEN** the node form with a saved image is opened
- **THEN** the thumbnail has the address `thumb`, and its link has the address of the original

#### Scenario: Document
- **WHEN** a PDF is selected in the widget
- **THEN** the widget shows an icon without a link, and `preview` in the data is `null`

### Requirement: Avatar upload profile
The system SHALL provide the upload profile `avatar` that accepts only JPEG, PNG and WebP images up to a small size limit and downscales an image larger than 512 pixels on either side proportionally. A file uploaded with another profile SHALL NOT be accepted as an avatar.

#### Scenario: Valid avatar image
- **WHEN** a JPEG, PNG or WebP image within the size limit is uploaded with the profile `avatar`
- **THEN** it is stored, and an image wider or taller than 512 pixels is saved downscaled

#### Scenario: Unsupported file
- **WHEN** a GIF, a document or an oversized file is uploaded with the profile `avatar`
- **THEN** the upload is rejected with an error under the `file` key
