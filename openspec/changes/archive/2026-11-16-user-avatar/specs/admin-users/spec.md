# Spec Delta

## ADDED Requirements

### Requirement: Avatar in the users list and on deletion
The name cell of the users list SHALL show the user's avatar when they have one and their initials otherwise. Deleting a user SHALL release the user's avatar in the media library; the file itself SHALL stay in the storage.

#### Scenario: Avatar in the list
- **WHEN** an administrator opens `/admin/users` and a user has an avatar
- **THEN** that user's row shows the picture next to the name, and rows of users without an avatar show initials

#### Scenario: Deleting a user with an avatar
- **WHEN** an administrator deletes a user who has an avatar
- **THEN** the user is deleted and the media library no longer lists the avatar file as used by that user
