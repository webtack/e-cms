# admin-roles Specification

## Purpose

Lets administrators manage roles and their rights: create and change roles, and configure the access matrix for sections, content types, and the media library.

## Requirements

### Requirement: List of roles
The system SHALL provide the page `/admin/roles` with a list of roles (name, number of users, a system mark). The page is available only with the right to manage roles.

#### Scenario: Access by right
- **WHEN** a user with the right to manage roles opens `/admin/roles`
- **THEN** they see the roles, and the system role `admin` is marked

#### Scenario: Without the right
- **WHEN** a user without this right opens `/admin/roles`
- **THEN** the response is 403

### Requirement: Creating and editing a role
The system SHALL allow creating a role with a unique name and configuring its rights, and changing them later. A change of rights applies to all users of the role from their next request.

#### Scenario: New role
- **WHEN** an administrator creates a role with a unique name
- **THEN** the role is saved without any rights

#### Scenario: Duplicate name
- **WHEN** the name of an existing role is specified
- **THEN** saving is rejected with an error next to the name field

### Requirement: Role rights matrix
The role screen SHALL show the system rights as toggles, and for each content type and for the media library a choice of level `none`, `own`, `any`. Types are grouped by kind (`content`, `page`, `block`), and the list corresponds to the types that exist at the moment the screen is opened.

#### Scenario: Type created after the role
- **WHEN** a new type is added after the role was created, and the role screen is opened
- **THEN** the type is in the matrix with level `none`

#### Scenario: Saving the matrix
- **WHEN** an administrator changes the levels and saves
- **THEN** the saved levels are displayed the next time the screen is opened

### Requirement: Protection of the system role
The system role `admin` SHALL be impossible to change or delete. Its matrix is shown for viewing only.

#### Scenario: Attempt to change admin
- **WHEN** a request to change or delete the role `admin` arrives
- **THEN** the response is 403 or a validation error, and the role is not changed

### Requirement: Deleting a role
The system SHALL allow deleting a role only when no user has it.

#### Scenario: Role with users
- **WHEN** a role that at least one user has is deleted
- **THEN** the deletion is rejected with an explanation, and the role is kept

#### Scenario: Empty role
- **WHEN** a role without users is deleted
- **THEN** the role and its rights are deleted
