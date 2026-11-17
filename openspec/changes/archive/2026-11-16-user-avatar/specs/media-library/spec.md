# Spec Delta

## ADDED Requirements

### Requirement: Uploader avatar in the media table
The "uploaded by" cell of the media library table SHALL show the uploader's avatar next to their name when the uploader has one, and their initials otherwise. A file whose uploader is unknown SHALL show no avatar.

#### Scenario: Uploader with an avatar
- **WHEN** a file was uploaded by a user who has an avatar
- **THEN** the media table row shows that picture next to the uploader's name

#### Scenario: Uploader without an avatar or unknown uploader
- **WHEN** the uploader has no avatar, or the uploader is unknown
- **THEN** the row shows the initials next to the name, or nothing for an unknown uploader
