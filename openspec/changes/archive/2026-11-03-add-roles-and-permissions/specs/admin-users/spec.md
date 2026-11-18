# Spec Delta

## Purpose

Allows managing the users of the admin: creating them, assigning a role, deactivating and deleting them by the rules that preserve the authorship of records.

## ADDED Requirements

### Requirement: Список користувачів
The system SHALL provide the page `/admin/users` with a list of users (the name, the email, the role, the activity) in the shared data table. The page is available only with the right of managing users.

#### Scenario: Access by the right
- **WHEN** a user with the right of managing users opens `/admin/users`
- **THEN** he sees the list of users

#### Scenario: Without the right
- **WHEN** a user without this right opens `/admin/users`
- **THEN** the response is 403

### Requirement: Створення користувача
The system SHALL allow creating a user with a name, a unique email, a password and a mandatory role. A user created by the administrator is considered to have a confirmed email and to be active.

#### Scenario: Successful creation
- **WHEN** the administrator sends a correct name, email, password and role
- **THEN** the user is created, he can log in and has the specified role

#### Scenario: Duplicate email
- **WHEN** the email of an existing user is specified
- **THEN** the creation is rejected with an error next to the field of the email

#### Scenario: Without a role
- **WHEN** the role is not specified or a non-existent one is specified
- **THEN** the creation is rejected with an error next to the field of the role

### Requirement: Редагування користувача
The system SHALL allow changing the name, the email, the role and the activity of a user, and also setting a new password (an empty password field keeps the existing one). A user cannot change his own role or deactivate himself.

#### Scenario: Change of role
- **WHEN** the administrator changes the role of another user
- **THEN** the new rights take effect from the next request of this user

#### Scenario: Self-deactivation
- **WHEN** a user tries to deactivate his own account
- **THEN** the action is rejected with an explanation

### Requirement: Деактивація користувача
A deactivated user SHALL not be able to log in, and his records SHALL keep the authorship. A repeated activation restores the access.

#### Scenario: Deactivation keeps the records
- **WHEN** the administrator deactivates the author of records
- **THEN** the records remain with the same author

### Requirement: Видалення користувача
The system SHALL allow deleting only a user who is not the author of any content record. A user-author can only be deactivated.

#### Scenario: Deletion without records
- **WHEN** the administrator deletes a user without records
- **THEN** the user is deleted

#### Scenario: Deletion of an author
- **WHEN** the administrator tries to delete the author of records
- **THEN** the deletion is rejected with an explanation that one can only deactivate him

### Requirement: Захист останнього адміністратора
The system SHALL guarantee that at least one active user of the role `admin` always remains: the last such user cannot be deactivated, deleted or moved to another role.

#### Scenario: Last administrator
- **WHEN** an attempt is made to deactivate, delete or change the role of the only active administrator
- **THEN** the action is rejected with an explanation
