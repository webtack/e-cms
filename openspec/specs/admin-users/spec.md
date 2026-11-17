# admin-users Specification

## Purpose

Lets administrators manage admin panel users: create them, assign a role, deactivate and delete them under rules that preserve the authorship of records.

## Requirements

### Requirement: List of users
The system SHALL provide the page `/admin/users` with a list of users (name, email, role, activity) in the shared data table. The page is available only with the right to manage users.

#### Scenario: Access by right
- **WHEN** a user with the right to manage users opens `/admin/users`
- **THEN** they see the list of users

#### Scenario: Without the right
- **WHEN** a user without this right opens `/admin/users`
- **THEN** the response is 403

### Requirement: Creating a user
The system SHALL allow creating a user with a name, a unique email, a password, and a mandatory role. A user created by an administrator is considered to have a verified email and to be active.

#### Scenario: Successful creation
- **WHEN** an administrator submits a correct name, email, password, and role
- **THEN** the user is created, can log in, and has the specified role

#### Scenario: Duplicate email
- **WHEN** the email of an existing user is specified
- **THEN** creation is rejected with an error next to the email field

#### Scenario: Without a role
- **WHEN** the role is not specified or a non-existent one is specified
- **THEN** creation is rejected with an error next to the role field

### Requirement: Editing a user
The system SHALL allow changing a user's name, email, role, and activity, and setting a new password (an empty password field keeps the current one). A user cannot change their own role or deactivate themselves.

#### Scenario: Changing a role
- **WHEN** an administrator changes the role of another user
- **THEN** the new rights take effect from this user's next request

#### Scenario: Self-deactivation
- **WHEN** a user attempts to deactivate their own account
- **THEN** the action is rejected with an explanation

### Requirement: Deactivating a user
A deactivated user SHALL be unable to log in, and their records SHALL keep the authorship. Reactivation restores access.

#### Scenario: Deactivation keeps records
- **WHEN** an administrator deactivates the author of records
- **THEN** the records remain with the same author

### Requirement: Deleting a user
The system SHALL allow deleting only a user who is not the author of any content record. A user who is an author can only be deactivated.

#### Scenario: Deleting without records
- **WHEN** an administrator deletes a user without records
- **THEN** the user is deleted

#### Scenario: Deleting an author
- **WHEN** an administrator attempts to delete the author of records
- **THEN** the deletion is rejected with an explanation that the user can only be deactivated

### Requirement: Protection of the last administrator
The system SHALL guarantee that at least one active user of the role `admin` always remains: the last such user cannot be deactivated, deleted, or moved to another role.

#### Scenario: Last administrator
- **WHEN** an attempt is made to deactivate, delete, or change the role of the only active administrator
- **THEN** the action is rejected with an explanation

### Requirement: Avatar with initials in the users list
The users list SHALL show before the name of each user a round avatar with the initials of that user (the first letters of up to two words of the name, in upper case). The avatar of an active user SHALL be a soft accent tint and the avatar of an inactive user a neutral grey. The avatar SHALL be decorative: it SHALL not replace the name text and SHALL not be announced separately by assistive technology.

#### Scenario: Initials of an active user
- **WHEN** an administrator opens `/admin/users` and a user is named "Oksana Melnyk"
- **THEN** the row shows an accent-tinted avatar with "OM" before the name

#### Scenario: Inactive user
- **WHEN** the list contains a deactivated user
- **THEN** that user's avatar is neutral grey and the name is shown as before

### Requirement: Avatar in the users list and on deletion
The name cell of the users list SHALL show the user's avatar when they have one and their initials otherwise. Deleting a user SHALL release the user's avatar in the media library; the file itself SHALL stay in the storage.

#### Scenario: Avatar in the list
- **WHEN** an administrator opens `/admin/users` and a user has an avatar
- **THEN** that user's row shows the picture next to the name, and rows of users without an avatar show initials

#### Scenario: Deleting a user with an avatar
- **WHEN** an administrator deletes a user who has an avatar
- **THEN** the user is deleted and the media library no longer lists the avatar file as used by that user

### Requirement: Administrator accounts are managed only by administrators
The system SHALL allow only a user of the system role `admin` to assign the role `admin` and to manage users who currently have it. A user who holds the right to manage users but is not an administrator SHALL be refused with 403 when they create a user with the role `admin`, change any user's role to `admin`, open the edit form of, edit, deactivate, or delete a user who has the role `admin`. The restriction SHALL concern only the role `admin`: users of other roles SHALL be managed by every user with the right to manage users, and no hierarchy between other roles SHALL apply. Administrators SHALL keep full management of all users, subject to the protection of the last administrator and the self-protection rules.

#### Scenario: Creating an administrator without being one
- **WHEN** a non-administrator with the right to manage users submits the create form with the role `admin`
- **THEN** the response is 403 and no user is created

#### Scenario: Promoting a user to administrator without being one
- **WHEN** a non-administrator with the right to manage users saves a user of another role with the role `admin`
- **THEN** the response is 403 and the user's role does not change

#### Scenario: Editing an administrator without being one
- **WHEN** a non-administrator with the right to manage users opens the edit form of an administrator or submits it (including a new password or a role change)
- **THEN** the response is 403 and the administrator's name, email, role, password, and activity do not change

#### Scenario: Deactivating or deleting an administrator without being one
- **WHEN** a non-administrator with the right to manage users deactivates or deletes an administrator
- **THEN** the response is 403 and the administrator stays active and present

#### Scenario: Managing users of other roles
- **WHEN** a non-administrator with the right to manage users creates, edits, deactivates, or deletes a user whose role is not `admin`, without assigning the role `admin`
- **THEN** the action succeeds as for any other user with the right to manage users

#### Scenario: Another role with the right to manage users
- **WHEN** a user of a role other than `admin` that holds the right to manage users edits a user of another role that also holds this right
- **THEN** the action succeeds, because no hierarchy between roles other than `admin` applies

#### Scenario: Administrator actor
- **WHEN** an administrator creates a user with the role `admin`, promotes a user to `admin`, or edits, deactivates, or deletes another administrator
- **THEN** the action succeeds, except where the protection of the last administrator or the self-protection rules refuse it

### Requirement: Administrators in the users list and the role select for other actors
For an actor who is not an administrator, the users list SHALL still show the users of the role `admin`, but their rows SHALL have no Edit and Delete actions, and the role select of the create and edit forms SHALL NOT offer the role `admin`. For an administrator the list rows and the role select SHALL be unchanged. The server SHALL remain the arbiter: hiding the actions and the option does not replace the refusal described above.

#### Scenario: List for a non-administrator
- **WHEN** a non-administrator with the right to manage users opens `/admin/users` and the list contains an administrator
- **THEN** the administrator is listed with name, email, role, and activity, and the row has no Edit and no Delete action, while rows of other users keep their actions

#### Scenario: Role select for a non-administrator
- **WHEN** a non-administrator with the right to manage users opens the create form or the edit form of a user of another role
- **THEN** the role select lists every role except `admin`

#### Scenario: List and select for an administrator
- **WHEN** an administrator opens the users list and the forms
- **THEN** every row has its actions and the role select offers every role, including `admin`
