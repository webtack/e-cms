# admin-block-types Specification

## Purpose

Provides the admin panel with pages for managing content types of kind `block` and their fields: listing, creating, editing, deleting a type, and managing its fields. The behavior matches types of kind `content`, except for the absence of a slug and of an own section address.

## Requirements

### Requirement: Block types table page
The admin panel SHALL have the page `/admin/block-types`, available only to authenticated users with a verified email, which shows all content types of kind `block` in a data table in server mode: search, sorting, pagination, and page size are performed by the server, and the state lives in the address. Types of kinds `content` and `page` SHALL NOT be shown on this page. For each type the table SHALL show the name with the description, the key, the parent type key, the number of fields, and the number of records; the table SHALL NOT have a `slug` column, a kind (`kind`) column, or filters. Without an explicit sort, the rows SHALL go by name.

#### Scenario: Opening the page
- **WHEN** an authenticated user opens `/admin/block-types`
- **THEN** they see the table of block types, and the heading matches the interface language

#### Scenario: Only kind block
- **WHEN** the system has types of kinds `content`, `page`, and `block`
- **THEN** the table shows only types of kind `block`

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/block-types`
- **THEN** they are redirected to the login page

#### Scenario: No slug
- **WHEN** the user looks at the table columns
- **THEN** there is no slug column

### Requirement: Row action menu of a block type
Each row of the block types table SHALL have a group of action buttons: a "Settings" button (the edit form) and a menu with the items "Edit", "Display", "Fields", "Nodes", and "Delete" (in this order), where "Delete" is separated as a destructive action. A block type has no "SEO" item, because it has no "SEO" tab. Every destination of the section menu of a block type SHALL also be an item of the row menu. The number of records in the table row SHALL be a link to the list of records of this type. The "Create" button above the table SHALL lead to the creation form of a block type.

#### Scenario: Composition of the menu
- **WHEN** the user opens the "⋮" menu in the row of a block type
- **THEN** they see the items "Edit", "Display", "Fields", "Nodes", "Delete" in this order, and there is no "SEO" item

#### Scenario: Navigation to display
- **WHEN** the user selects the "Display" item in the row menu
- **THEN** `/admin/block-types/{key}/display` opens

#### Scenario: Navigation to fields
- **WHEN** the user selects the "Fields" item in the row menu
- **THEN** `/admin/block-types/{key}/fields` opens

#### Scenario: Navigation to nodes
- **WHEN** the user selects the "Nodes" item in the row menu or clicks the number of records
- **THEN** `/admin/block-types/{key}/nodes` opens

#### Scenario: Create button
- **WHEN** the user clicks "Create" above the table
- **THEN** `/admin/block-types/create` opens

### Requirement: Creating a block type
The admin panel SHALL have the form `/admin/block-types/create` with the fields: name, key, parent type, and description; there is no slug field in the form. Saving SHALL create a type of kind `block` regardless of the values in the request (the kind is not passed from the form), together with the system field `title`. Only a type of kind `block` SHALL be selectable as the parent type. Validation errors SHALL be shown next to the fields, with the entered values preserved. After success, the user SHALL land on the list of block types with a success message.

#### Scenario: Successful creation
- **WHEN** the user fills in the name and a unique key and saves the form
- **THEN** a type of kind `block` is created with the system field `title`, and the user is redirected to `/admin/block-types` with a message

#### Scenario: Kind in the request is ignored
- **WHEN** the creation request contains `kind=content` or `kind=page` or `slug`
- **THEN** the type is created with kind `block` and without a slug

#### Scenario: Parent of another kind
- **WHEN** the creation request specifies as the parent type a key of a type of kind `content` or `page`
- **THEN** saving is rejected with an error next to the parent type field

#### Scenario: Duplicate key
- **WHEN** the key is already taken by any type
- **THEN** saving is rejected with an error next to the key field

### Requirement: Editing a block type
The admin panel SHALL have the form `/admin/block-types/{key}/edit` with the fields name, parent type, and description, and the key read-only. Saving SHALL change only the name, the parent type, and the description, and the user SHALL remain on the edit form; `key` and `kind` do not change. The type itself or its descendant cannot be chosen as the parent. For a key that is not among types of kind `block` (in particular a key of a type `content` or `page`), all routes of the section SHALL respond with 404, and `/admin/content-types/{key}/**` and `/admin/page-types/{key}/**` for a `block` type — also with 404.

#### Scenario: Successful save
- **WHEN** the user changes the name of a block type and saves
- **THEN** the name is updated, and the user stays on the type edit form with a message

#### Scenario: Type of another kind
- **WHEN** the user opens `/admin/block-types/{key}/edit` for the key of a type of kind `content` or `page`
- **THEN** the response is 404

#### Scenario: Other sections do not open a block type
- **WHEN** the user opens `/admin/content-types/{key}/edit` or `/admin/page-types/{key}/edit` for the key of a type of kind `block`
- **THEN** the response is 404

#### Scenario: Descendant as the parent
- **WHEN** the edit request specifies a descendant of the type as the parent
- **THEN** saving is rejected with an error next to the parent type field

### Requirement: Deleting a block type
The row menu SHALL open a delete confirmation dialog with the name of the type. A type that has records or child types SHALL NOT be deleted: the dialog explains the reason without a confirm button, and the server refuses with an error message. Otherwise the type is deleted together with its fields, and the user lands on the list with a success message.

#### Scenario: Deleting an empty type
- **WHEN** the user confirms the deletion of a type without records and child types
- **THEN** the type and its fields are deleted, and the user sees a success message on `/admin/block-types`

#### Scenario: Type with records
- **WHEN** the type has records
- **THEN** the dialog has no confirm button, and a delete request is rejected with an error message

#### Scenario: Type with child types
- **WHEN** the type has child types
- **THEN** the deletion is rejected with an error message

### Requirement: Section menu of a block type
The edit pages of a block type, the "Display" tab, the list of fields, the creation and editing of a field, and the records of the type SHALL have a horizontal section menu with the items "Settings" (`/admin/block-types/{key}/edit`), "Display" (`/admin/block-types/{key}/display`), "Fields" (`/admin/block-types/{key}/fields`), and "Nodes" (`/admin/block-types/{key}/nodes`), and a link back to the list of block types. The active item SHALL be highlighted; on the pages of creating and editing a field the active item is "Fields", on the record pages — "Nodes", on the "Display" tab — "Display". The creation page of a type has no menu. The "Display" tab of a block type contains only the "Public display" block (the choice of page fields) and is saved by its own "Save" button.

#### Scenario: Switching sections
- **WHEN** on the edit page of a block type the user selects "Fields"
- **THEN** the list of fields of the type opens, and the "Fields" item is active

#### Scenario: Navigation to nodes
- **WHEN** on the edit page of a block type the user selects "Nodes"
- **THEN** `/admin/block-types/{key}/nodes` opens, and the "Nodes" item is active

#### Scenario: Navigation to display
- **WHEN** on the edit page of a block type the user selects "Display"
- **THEN** `/admin/block-types/{key}/display` opens, the "Display" item is active, and the "Public display" block contains no card fields, sorting, or count

#### Scenario: Link back
- **WHEN** the user clicks the link back on the fields page of a block type
- **THEN** `/admin/block-types` opens

### Requirement: Fields of a block type
The admin panel SHALL provide for types of kind `block` the same pages and field actions as for content types, at the addresses `/admin/block-types/{key}/fields`, `/fields/create`, `/fields/{field}/edit`: the list (a client table, order by weight), creation, editing, deletion, and drag-and-drop ordering, with the same validation rules and prohibitions (the system field `title` and a field with values cannot be deleted; the field key is immutable). After the actions, the user SHALL return to the list of fields of the block type.

#### Scenario: List of fields
- **WHEN** the user opens `/admin/block-types/{key}/fields`
- **THEN** they see all fields of the block type, including the system `title`, in order of weight

#### Scenario: Creating a field
- **WHEN** the user creates a field with a unique key in a block type
- **THEN** the field is added, and the user is redirected to `/admin/block-types/{key}/fields` with a message

#### Scenario: Deleting a system field
- **WHEN** a deletion request for the field `title` arrives
- **THEN** the field remains, and the user sees an error message

#### Scenario: Changing the order
- **WHEN** the user drags a field to a new place
- **THEN** the new sequence is saved in `weight` and persists after reload

#### Scenario: Field of another type
- **WHEN** the address specifies a field key that does not exist in this block type
- **THEN** the response is 404
