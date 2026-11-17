# Spec Delta

## MODIFIED Requirements

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
