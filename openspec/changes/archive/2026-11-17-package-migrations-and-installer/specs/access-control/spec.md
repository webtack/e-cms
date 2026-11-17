# Spec Delta

## MODIFIED Requirements

### Requirement: One role per user
Each user created through the admin panel SHALL have exactly one role. The user's rights equal the rights of their role; combining several roles is not supported. A user account that has no role (for example an account of the host application that existed before the CMS was installed) SHALL have no rights in the CMS and SHALL be denied access to the admin panel; accounts SHALL never receive a role automatically.

#### Scenario: User without a role
- **WHEN** an attempt is made to create a user without a role
- **THEN** creation is rejected with a validation error on the role field

#### Scenario: Pre-existing account without a role
- **WHEN** an account with no role requests an admin page
- **THEN** access is denied and no CMS right is granted

#### Scenario: Roleless user is evaluated safely
- **WHEN** the permissions of an account with no role are evaluated (system permissions, node scopes, media, permissions shared with the client)
- **THEN** every system permission is denied, every scope is `none`, and no error is raised

#### Scenario: Roleless user as the target of an action
- **WHEN** an administrator opens, edits or deletes an account that has no role
- **THEN** the policy treats it as a non-system account, the action works without an error, and the role field can be set

#### Scenario: Roleless user in the users list
- **WHEN** an administrator opens the users list and an account without a role exists
- **THEN** the list renders and shows that account with a "No role" label instead of failing
