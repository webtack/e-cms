# Spec Delta

## ADDED Requirements

### Requirement: Avatar
The profile page SHALL let the user upload, replace and remove their own avatar. Only an image of the avatar upload profile is accepted; a file of another profile or a non-image SHALL be rejected with an error next to the avatar field and the avatar SHALL stay unchanged. A saved avatar SHALL be shown on the profile page in place of the initials; a user without an avatar SHALL see their initials. Removing the avatar SHALL restore the initials. The avatar SHALL be recorded in the media library as used by this user.

#### Scenario: Avatar uploaded
- **WHEN** the user uploads a valid image as their avatar and saves
- **THEN** the profile page shows the picture instead of the initials and the media library lists the file as used by the user

#### Scenario: Avatar replaced
- **WHEN** the user uploads another image over an existing avatar and saves
- **THEN** the new picture is shown and the media library no longer lists the previous file as used by the user

#### Scenario: Avatar removed
- **WHEN** the user removes their avatar and saves
- **THEN** the initials are shown again and the previous file is no longer listed as used by the user

#### Scenario: Wrong kind of file
- **WHEN** the request carries a file that was not uploaded with the avatar profile
- **THEN** the avatar stays unchanged and an error is shown next to the avatar field
