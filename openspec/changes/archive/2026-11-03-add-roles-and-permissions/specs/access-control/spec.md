# Spec Delta

## Purpose

Defines the model of access «by role»: the role of a user sets which sections of the admin he sees and which records he can create, change and delete. The spec describes the set of rights, the levels of access, the checking on the server and the behaviour on refusal.

## ADDED Requirements

### Requirement: Одна роль на користувача
Each user SHALL have exactly one role. The rights of a user equal the rights of his role; the combination of several roles is not supported.

#### Scenario: User without a role
- **WHEN** an attempt is made to create a user without a role
- **THEN** the creation is rejected with a validation error of the field of the role

### Requirement: Системні права
A role SHALL have a set of boolean system rights: management of users, roles, menus, site settings, taxonomy and types (creation and settings of types and their fields). The right to a section gives full access to this section (viewing, creation, change, deletion), and the absence of the right closes the section completely.

#### Scenario: Role without the right to menus
- **WHEN** a user of a role without the right to menus opens `/admin/menus`
- **THEN** the response is 403, and the content of the section is not returned

#### Scenario: Role with the right to taxonomy
- **WHEN** a user of a role with the right to taxonomy opens `/admin/taxonomy` and creates a vocabulary
- **THEN** the actions are performed in the same way as for the administrator

### Requirement: Рівні доступу до записів типу
For each content type (all kinds `content`, `page`, `block`) a role SHALL have one of the levels: `none`, `own` or `any`. `none` — there is no access to the records of the type. `own` — creation of records, viewing of the list of all records of the type, change and deletion only of the records whose author is the user himself. `any` — creation, change and deletion of any records of the type.

#### Scenario: Level own and own record
- **WHEN** a user with the level `own` on a type changes a record whose author he is
- **THEN** the change is saved

#### Scenario: Level own and foreign record
- **WHEN** a user with the level `own` tries to change or delete a record of another author
- **THEN** the response is 403, the record is not changed

#### Scenario: Level own and the list
- **WHEN** a user with the level `own` opens the list of records of the type
- **THEN** he sees all records of the type, including the foreign ones, but does not see the actions of change and deletion for the foreign ones

#### Scenario: Level none
- **WHEN** a user with the level `none` on a type opens the list, the form of creation or the form of editing of records of this type
- **THEN** the response is 403

#### Scenario: Level any
- **WHEN** a user with the level `any` changes or deletes a record of another author
- **THEN** the action is performed

### Requirement: Публікація керується правом на запис
The publication fields of a record (`is_active`, `published_at`, `unpublished_at`) SHALL be changed by the same right as the rest of the record; there is no separate right of publication.

#### Scenario: Author publishes his own record
- **WHEN** a user with the level `own` changes the publication date of his own record
- **THEN** the change is saved

### Requirement: Рівні доступу до медіатеки
The media library SHALL have the same levels `none`, `own`, `any`, where the owner of a file is the user who uploaded it. A file without an owner is available only at the level `any`.

#### Scenario: Deleting a foreign file at own
- **WHEN** a user with the level `own` on the media deletes a file of another user
- **THEN** the response is 403, the file is kept

#### Scenario: Media with the level none
- **WHEN** a user with the level `none` on the media opens `/admin/media`
- **THEN** the response is 403

### Requirement: Нові типи за замовчуванням закриті
A newly created content type SHALL have the level `none` for all roles except `admin`. The deletion of a type removes its levels from the roles.

#### Scenario: New type
- **WHEN** the administrator creates a content type
- **THEN** a user of any role except `admin` has no access to the records of this type until the role is updated

#### Scenario: Deleting a type
- **WHEN** a type is deleted
- **THEN** the saved levels of the roles for this type do not remain and are not restored for a type with the same key

### Requirement: Системна роль admin
The system SHALL have the system role `admin` with all system rights and the level `any` on all types and on the media library, including the types created later. The role `admin` cannot be changed or deleted.

#### Scenario: Administrator and a new type
- **WHEN** a new type is created and a user of the role `admin` opens its records
- **THEN** the access is available without an additional setting

#### Scenario: Existing users after the introduction of roles
- **WHEN** the migration of the introduction of roles is applied
- **THEN** each existing user has the role `admin`

### Requirement: Перевірка на сервері
All routes of the admin, except the dashboard, the user profile and the auxiliary search endpoints `lookup/*`, SHALL check the rights on the server regardless of which items the interface shows. An unavailable action responds with 403.

#### Scenario: Direct access by address
- **WHEN** a user without a right sends a request to an unavailable route, bypassing the interface
- **THEN** the response is 403, the action is not performed

#### Scenario: Dashboard and profile
- **WHEN** an active user of any role opens the dashboard or the profile
- **THEN** the pages are available

### Requirement: Допоміжні ендпойнти
The search endpoints `lookup/content-types` and `lookup/nodes` SHALL be available to every active user who has logged in. The upload of files and the request of the data of YouTube videos SHALL be available only to a user who has the level `own` or `any` on at least one content type or on the media.

#### Scenario: Search of references
- **WHEN** a user with the level `none` on a type calls `lookup/nodes` for this type
- **THEN** he receives the results of the search

#### Scenario: Upload of a file without the rights to write
- **WHEN** a user without any level `own` or `any` on the types and the media uploads a file
- **THEN** the response is 403

### Requirement: Права на клієнті
The interface SHALL receive from the server the list of rights of the current user and use it only to show or hide the elements; the final decision is always made by the server.

#### Scenario: Button of an unavailable action
- **WHEN** a user with the level `own` looks at a foreign record in the list
- **THEN** the buttons of change and deletion of this record are not shown
