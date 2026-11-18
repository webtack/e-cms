# Spec Delta

## Purpose

Gives the admin the pages for managing content types of the kind `page` and their fields: the list, creation, editing, deletion of a type, and management of its fields. The behaviour matches types of the kind `content`, except for the absence of a slug and its own section address.

## ADDED Requirements

### Requirement: Page types table page
The admin SHALL have a page `/admin/page-types`, available only to authenticated users with a confirmed email, which shows all content types of the kind `page` in the data table in server mode: search, sorting, pagination and page size are performed by the server, and the state lives in the address. Types of the kinds `content` and `block` SHALL NOT be shown on this page. The table SHALL show for each type the name with the description, the key, the parent type key, the number of fields and the number of nodes; the table SHALL NOT have a `slug` column, a kind column (`kind`) or filters. Without an explicit sorting the rows SHALL follow the name.

#### Scenario: Opening the page
- **WHEN** an authenticated user opens `/admin/page-types`
- **THEN** they see the table of page types, and the title matches the interface language

#### Scenario: Only the page kind
- **WHEN** the system has types of the kinds `content`, `page` and `block`
- **THEN** the table shows only the types of the kind `page`

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/page-types`
- **THEN** they are redirected to the login page

#### Scenario: Without slug
- **WHEN** the user looks at the table columns
- **THEN** there is no slug column

### Requirement: Row action menu of a page type
Each row of the page types table SHALL have a group of action buttons: a "Settings" button (the edit form) and a menu with the items "Edit", "Fields" and "Delete", where "Delete" is separated as a destructive action. The "Create" button above the table SHALL lead to the creation form of a page type.

#### Scenario: Going to the fields
- **WHEN** the user chooses the "Fields" item in the row menu
- **THEN** `/admin/page-types/{key}/fields` opens

#### Scenario: Create button
- **WHEN** the user clicks "Create" above the table
- **THEN** `/admin/page-types/create` opens

### Requirement: Creating a page type
The admin SHALL have a form `/admin/page-types/create` with the fields: name, key, parent type and description; there is no slug field in the form. Saving SHALL create a type of the kind `page` regardless of the values in the request (the kind is not passed from the form), together with the system field `title`. Only a type of the kind `page` can be chosen as the parent type. Validation errors SHALL be shown next to the fields with the entered values kept. After success the user SHALL land on the list of page types with a success message.

#### Scenario: Successful creation
- **WHEN** the user fills in the name and a unique key and saves the form
- **THEN** a type of the kind `page` with the system field `title` is created, and the user is redirected to `/admin/page-types` with a message

#### Scenario: Kind in the request is ignored
- **WHEN** the create request contains `kind=content` or a `slug`
- **THEN** the type is created with the kind `page` and without a slug

#### Scenario: Parent of another kind
- **WHEN** the create request specifies as the parent type the key of a type of the kind `content`
- **THEN** the save is rejected with an error next to the parent type field

#### Scenario: Duplicate key
- **WHEN** the key is already taken by any type
- **THEN** the save is rejected with an error next to the key field

### Requirement: Editing a page type
The admin SHALL have a form `/admin/page-types/{key}/edit` with the fields name, parent type and description, and the key read-only. Saving SHALL change only the name, the parent type and the description; `key` and `kind` do not change. The type itself or its descendant cannot be chosen as the parent. For a key that is not among the types of the kind `page` (including the key of a type `content` or `block`), all routes of the section SHALL respond 404, and `/admin/content-types/{key}/**` for a type `page` — also 404.

#### Scenario: Successful save
- **WHEN** the user changes the name of a page type and saves
- **THEN** the name is updated, and the user is redirected to `/admin/page-types` with a message

#### Scenario: Type of another kind
- **WHEN** the user opens `/admin/page-types/{key}/edit` for the key of a type of the kind `content`
- **THEN** the response is 404

#### Scenario: Content section does not open a page type
- **WHEN** the user opens `/admin/content-types/{key}/edit` for the key of a type of the kind `page`
- **THEN** the response is 404

#### Scenario: Descendant as the parent
- **WHEN** the edit request specifies a descendant of the type as its parent
- **THEN** the save is rejected with an error next to the parent type field

### Requirement: Deleting a page type
The row menu SHALL open a deletion confirmation dialog with the name of the type. A type that has nodes or child types SHALL NOT be deleted: the dialog explains the reason without a confirmation button, and the server refuses with an error message. Otherwise the type is deleted together with its fields, and the user lands on the list with a success message.

#### Scenario: Deleting an empty type
- **WHEN** the user confirms the deletion of a type without nodes and child types
- **THEN** the type and its fields are deleted, and the user sees a success message on `/admin/page-types`

#### Scenario: Type with nodes
- **WHEN** the type has nodes
- **THEN** the dialog has no confirmation button, and the deletion request is rejected with an error message

#### Scenario: Type with child types
- **WHEN** the type has child types
- **THEN** the deletion is rejected with an error message

### Requirement: Section menu of a page type
The pages of editing a page type, the list of fields, creation and editing of a field SHALL have a horizontal section menu with the items "Settings" (`/admin/page-types/{key}/edit`) and "Fields" (`/admin/page-types/{key}/fields`) and a link back to the list of page types. The active item SHALL be highlighted; on the creation and editing pages of a field the active item is "Fields". On the page of creating a type there is no menu.

#### Scenario: Switching sections
- **WHEN** the user on the edit page of a page type chooses "Fields"
- **THEN** the list of fields of the type opens, and the item "Fields" is active

#### Scenario: Link back
- **WHEN** the user clicks the link back on the fields page of a page type
- **THEN** `/admin/page-types` opens

### Requirement: Fields of a page type
The admin SHALL provide for types of the kind `page` the same pages and field actions as for content types, at the addresses `/admin/page-types/{key}/fields`, `/fields/create`, `/fields/{field}/edit`: the list (a client table, ordered by weight), creation, editing, deletion and ordering by dragging, with the same validation rules and bans (the system field `title` and a field with values are not deleted, the field key is immutable). After the actions the user SHALL return to the list of fields of the page type.

#### Scenario: List of fields
- **WHEN** the user opens `/admin/page-types/{key}/fields`
- **THEN** they see all fields of the page type, including the system `title`, in the order of weight

#### Scenario: Creating a field
- **WHEN** the user creates a field with a unique key in a page type
- **THEN** the field is added, and the user is redirected to `/admin/page-types/{key}/fields` with a message

#### Scenario: Deleting the system field
- **WHEN** a deletion request for the field `title` arrives
- **THEN** the field remains, and the user sees an error message

#### Scenario: Changing the order
- **WHEN** the user drags a field to a new place
- **THEN** the new sequence is saved in `weight` and persists after a reload

#### Scenario: Field of another type
- **WHEN** the address specifies a field key that does not exist in this page type
- **THEN** the response is 404
