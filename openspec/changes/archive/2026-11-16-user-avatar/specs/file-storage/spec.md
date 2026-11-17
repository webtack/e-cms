# Spec Delta

## ADDED Requirements

### Requirement: Avatar upload profile
The system SHALL provide the upload profile `avatar` that accepts only JPEG, PNG and WebP images up to a small size limit and downscales an image larger than 512 pixels on either side proportionally. A file uploaded with another profile SHALL NOT be accepted as an avatar.

#### Scenario: Valid avatar image
- **WHEN** a JPEG, PNG or WebP image within the size limit is uploaded with the profile `avatar`
- **THEN** it is stored, and an image wider or taller than 512 pixels is saved downscaled

#### Scenario: Unsupported file
- **WHEN** a GIF, a document or an oversized file is uploaded with the profile `avatar`
- **THEN** the upload is rejected with an error under the `file` key
