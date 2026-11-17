## ADDED Requirements

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
