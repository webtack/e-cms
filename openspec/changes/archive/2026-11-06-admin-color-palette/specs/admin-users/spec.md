# Spec Delta

## ADDED Requirements

### Requirement: Avatar with initials in the users list
The users list SHALL show before the name of each user a round avatar with the initials of that user (the first letters of up to two words of the name, in upper case). The avatar of an active user SHALL be a soft accent tint and the avatar of an inactive user a neutral grey. The avatar SHALL be decorative: it SHALL not replace the name text and SHALL not be announced separately by assistive technology.

#### Scenario: Initials of an active user
- **WHEN** an administrator opens `/admin/users` and a user is named "Oksana Melnyk"
- **THEN** the row shows an accent-tinted avatar with "OM" before the name

#### Scenario: Inactive user
- **WHEN** the list contains a deactivated user
- **THEN** that user's avatar is neutral grey and the name is shown as before
