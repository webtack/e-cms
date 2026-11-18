# Spec Delta

## ADDED Requirements

### Requirement: Зменшення зображення до меж профілю
The system SHALL, when saving an image, reduce it if its width exceeds `max_width` or its height exceeds `max_height` of the applicable rules. The reduction SHALL keep the proportions: the image is scaled so that the side that exceeds its limit the most equals its limit, and the other side does not exceed its own. A limit that does not exist (the profile does not set it and the field does not narrow it) SHALL NOT limit the side. The system SHALL NOT enlarge images, and an image within both sides SHALL be saved without re-encoding, with the same contents as the uploaded one. The image format (JPEG, PNG, WebP) SHALL stay the same, the transparency of PNG and WebP SHALL be kept, the EXIF orientation SHALL be taken into account so that the image does not change its appearance. Animated GIF and WebP SHALL be saved without changes, regardless of the size. A GIF without animation SHALL be reduced by the same rules as the other images.

#### Scenario: Width exceeds the limit
- **WHEN** an image 4000×3000 is uploaded for the limits 1000×1000
- **THEN** the saved image has the size 1000×750

#### Scenario: Height exceeds the limit
- **WHEN** an image 3000×4000 is uploaded for the limits 1000×1000
- **THEN** the saved image has the size 750×1000

#### Scenario: Different limits of the sides
- **WHEN** an image 1600×1200 is uploaded for the `max_width` 800 and `max_height` 400 limits
- **THEN** the saved image has the size 533×400, because the height exceeds its limit the most

#### Scenario: Only one limit
- **WHEN** only `max_width` 500 is set and an image 2000×3000 is uploaded
- **THEN** the saved image has the size 500×750

#### Scenario: Image within the limits
- **WHEN** an image 640×480 is uploaded for the limits 1000×1000
- **THEN** the saved file has the same contents and the same hash as the uploaded one

#### Scenario: No enlargement
- **WHEN** an image 100×100 is uploaded for the limits 5000×5000
- **THEN** the saved image has the size 100×100

#### Scenario: Transparency
- **WHEN** a large PNG with a transparent background is uploaded
- **THEN** the saved reduced PNG remains transparent

#### Scenario: Orientation
- **WHEN** a JPEG 4000×3000 is uploaded which, by its EXIF orientation, is shown rotated by 90°, and the limits are 1000×1000
- **THEN** the saved image looks the same as the viewer shows it, and has the size 750×1000

#### Scenario: Animated GIF
- **WHEN** an animated GIF 400×400 is uploaded for the limits 100×100
- **THEN** it is saved without changes, with the same contents and sizes

#### Scenario: Format does not change
- **WHEN** a WebP image is reduced
- **THEN** the saved file remains `image/webp` with the extension `webp`

### Requirement: Метадані зображення описують збережений файл
The system SHALL record in the account of the file the size in bytes, the content hash, the width and the height of the saved (and not of the uploaded) file. The limit of the maximum size SHALL still be checked against the uploaded file before the reduction. The response to the upload SHALL return the width, the height and the size of the saved file.

#### Scenario: Metadata after the reduction
- **WHEN** a JPEG 4000×3000 of 4 MB is uploaded for the limits 1000×1000
- **THEN** the record contains the width 1000, the height 750, the size and the hash of the saved file, and not of the uploaded one

#### Scenario: Size limit by the original
- **WHEN** an image of 8 MB is uploaded under a profile with a 5 MB limit, which would become smaller than 5 MB after the reduction
- **THEN** the upload is rejected with a validation error, nothing is created on the disk

### Requirement: Захист від надто великих зображень
The system SHALL reject an image whose number of pixels (width multiplied by height) exceeds the limit set in the developer's configuration, with a validation error under the key `file`. The check SHALL be performed before decoding the image, and nothing SHALL be created on the disk. The limit SHALL apply both to the images within `max_size` and SHALL NOT depend on the settings of the field.

#### Scenario: Too many pixels
- **WHEN** a compressed image within `max_size` is uploaded whose number of pixels exceeds the limit
- **THEN** the upload is rejected with a validation error, and not with a server failure, and nothing is saved

#### Scenario: Pixels within the limit
- **WHEN** an image whose number of pixels does not exceed the limit is uploaded
- **THEN** it is processed by the usual rules

### Requirement: Помилка обробки зображення
The system SHALL reject an upload with a validation error under the key `file` if the image could not be reduced (corrupted contents which passed the type check). In such a case the file SHALL NOT be saved, and the account SHALL NOT be created.

#### Scenario: Corrupted image
- **WHEN** a file is uploaded that has the signature of a JPEG, but whose contents cannot be decoded for the reduction
- **THEN** the upload is rejected with a validation error, nothing is created on the disk and in the database

## MODIFIED Requirements

### Requirement: Профілі завантаження
The system SHALL describe the upload rules by named profiles in the developer's configuration: each profile defines the disk, the allowed types and the maximum size, and the profile of images may additionally define the maximum width and height in pixels (each separately; without a value the side is not limited). A profile SHALL NOT define a directory: the file is saved in the root of the disk, and the location is set by the standard setting of this disk. The user SHALL NOT choose the disk nor set the rules during the upload. At the start the profiles `image` (images JPEG, PNG, WebP, GIF, up to 5 MB, by default reduced to 1920×1920 pixels; the limits and other parameters of the image processing, as well as the maximum sizes of the profiles, the developer sets by the environment variables `.env`) and `document` (PDF, DOCX, XLSX, up to 20 MB) SHALL exist.

#### Scenario: Disk from the profile
- **WHEN** a file is uploaded under the profile `image`
- **THEN** the file is saved in the root of the disk specified in the configuration of this profile, and the record captures this disk

#### Scenario: Unknown profile
- **WHEN** an upload is requested under a profile that is absent from the configuration
- **THEN** the upload is rejected with a validation error, the file is not saved

#### Scenario: New profile without code changes
- **WHEN** the developer adds a profile to the configuration
- **THEN** it is available for uploads without changes to the other parts of the system

#### Scenario: Different profiles on one disk
- **WHEN** the files of the profiles `image` and `document` are saved on one disk
- **THEN** both lie in the root of the disk under the names `{uuid}.{extension}` and do not conflict

#### Scenario: Size limits of an image in the profile
- **WHEN** the developer sets for the profile `image` `max_width` 1200 and `max_height` 800
- **THEN** the images uploaded under this profile are reduced to these limits without changes to the other parts of the system

#### Scenario: Limits from the environment variables
- **WHEN** the developer sets in `.env` the maximum width and height of an image and reloads the configuration
- **THEN** the profile `image` reduces the images to these limits without changes to the code

#### Scenario: Profile without size limits
- **WHEN** the profile does not set `max_width` and `max_height`
- **THEN** the images under this profile are saved without reduction

### Requirement: Завантаження файлу окремим запитом
The system SHALL accept a file in a separate request of the admin (multipart) only from an authenticated user with a confirmed mail. The request SHALL specify either the upload profile or the field of a content type (its type and key), but not both at once. By the field the system SHALL take the profile from the type of the field and apply the settings of the field that narrow the rules of the profile (the allowed types, the maximum size and, for images, the maximum width and height); the rules SHALL NOT be wider than the profile: for each limit the smaller value from the profile and the field applies. In the response the system SHALL return the metadata of the created record: `uuid`, the profile, the original name, the type, the size, the width and the height (for images) and the link to the file. The forms that use the file SHALL further pass only its `uuid`.

#### Scenario: Successful upload
- **WHEN** an authorized administrator sends a file and the profile `image`
- **THEN** the response contains the `uuid` and the metadata of the record and the link by which the file is available

#### Scenario: Upload by the field
- **WHEN** an administrator sends a file and the field `image` that allows only `image/png`, and the file is a PNG within the size of the field
- **THEN** the file is accepted, the profile of the record equals the profile of the type of the field

#### Scenario: Type outside the settings of the field
- **WHEN** an administrator sends a JPEG to a field that allows only `image/png`
- **THEN** the upload is rejected with a validation error, nothing is created on the disk

#### Scenario: Size above the settings of the field
- **WHEN** an administrator sends a file larger than the `max_size` of the field, but within the profile
- **THEN** the upload is rejected with a validation error

#### Scenario: Profile and field at once
- **WHEN** a request contains both a profile and a field
- **THEN** the response contains a validation error, the record is not created

#### Scenario: Unknown field
- **WHEN** a request specifies a field that does not exist, or a field of another type (not `image`/`file`)
- **THEN** the response contains a validation error, the record is not created

#### Scenario: Guest
- **WHEN** an upload request arrives without authentication
- **THEN** the file is not accepted, the record is not created

#### Scenario: No file
- **WHEN** a request is sent without a file or without a profile or a field
- **THEN** the response contains validation errors, the record is not created

#### Scenario: Limits of the size of the field
- **WHEN** an administrator sends an image 2000×2000 to the field `image` with `max_width` 500 and `max_height` 500 under a profile with the limits 1920×1920
- **THEN** the file is accepted, the saved image has the size 500×500, and the response contains this width and height

#### Scenario: The field does not widen the limits of the profile
- **WHEN** the field has `max_width` 4000, and the profile sets `max_width` 1920
- **THEN** the image is reduced to 1920 by width

#### Scenario: The field narrows a profile without limits
- **WHEN** the profile does not set `max_width`, and the field has `max_width` 600
- **THEN** an image wider than 600 is reduced to 600 by width
