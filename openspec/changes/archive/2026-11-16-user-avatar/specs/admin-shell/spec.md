# Spec Delta

## ADDED Requirements

### Requirement: Avatar in the user menu
The user menu in the header SHALL show the signed-in user's avatar instead of their initials when they have one.

#### Scenario: User with an avatar
- **WHEN** a user who has an avatar opens any admin page
- **THEN** the header user menu shows their picture

#### Scenario: User without an avatar
- **WHEN** a user without an avatar opens any admin page
- **THEN** the header user menu shows their initials
