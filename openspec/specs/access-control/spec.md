# access-control Specification

## Purpose

Defines the "by role" access model: a user's role determines which admin sections they see and which records they may create, change, and delete. The spec describes the set of rights, the access levels, the server-side check, and the behavior on denial.

## Requirements

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

### Requirement: System rights
A role SHALL have a set of boolean system rights: managing users, roles, menus, site settings, taxonomy, and types (creating and configuring types and their fields). A right to a section gives full access to that section (view, create, change, delete), and the absence of a right closes the section completely.

#### Scenario: Role without the menu right
- **WHEN** a user of a role without the menu right opens `/admin/menus`
- **THEN** the response is 403, and the section content is not returned

#### Scenario: Role with the taxonomy right
- **WHEN** a user of a role with the taxonomy right opens `/admin/taxonomy` and creates a vocabulary
- **THEN** the actions are performed the same way as for an administrator

### Requirement: Access levels for the records of a type
For each content type (all kinds `content`, `page`, `block`) a role SHALL have one of the levels: `none`, `own`, or `any`. `none` — there is no access to the records of the type. `own` — creating records, viewing the list of all records of the type, changing and deleting only the records whose author is the user themselves. `any` — creating, changing, and deleting any records of the type.

#### Scenario: Level own and an own record
- **WHEN** a user with level `own` on a type changes a record whose author is that user
- **THEN** the change is saved

#### Scenario: Level own and someone else's record
- **WHEN** a user with level `own` attempts to change or delete a record of another author
- **THEN** the response is 403, and the record is not changed

#### Scenario: Level own and the list
- **WHEN** a user with level `own` opens the list of records of a type
- **THEN** they see all records of the type, including others', but do not see the change and delete actions for others' records

#### Scenario: Level none
- **WHEN** a user with level `none` on a type opens the list, the create form, or the edit form of records of this type
- **THEN** the response is 403

#### Scenario: Level any
- **WHEN** a user with level `any` changes or deletes a record of another author
- **THEN** the action is performed

### Requirement: Publication is governed by the right to the record
The publication fields of a record (`is_active`, `published_at`, `unpublished_at`) SHALL be changed by the same right as the rest of the record; there is no separate publication right.

#### Scenario: An author publishes their own record
- **WHEN** a user with level `own` changes the publication date of their own record
- **THEN** the change is saved

### Requirement: Access levels for the media library
The media library SHALL have the same levels `none`, `own`, `any`, where the owner of a file is the user who uploaded it. A file without an owner is accessible only at level `any`.

#### Scenario: Deleting someone else's file with own
- **WHEN** a user with level `own` on media deletes a file of another user
- **THEN** the response is 403, and the file is kept

#### Scenario: Media with level none
- **WHEN** a user with level `none` on media opens `/admin/media`
- **THEN** the response is 403

### Requirement: New types are closed by default
A newly created content type SHALL have level `none` for all roles except `admin`. Deleting a type removes its levels from the roles.

#### Scenario: New type
- **WHEN** an administrator creates a content type
- **THEN** a user of any role except `admin` has no access to the records of this type until the role is updated

#### Scenario: Deleting a type
- **WHEN** a type is deleted
- **THEN** the saved levels of roles for this type do not remain and are not restored for a type with the same key

### Requirement: System role admin
The system SHALL have the system role `admin` with all system rights and level `any` for all types and the media library, including types created later. The role `admin` cannot be changed or deleted.

#### Scenario: Administrator and a new type
- **WHEN** a new type is created and a user of the role `admin` opens its records
- **THEN** access is available without additional configuration

#### Scenario: Existing users after roles are introduced
- **WHEN** the migration introducing roles is applied
- **THEN** every existing user has the role `admin`

### Requirement: Server-side check
All admin routes, except the dashboard, the user profile, and the auxiliary search endpoints `lookup/*`, SHALL check rights on the server regardless of which items the interface shows. An unavailable action responds with 403.

#### Scenario: Direct request by address
- **WHEN** a user without a right sends a request to an unavailable route, bypassing the interface
- **THEN** the response is 403, and the action is not performed

#### Scenario: Dashboard and profile
- **WHEN** an active user of any role opens the dashboard or the profile
- **THEN** the pages are available

### Requirement: Auxiliary endpoints
The search endpoints `lookup/content-types` and `lookup/nodes` SHALL be available to every active logged-in user. Uploading files and requesting YouTube video data SHALL be available only to a user who has level `own` or `any` on at least one content type or on media.

#### Scenario: Searching for references
- **WHEN** a user with level `none` on a type calls `lookup/nodes` for this type
- **THEN** they receive the search results

#### Scenario: Uploading a file without write rights
- **WHEN** a user without any level `own` or `any` on types and media uploads a file
- **THEN** the response is 403

### Requirement: Rights on the client
The interface SHALL receive the list of the current user's rights from the server and use it only to show or hide elements; the final decision is always made by the server.

#### Scenario: Button of an unavailable action
- **WHEN** a user with level `own` looks at someone else's record in the list
- **THEN** the change and delete buttons for this record are not shown


### Requirement: Resource-heavy helper endpoints are rate limited
The system SHALL limit, per signed-in user, requests to the file upload endpoint, the two lookup endpoints for references, and the YouTube lookup endpoint. A request over the limit SHALL be answered with status 429 and SHALL NOT perform the work. The YouTube lookup SHALL have a lower limit than the others, because each call can spend the quota of an external service. The limit of one user SHALL NOT affect another user, and a request within the limit SHALL behave as before, including its access checks.

#### Scenario: Within the limit
- **WHEN** an editor uploads files and uses the reference autocomplete at a normal pace
- **THEN** every request is answered as before

#### Scenario: Over the limit
- **WHEN** one user sends more requests to one of these endpoints in a minute than its limit allows
- **THEN** the extra requests are answered with status 429 and no file is stored and no external request is made

#### Scenario: Another user
- **WHEN** one user is over the limit of an endpoint and another user calls the same endpoint
- **THEN** the second user's request is answered as usual

#### Scenario: Access is still checked
- **WHEN** a user without the right to write anything calls the upload endpoint within the limit
- **THEN** the response is 403 as before
