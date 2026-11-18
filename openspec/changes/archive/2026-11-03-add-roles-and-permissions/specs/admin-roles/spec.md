# Spec Delta

## Purpose

Allows managing roles and their rights: creating and changing roles, configuring the matrix of access to the sections, the content types and the media library.

## ADDED Requirements

### Requirement: Список ролей
The system SHALL provide the page `/admin/roles` with a list of roles (the name, the number of users, the sign of a system role). The page is available only with the right of managing roles.

#### Scenario: Access by the right
- **WHEN** a user with the right of managing roles opens `/admin/roles`
- **THEN** he sees the roles, and the system role `admin` is marked

#### Scenario: Without the right
- **WHEN** a user without this right opens `/admin/roles`
- **THEN** the response is 403

### Requirement: Створення та редагування ролі
The system SHALL allow creating a role with a unique name and configuring its rights, and changing them later. A change of rights takes effect for all users of the role from the next request.

#### Scenario: New role
- **WHEN** the administrator creates a role with a unique name
- **THEN** the role is saved without any rights

#### Scenario: Duplicate name
- **WHEN** the name of an existing role is specified
- **THEN** the saving is rejected with an error next to the field of the name

### Requirement: Матриця прав ролі
The screen of a role SHALL show the system rights as switches, and for each content type and for the media library a choice of the level `none`, `own`, `any`. The types are grouped by kind (`content`, `page`, `block`), and the list corresponds to the types that exist at the moment the screen is opened.

#### Scenario: Type created after the role
- **WHEN** a new type is added after the creation of a role and the screen of the role is opened
- **THEN** the type is in the matrix with the level `none`

#### Scenario: Saving the matrix
- **WHEN** the administrator changes the levels and saves
- **THEN** the saved levels are displayed when the screen is opened next time

### Requirement: Захист системної ролі
The system role `admin` SHALL be impossible to change or delete. Its matrix is shown only for viewing.

#### Scenario: Attempt to change admin
- **WHEN** a request to change or delete the role `admin` arrives
- **THEN** the response is 403 or a validation error, the role is not changed

### Requirement: Видалення ролі
The system SHALL allow deleting a role only when no user has it.

#### Scenario: Role with users
- **WHEN** a role that at least one user has is being deleted
- **THEN** the deletion is rejected with an explanation, the role is kept

#### Scenario: Empty role
- **WHEN** a role without users is being deleted
- **THEN** the role and its rights are deleted
