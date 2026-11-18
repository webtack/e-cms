## MODIFIED Requirements

### Requirement: Content types table page
The admin SHALL have a page `/admin/content-types`, available only to authenticated users with a confirmed email, which shows all content types of the `content` kind in the data table in server mode: search, filters, sorting, pagination and page size are performed by the server, and the state lives in the address. Types of the `page` and `block` kinds SHALL NOT be shown on this page (they will have their own pages).

#### Scenario: Opening the page
- **WHEN** an authenticated user opens `/admin/content-types`
- **THEN** they see the table of content types, and the title matches the interface language

#### Scenario: Only the content kind
- **WHEN** the system has types of the kinds `content`, `page` and `block`
- **THEN** the table shows only the types of the `content` kind

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/content-types`
- **THEN** they are redirected to the login page

#### Scenario: State restoration
- **WHEN** the user applied a search, a sorting and a second page, and then reloaded the page
- **THEN** the table shows the same set of rows

### Requirement: Table columns
The table SHALL show for each type: the name with the description, the key, the slug, the parent type key, the number of fields and the number of nodes. The description SHALL be shown in the name column as a separate line under the name (in a smaller muted font) and SHALL NOT have its own column; an empty description does not add a line. The table SHALL NOT have the kind column (`kind`). A missing slug or parent type is shown as an empty value. The name and key columns SHALL be non-hideable.

#### Scenario: Counters
- **WHEN** a type has 3 fields (including the system `title`) and 5 nodes
- **THEN** its row shows 3 fields and 5 nodes

#### Scenario: Type without a parent
- **WHEN** a type has no `parent_type`
- **THEN** the parent type column shows an empty value

#### Scenario: Slug in the table
- **WHEN** one type has the slug `news`, and another has none
- **THEN** the first shows `news` in the slug column, the second shows an empty value

#### Scenario: Description under the name
- **WHEN** a type has the name "News" and the description "Site news feed"
- **THEN** the name column shows "News", and under it, in a smaller muted font, "Site news feed"

#### Scenario: Type without a description
- **WHEN** a type has no description
- **THEN** the name column shows only the name, with no empty line under it

#### Scenario: No kind and description columns
- **WHEN** the user opens the column selection menu
- **THEN** there are no "Kind" and "Description" columns among them

### Requirement: Create button
Above the table there SHALL be an active "Create" button in the toolbar, which opens the form for creating a content type.

#### Scenario: Button is visible
- **WHEN** the user opens the page
- **THEN** an active "Create" button with a translated label is visible in the toolbar

#### Scenario: Going to the form
- **WHEN** the user clicks "Create"
- **THEN** `/admin/content-types/create` opens

#### Scenario: Button in the empty state
- **WHEN** the system has no content types at all
- **THEN** the "Create" button is visible and active

### Requirement: Row action menu
Each row SHALL have a "⋮" menu with the items "Edit", "Fields" and "Delete" (in this order; "Delete" is visually highlighted as a destructive action and separated from the others). "Edit" SHALL open the edit form of this type, "Delete" — start the deletion with confirmation. The "Fields" item SHALL remain a skeleton: it is displayed but inactive until the fields page appears.

#### Scenario: Menu content
- **WHEN** the user opens the "⋮" menu in a type row
- **THEN** they see the items "Edit", "Fields", "Delete" with translated labels

#### Scenario: Edit
- **WHEN** the user chooses "Edit" in the row of the type with the key `news`
- **THEN** `/admin/content-types/news/edit` opens

#### Scenario: Items are inactive
- **WHEN** the user chooses the "Fields" item
- **THEN** nothing happens: no navigation is performed, the data of the type does not change

## ADDED Requirements

### Requirement: Search and sorting
The table SHALL support search by name, description, slug and key, and sorting by name, key, slug, parent type and the counters of fields and nodes. The table SHALL NOT have filters: there are no filter controls in the toolbar, and filters in the address are ignored. Without an explicit sorting the types SHALL be ordered by name in ascending order.

#### Scenario: Search by name
- **WHEN** the user types part of a type name
- **THEN** only the types whose name, description, slug or key contains this string remain, case-insensitively

#### Scenario: Search by key
- **WHEN** the user types part of a type key
- **THEN** only the types whose key contains this string remain, case-insensitively

#### Scenario: Search by description
- **WHEN** the user types a word that is only in the description of a type
- **THEN** this type remains in the table

#### Scenario: Search by slug
- **WHEN** the user types part of a type slug
- **THEN** this type remains in the table

#### Scenario: No filters
- **WHEN** the user looks at the table toolbar
- **THEN** there is no filter button there; the search, the column selection, the "Create" button and (if there are changes) "Reset" remain

#### Scenario: Filters in the address are ignored
- **WHEN** the address contains a filter on any column (including `kind` or `nodes_count`)
- **THEN** the filter is ignored, and the table shows all types of the `content` kind

#### Scenario: Default sorting
- **WHEN** the user opens the page without sorting parameters
- **THEN** the types are ordered by name in ascending order

### Requirement: Create type form
The admin SHALL have a page `/admin/content-types/create`, available only to authenticated users with a confirmed email, with a form of fields: name (required), key (required), slug (optional), parent type (an optional choice) and description (optional). The form SHALL NOT have a kind field: the created type always has the kind `content`. Field labels, hints and error messages SHALL be displayed in the interface language. The form SHALL have a save button and a "Cancel" link, which returns to the list without changes.

#### Scenario: Opening the form
- **WHEN** an authenticated user opens `/admin/content-types/create`
- **THEN** they see a form with the name, key, slug, parent type and description fields, and without a kind field

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/content-types/create` or submits the form
- **THEN** they are redirected to the login page, and no type is created

#### Scenario: Cancel
- **WHEN** the user clicks "Cancel"
- **THEN** they return to `/admin/content-types`, and no new type is created

### Requirement: Saving a new type
Submitting the create form SHALL create a type of the `content` kind with the given name, key, slug, parent type and description (empty optional fields are stored as missing values; extra spaces at the edges are trimmed). The key SHALL match the format of type keys (lowercase Latin letters, digits and `_`, starts with a letter, up to 64 characters) and be unique; the name — up to 255 characters; the slug SHALL meet the requirements for a type slug. The parent type SHALL be only an existing type of the `content` kind. When a type is created, the system SHALL automatically add its system field `title`. After success the user SHALL land on `/admin/content-types` with a success message, and the new type SHALL be in the list.

#### Scenario: Successful creation
- **WHEN** the user enters the name "News", the key `news`, the slug `news` and submits the form
- **THEN** a type of the `content` kind with this key and slug and the system field `title` is created, the user is redirected to the list, where a success message and the new type are shown

#### Scenario: Minimal filling
- **WHEN** the user fills in only the name and the key
- **THEN** the type is created without a slug, a parent type and a description

#### Scenario: Required fields
- **WHEN** the user submits the form without a name or without a key
- **THEN** the type is not created, the form stays open, errors are shown next to the empty fields, and the other entered values are kept

#### Scenario: Invalid or taken key
- **WHEN** the user enters the key `News 1` or a key that another type already has
- **THEN** the type is not created, and an error is shown next to the key field

#### Scenario: Invalid or taken slug
- **WHEN** the user enters a slug in an invalid format or one that another type already has (in any case)
- **THEN** the type is not created, and an error is shown next to the slug field

#### Scenario: Parent type
- **WHEN** the user chooses an existing type of the `content` kind as the parent
- **THEN** the new type is created with this `parent_type`

#### Scenario: Parent type of another kind
- **WHEN** a `parent_type` of a type of the `page` or `block` kind, or of a non-existent type, is submitted
- **THEN** the type is not created, and an error is shown next to the parent type field

#### Scenario: Kind in the request is ignored
- **WHEN** the create request passes `kind = page`
- **THEN** a type of the `content` kind is created

### Requirement: Parent type choice in the forms
The list of parent types in the create and edit forms SHALL contain only types of the `content` kind, sorted by name, and the option "no parent type". In the create form "no parent type" is selected by default. In the edit form the list SHALL NOT contain the type itself and all of its descendants (so as not to create a cycle).

#### Scenario: List content
- **WHEN** the system has types of the kinds `content`, `page` and `block`
- **THEN** the list of parent types contains only types of the `content` kind

#### Scenario: Default value
- **WHEN** the user opens the create form
- **THEN** no parent type is selected

#### Scenario: Excluding itself and its descendants
- **WHEN** the user opens the edit form of a type that has a child type
- **THEN** the list of parent types contains neither this type nor its child types (at any depth)

### Requirement: Edit type form
The admin SHALL have a page `/admin/content-types/{key}/edit`, available only to authenticated users with a confirmed email, with a form pre-filled with the current values of a type of the `content` kind: name, slug, parent type, description. The key SHALL be shown read-only with an explanation that it is immutable. The kind is absent from the form. For a non-existent key, and for a type of the `page` or `block` kind, the page SHALL respond "not found". The form SHALL have a save button and a "Cancel" link.

#### Scenario: Opening the form
- **WHEN** the user opens `/admin/content-types/news/edit` for an existing type `content`
- **THEN** they see a form with the current name, slug, parent type and description, and the key `news` is read-only

#### Scenario: Non-existent type
- **WHEN** the user opens the edit form for a non-existent key
- **THEN** the response is "not found"

#### Scenario: Type of another kind
- **WHEN** the user opens the edit form for the key of a type of the `page` or `block` kind
- **THEN** the response is "not found"

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens the edit form or submits it
- **THEN** they are redirected to the login page, and the type is not changed

### Requirement: Saving type changes
Submitting the edit form SHALL update the name, slug, parent type and description of the type by the same rules as creation; the uniqueness of the slug SHALL be checked excluding the type itself. The key and the kind SHALL NOT change, even if they are passed in the request. An empty slug clears the value. The type itself or its descendant SHALL NOT be selectable as the parent. After success the user SHALL land on `/admin/content-types` with a success message.

#### Scenario: Successful change
- **WHEN** the user changes the name and slug of an existing type to free valid values and saves
- **THEN** the changes are saved, the key and the kind have not changed, and the user is redirected to the list with a success message

#### Scenario: Saving one's own slug
- **WHEN** the user saves the form without changing the slug of the type
- **THEN** there is no uniqueness error

#### Scenario: Slug of another type
- **WHEN** the user enters a slug that another type already has
- **THEN** the changes are not saved, and an error is shown next to the slug field

#### Scenario: Clearing the slug
- **WHEN** the user erases the slug and saves
- **THEN** the slug of the type is empty

#### Scenario: Key in the request is ignored
- **WHEN** a different `key` or `kind` is passed in the edit request
- **THEN** the key and the kind of the type remain unchanged

#### Scenario: Cycle in the hierarchy
- **WHEN** the type itself or its descendant is chosen as the parent
- **THEN** the changes are not saved, and an error is shown next to the parent type field

### Requirement: Deleting a type
The user SHALL be able to delete a type of the `content` kind via the "Delete" item of the row menu after confirmation in a dialog that names the type. Deletion SHALL be forbidden if the type has nodes or child types: the type remains unchanged, and the user sees a message with the reason. The dialog SHALL show the reason for the ban and SHALL NOT allow confirming the deletion if the reason is known from the row data. The server SHALL check the ban itself, regardless of the dialog. A type without nodes and child types SHALL be deleted together with its fields; after success the user SHALL land on `/admin/content-types` with a success message. Deleting types of the `page` and `block` kinds through this route SHALL be impossible.

#### Scenario: Confirmation dialog
- **WHEN** the user chooses "Delete" in the row of a type without nodes and child types
- **THEN** a dialog opens with the name of the type, a confirm button and a cancel button, and the type is not yet deleted

#### Scenario: Cancel deletion
- **WHEN** the user closes the dialog with the cancel button
- **THEN** the type remains in the list

#### Scenario: Successful deletion
- **WHEN** the user confirms the deletion of a type without nodes and child types
- **THEN** the type and its fields are deleted, the user is returned to the list with a success message, and the type is not in the list

#### Scenario: Type with nodes
- **WHEN** the user chooses "Delete" for a type that has nodes
- **THEN** the dialog explains that the nodes must be deleted first, and does not allow confirming the deletion

#### Scenario: Type with child types
- **WHEN** the user chooses "Delete" for a type that has child types
- **THEN** the dialog explains that the child types must be deleted or moved first, and does not allow confirming the deletion

#### Scenario: Ban on the server
- **WHEN** a deletion request for a type with nodes or child types is sent to the route
- **THEN** the type and its data are not changed, and the user sees an error message

#### Scenario: Guest
- **WHEN** an unauthenticated visitor sends a deletion request
- **THEN** they are redirected to the login page, and the type is not deleted

#### Scenario: Type of another kind
- **WHEN** the key of a type of the `page` or `block` kind is sent to the deletion route
- **THEN** the response is "not found", and the type is not deleted

## REMOVED Requirements

### Requirement: Search, filters and sorting
**Reason**: Filters are not needed on this page; search and sorting are separated into their own requirement "Search and sorting", and the set of searched fields has changed (name, description, slug, key).
**Migration**: Use search and sorting; filters by kind, counters, name, key, parent type or description are no longer available, and filters in the address are ignored.
