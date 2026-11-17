# admin-content-types Specification

## Purpose

Gives the admin panel a page with a table of all content types (server-side search, filters, sorting, pagination) and a frame of actions on types.

## Requirements

### Requirement: Types table page
The admin panel SHALL have the page `/admin/content-types`, available only to authenticated users with a verified email, which shows all content types of kind `content` in a data table in server mode: search, filters, sorting, pagination, and page size are performed by the server, and the state lives in the address. Types of kinds `page` and `block` SHALL NOT be shown on this page (they will have their own pages).

#### Scenario: Opening the page
- **WHEN** an authenticated user opens `/admin/content-types`
- **THEN** they see the table of content types, and the heading matches the interface language

#### Scenario: Only kind content
- **WHEN** the system has types of kinds `content`, `page`, and `block`
- **THEN** the table shows only types of kind `content`

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/content-types`
- **THEN** they are redirected to the login page

#### Scenario: Restoring the state
- **WHEN** the user has applied a search, a sorting, and the second page, and reloaded the page
- **THEN** the table shows the same set of rows

### Requirement: Columns of the table
The table SHALL show for each type: the name with the description, the key, the slug, the parent type, the number of child types, the number of fields, and the number of nodes. The description SHALL be shown in the name column as a separate line under the name (in a smaller muted font) and SHALL NOT have its own column; an empty description adds no line. The table SHALL NOT have a column of kind (`kind`). An absent slug or parent type is an empty value. The name and key columns SHALL be non-hideable. On the content types page (`/admin/content-types`) the number of nodes SHALL be a link to the list of nodes of this type; on the page and block sections — a plain number.

The parent type column SHALL show the **name** of the parent type (not its key). For a user with the `types` right the name SHALL be a link to the node list of the parent type in the same section; for a user without it, plain text. The column SHALL be sortable by the displayed name. The child types column SHALL show the number of direct child types, SHALL be visible by default and sortable, and for a user with the `types` right the number SHALL be a link to the "Child types" tab of the row's type; without the `types` right the column SHALL NOT be shown.

#### Scenario: Counters
- **WHEN** a type has 3 fields (together with the system `title`) and 5 nodes
- **THEN** its row shows 3 fields and 5 nodes

#### Scenario: Node counter as a link
- **WHEN** the user clicks the number of nodes in the row of the type `news` in the content types section
- **THEN** `/admin/content-types/news/nodes` opens

#### Scenario: Node counter without a link
- **WHEN** the user looks at the number of nodes in the table of page types
- **THEN** it is a plain number without a link

#### Scenario: Type without a parent
- **WHEN** a type has no `parent_type`
- **THEN** the parent type column is empty

#### Scenario: Parent shown by name
- **WHEN** the type `institutions` has the parent `common` named "Common"
- **THEN** the parent type column of its row shows "Common", and not `common`

#### Scenario: Parent as a link
- **WHEN** a user with the `types` right clicks "Common" in the parent type column of the row of `institutions`
- **THEN** `/admin/content-types/common/nodes` opens

#### Scenario: Sorting by the parent name
- **WHEN** the user sorts the table by the parent type column ascending
- **THEN** the rows are ordered by the displayed parent names, and the rows without a parent come together at one end

#### Scenario: Child type counter
- **WHEN** the type `common` has two child types
- **THEN** its row shows 2 in the child types column, and the number is a link to `/admin/content-types/common/children`

#### Scenario: Type without children
- **WHEN** a type has no child types
- **THEN** its child types column shows 0, and the number still links to the "Child types" tab

#### Scenario: Child types column without the types right
- **WHEN** a user with only the node level on a type opens the types table
- **THEN** the child types column is absent, and the parent type column shows the parent name as plain text

#### Scenario: Slug in the table
- **WHEN** one type has the slug `news`, and another has no slug
- **THEN** the first one shows `news` in the slug column, and the second one shows an empty value

#### Scenario: Description under the name
- **WHEN** a type has the name "News" and the description "The site news feed"
- **THEN** the name column shows "News", and under it in a smaller muted font — "The site news feed"

#### Scenario: Type without a description
- **WHEN** a type has no description
- **THEN** the name column shows only the name, without an empty line under it

#### Scenario: No kind and description columns
- **WHEN** the user opens the column selection menu
- **THEN** among the columns there is no "Kind" and no "Description"

### Requirement: Create button
Above the table there SHALL be an active "Create" button in the toolbar, which opens the form for creating a content type.

#### Scenario: Button is visible
- **WHEN** the user opens the page
- **THEN** an active "Create" button with a translated label is visible in the toolbar

#### Scenario: Navigation to the form
- **WHEN** the user clicks "Create"
- **THEN** `/admin/content-types/create` opens

#### Scenario: Button in the empty state
- **WHEN** the system has no content types at all
- **THEN** the "Create" button is visible and active

### Requirement: Row action menu
Each row SHALL have a group of buttons: the main button "Settings" and next to it the "⋮" menu button. "Settings" SHALL open the edit form of this type. The "⋮" menu in the content types section SHALL have the items "Edit", "Display", "SEO", "Fields", "Nodes", "Preview", and "Delete" (in this order; "Delete" is visually highlighted as a destructive action and separated from the others). "Edit" SHALL open the edit form of this type, "Display" — the "Display" tab of this type, "SEO" — the "SEO" tab of this type, "Fields" — the fields page of this type, "Nodes" — the list of nodes of this type, "Preview" — the public list page of the type, `/{type slug}`, in a new browser tab, "Delete" — start the deletion with confirmation. The "Preview" item SHALL exist only for a type that has a non-empty slug; without a slug the menu has no "Preview". Every destination of the section menu of an existing type (see "Horizontal menu of the type section") that is available for the kind SHALL also be an item of the row menu, and the other way round, so the row menu and the section menu never differ in their destinations; "Preview" is available in both under the same condition. The page and block sections have the same row menu, except that the block section has no "SEO" item and neither section has "Preview" (see `admin-page-types` and `admin-block-types`).

#### Scenario: Composition of the group
- **WHEN** the user sees the row of a type
- **THEN** the active "Settings" button and next to it the "⋮" menu button with an accessible name are visible in it

#### Scenario: Settings
- **WHEN** the user clicks "Settings" in the row of the type with the key `news`
- **THEN** `/admin/content-types/news/edit` opens

#### Scenario: Composition of the menu
- **WHEN** the user opens the "⋮" menu in the row of a type with a slug in the content types section
- **THEN** they see the active items "Edit", "Display", "SEO", "Fields", "Nodes", "Preview", "Delete" in this order with translated labels

#### Scenario: Composition of the menu without a slug
- **WHEN** the user opens the "⋮" menu in the row of a type without a slug
- **THEN** the items are "Edit", "Display", "SEO", "Fields", "Nodes", "Delete", without "Preview"

#### Scenario: Edit
- **WHEN** the user selects "Edit" in the row of the type with the key `news`
- **THEN** `/admin/content-types/news/edit` opens

#### Scenario: Display
- **WHEN** the user selects "Display" in the row of the type with the key `news`
- **THEN** `/admin/content-types/news/display` opens and the "Display" item of the section menu is active

#### Scenario: SEO
- **WHEN** the user selects "SEO" in the row of the type with the key `news`
- **THEN** `/admin/content-types/news/seo` opens and the "SEO" item of the section menu is active

#### Scenario: Items are active
- **WHEN** the user selects "Fields" in the row of the type with the key `news`
- **THEN** `/admin/content-types/news/fields` opens

#### Scenario: Nodes
- **WHEN** the user selects "Nodes" in the row of the type with the key `news`
- **THEN** `/admin/content-types/news/nodes` opens

#### Scenario: Preview
- **WHEN** the user selects "Preview" in the row of the type with the slug `news`
- **THEN** `/news` opens in a new tab

### Requirement: Empty state
If there are no types or the filters found nothing, the page SHALL show the standard message of the data table without an error; the "Create" button remains visible.

#### Scenario: No types
- **WHEN** the system has no content types at all
- **THEN** the page opens, the table shows a message that there is no data, and the "Create" button is visible

### Requirement: Search and sorting
The table SHALL support search by the name, the description, the slug, and the key, and sorting by the name, the key, the slug, the parent type, and the counters of fields and nodes. The table SHALL NOT have filters: there are no filter controls in the toolbar, and filters in the address are ignored. Without an explicit sort the types SHALL be ordered by name in ascending order.

#### Scenario: Search by name
- **WHEN** the user types part of a type's name
- **THEN** only the types whose name, description, slug, or key contains this string (case-insensitively) remain

#### Scenario: Search by key
- **WHEN** the user types part of a type's key
- **THEN** only the types whose key contains this string (case-insensitively) remain

#### Scenario: Search by description
- **WHEN** the user types a word that is only in the description of a type
- **THEN** this type remains in the table

#### Scenario: Search by slug
- **WHEN** the user types part of a type's slug
- **THEN** this type remains in the table

#### Scenario: No filters
- **WHEN** the user looks at the toolbar of the table
- **THEN** there is no filter button there; the search, the column selection, the "Create" button, and (if there are changes) "Reset" remain

#### Scenario: Filters in the address are ignored
- **WHEN** the address has a filter by any column (in particular `kind` or `nodes_count`)
- **THEN** the filter is ignored, and the table shows all types of kind `content`

#### Scenario: Default sorting
- **WHEN** the user opens the page without sorting parameters
- **THEN** the types are ordered by name in ascending order

### Requirement: Form of creating a type
The admin panel SHALL have the page `/admin/content-types/create`, available only to authenticated users with a verified email, with the form fields: the name (required), the key (required), the slug (optional), the parent type (an optional choice), and the description (optional). The form SHALL NOT have a kind field: the created type always has the kind `content`. The labels of the fields, the hints, and the error messages SHALL be displayed in the interface language. The form SHALL have a save button and a "Cancel" link that returns to the list without changes.

#### Scenario: Opening the form
- **WHEN** an authenticated user opens `/admin/content-types/create`
- **THEN** they see the form with the fields name, key, slug, parent type, and description, and without a kind field

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/content-types/create` or submits the form
- **THEN** they are redirected to the login page, and no type is created

#### Scenario: Cancellation
- **WHEN** the user clicks "Cancel"
- **THEN** they return to `/admin/content-types`, and no new type is created

### Requirement: Saving a new type
Submitting the creation form SHALL create a type of kind `content` with the given name, key, slug, parent type, and description (empty optional fields are stored as absent values; extra spaces at the edges are trimmed). The key SHALL have the format of type keys (lowercase Latin letters, digits, and `_`, starting with a letter, up to 64 characters) and be unique; the name — up to 255 characters; the slug SHALL meet the requirements for the type slug. The parent type SHALL be only an existing type of kind `content`. On creating a type the system SHALL automatically add its system field `title`. After success, the user SHALL land on `/admin/content-types` with a success message, and the new type SHALL be in the list.

#### Scenario: Successful creation
- **WHEN** the user enters the name "News", the key `news`, the slug `news`, and submits the form
- **THEN** a type of kind `content` with this key and slug and the system field `title` is created, the user is redirected to the list, where a success message and the new type are shown

#### Scenario: Minimal filling
- **WHEN** the user fills in only the name and the key
- **THEN** the type is created without a slug, a parent type, and a description

#### Scenario: Required fields
- **WHEN** the user submits the form without a name or without a key
- **THEN** the type is not created, the form remains open, errors are shown next to the empty fields, and the other entered values are kept

#### Scenario: Invalid or taken key
- **WHEN** the user enters the key `News 1` or a key that another type already has
- **THEN** the type is not created, and an error is shown next to the key field

#### Scenario: Invalid or taken slug
- **WHEN** the user enters a slug in an invalid format or one that another type already has (in any case)
- **THEN** the type is not created, and an error is shown next to the slug field

#### Scenario: Parent type
- **WHEN** the user selects an existing type of kind `content` as the parent
- **THEN** the new type is created with this `parent_type`

#### Scenario: Parent type of another kind
- **WHEN** a `parent_type` of a type of kind `page`, `block`, or a non-existent type is submitted
- **THEN** the type is not created, and an error is shown next to the parent type field

#### Scenario: Kind in the request is ignored
- **WHEN** `kind = page` is passed in the creation request
- **THEN** a type of kind `content` is created

### Requirement: Choice of the parent type in the forms
The list of parent types in the create and edit forms SHALL contain only types of kind `content`, sorted by name, and the option "no parent type". In the create form "no parent type" is selected by default. In the edit form the list SHALL NOT contain the type itself and all its descendants (so as not to create a cycle).

#### Scenario: Composition of the list
- **WHEN** the system has types of kinds `content`, `page`, and `block`
- **THEN** the list of parent types contains only types of kind `content`

#### Scenario: Default value
- **WHEN** the user opens the create form
- **THEN** no parent type is selected

#### Scenario: Excluding itself and descendants
- **WHEN** the user opens the edit form of a type that has a child type
- **THEN** the list of parent types contains neither this type nor its children (at any depth)

### Requirement: Edit form of a type
The admin panel SHALL have the page `/admin/content-types/{key}/edit`, available only to authenticated users with a verified email, with a form pre-filled with the current values of a type of kind `content`: name, slug, parent type, description, and the settings of the node list (the "Node list" section). The key SHALL be shown read-only with an explanation that it is immutable. The kind is absent from the form. For a non-existent key, and for a type of kind `page` or `block`, the page SHALL respond "not found". The form SHALL have a save button and a "Cancel" link.

The "Node list" section SHALL allow: (1) selecting the columns of the list among the fields of the type that have values in the nodes, and the service columns "Slug", "Status", "Active", "Creation date", "Update date", "Publication date", "Unpublishing date", "Views", "Author", and setting their order by dragging (the column `title` is always present and is not turned off, but its position can be changed; the order is changed also from the keyboard, and there are no "up/down" buttons); (2) selecting the search fields among the text fields of the type; (3) selecting the default sorting — the field and the direction. The options of the sorting field SHALL be: the service "Status", "Active", "Creation date", "Update date", "Publication date", "Unpublishing date", "Views" and the fields of the type that can be sorted (without file fields, `youtube`, and `term`); the slug is not sortable. The initial choice for a type without settings SHALL be "Creation date" in ascending order, and for the columns — `title` first, then the slug, the status, the publication date, and the author, as in the list without settings. The section SHALL be the same for types of all kinds (`content`, `page`, `block`).

#### Scenario: Opening the form
- **WHEN** the user opens `/admin/content-types/news/edit` for an existing type `content`
- **THEN** they see the form with the current name, slug, parent type, and description, the "Node list" section with the current settings, and the key `news` read-only

#### Scenario: Type without node list settings
- **WHEN** the user opens the settings of a type that has no `settings.list`
- **THEN** the section has the columns slug, status, and publication date selected, search by all text fields, and the sorting "Creation date" in ascending order

#### Scenario: Dragging columns
- **WHEN** the user drags the column `title` to the third position or moves it by the keyboard
- **THEN** the order in the form changes, and the checkbox `title` remains checked and disabled for changes

#### Scenario: Sorting options
- **WHEN** the type has the fields `price` (number), `image` (file), and `tags` (term)
- **THEN** the sorting options include "Status", "Active", "Creation date", "Update date", "Publication date", "Unpublishing date", "Views", and `price`, and `image`, `tags`, and the slug are absent

#### Scenario: Non-existent type
- **WHEN** the user opens the edit form for a non-existent key
- **THEN** the response is "not found"

#### Scenario: Type of another kind
- **WHEN** the user opens the edit form for the key of a type of kind `page` or `block`
- **THEN** the response is "not found"

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens the edit form or submits it
- **THEN** they are redirected to the login page, and the type is not changed

### Requirement: Saving changes of a type
Submitting the edit form SHALL update the name, the slug, the parent type, the description, and the node list settings of the type by the same rules as creation; the uniqueness of the slug SHALL be checked excluding the type itself. The key and the kind SHALL NOT change, even if they are passed in the request. An empty slug clears the value. The type itself or its descendant SHALL NOT be selectable as the parent. The node list settings SHALL be stored in `settings.list` (`visible`, `search`, `sort`) and update only this key, without changing the other keys of `settings`. The keys in `visible` and `search` SHALL belong to the fields of the type or (only in `visible`) to the key `title` or the service keys `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, `views`, `author`; the sorting field — to a field of the type or a service key from this list, except `slug`; the direction — `asc` or `desc`; unknown keys SHALL be rejected with an error next to the section. The choice of sorting "Creation date" in ascending order SHALL not store `sort`, and an empty choice of columns and search SHALL not store the corresponding keys. The edit form SHALL NOT change `settings.public`: this key is saved only by the "Display" tab, and an edit request with `settings.public` SHALL be rejected with a validation error. After success, the user SHALL remain on the edit form of the type with a success message.

#### Scenario: Successful change
- **WHEN** the user changes the name and the slug of an existing type to free admissible values and saves
- **THEN** the changes are saved, the key and the kind have not changed, and the user remains on the edit form of the type with a success message

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
- **WHEN** the type itself or its descendant is selected as the parent
- **THEN** the changes are not saved, and an error is shown next to the parent type field

#### Scenario: Saving columns, search, and sorting
- **WHEN** the user leaves `title` first, selects the columns `price`, status, `is_active`, `created_at`, `unpublished_at` in this order, searches by `title`, selects the sorting "Update date" in descending order, and saves
- **THEN** the `settings.list` of the type is `visible: ["title", "price", "status", "is_active", "created_at", "unpublished_at"]`, `search: ["title"]`, `sort: {field: "updated_at", direction: "desc"}`, and the node list shows these columns and this order

#### Scenario: Views as a column and as the sorting
- **WHEN** the user selects the column "Views" and the sorting "Views" in descending order, and saves
- **THEN** the `settings.list` of the type has `views` in `visible` and `sort: {field: "views", direction: "desc"}`

#### Scenario: Position of title is kept
- **WHEN** the user places `title` in the second position after `price` and saves
- **THEN** `settings.list.visible` begins with `["price", "title"]`

#### Scenario: Default sorting is not stored
- **WHEN** the user leaves the sorting "Creation date" in ascending order and saves
- **THEN** there is no `sort` key in `settings.list`

#### Scenario: Unknown field in the list settings
- **WHEN** the request contains in `visible` a key that is absent among the fields of the type and the service keys, or `slug` in `sort.field`
- **THEN** the changes are not saved, and an error is shown next to the "Node list" section

#### Scenario: Other settings keys are kept
- **WHEN** the type has another key in `settings` (in particular `public`), and the user saves the form with a changed list
- **THEN** the other key of `settings` remains unchanged

#### Scenario: Public settings in the edit form
- **WHEN** an edit request of a type contains `settings.public`
- **THEN** the changes are not saved, the response contains a validation error, and `settings.public` of the type is not changed

#### Scenario: Types of pages and blocks
- **WHEN** the user saves the node list settings in a type of kind `page` or `block`
- **THEN** they are saved and apply in the node list of the same section by the same rules

### Requirement: Deleting a type
The user SHALL be able to delete a type of kind `content` through the "Delete" item of the row menu after confirmation in a dialog that names the type. The deletion SHALL be forbidden if the type has nodes or child types: the type remains unchanged, and the user sees a message about the reason. The dialog SHALL show the reason for the prohibition and not allow confirming, if the reason is known from the row data. The server SHALL check the prohibition by itself, regardless of the dialog. A type without nodes and child types SHALL be deleted together with its fields; after success the user SHALL land on `/admin/content-types` with a success message. Deleting types of kinds `page` and `block` through this route SHALL be impossible.

#### Scenario: Confirmation dialog
- **WHEN** the user selects "Delete" in the row of a type without nodes and child types
- **THEN** a dialog opens with the name of the type, a confirm button, and a cancel button, and the type is not yet deleted

#### Scenario: Cancelling the deletion
- **WHEN** the user closes the dialog with the cancel button
- **THEN** the type remains in the list

#### Scenario: Successful deletion
- **WHEN** the user confirms the deletion of a type without nodes and child types
- **THEN** the type and its fields are deleted, the user is returned to the list with a success message, and the type is absent from the list

#### Scenario: Type with nodes
- **WHEN** the user selects "Delete" for a type that has nodes
- **THEN** the dialog explains that the nodes must be deleted first, and does not allow confirming the deletion

#### Scenario: Type with child types
- **WHEN** the user selects "Delete" for a type that has child types
- **THEN** the dialog explains that the child types must be deleted or moved first, and does not allow confirming the deletion

#### Scenario: Prohibition on the server
- **WHEN** a deletion request for a type with nodes or child types is sent to the deletion route
- **THEN** the type and its data are not changed, and the user sees an error message

#### Scenario: Guest
- **WHEN** an unauthenticated visitor sends a deletion request
- **THEN** they are redirected to the login page, and the type is not deleted

#### Scenario: Type of another kind
- **WHEN** the key of a type of kind `page` or `block` is sent to the deletion route
- **THEN** the response is "not found", and the type is not deleted

### Requirement: Limiting the width of the name column
The name column of the types table SHALL have a limited width: a long name and the description under it SHALL each be cut into one line with an ellipsis, without stretching the table or pushing the other columns. The full text of the description SHALL be available in a hint.

#### Scenario: Long description
- **WHEN** a type has a description longer than the width of the name column
- **THEN** the description takes one line and ends with an ellipsis, and the other columns remain in place

#### Scenario: Long name
- **WHEN** the name of a type is longer than the width of the column
- **THEN** the name takes one line and ends with an ellipsis

#### Scenario: Full description in the hint
- **WHEN** the user hovers over a cut description
- **THEN** they see the full text of the description

### Requirement: Horizontal menu of the type section
The pages for managing an existing content type of kind `content` — the edit form of the type (`/admin/content-types/{key}/edit`), the list of fields (`/admin/content-types/{key}/fields`), the create and edit forms of a field, the list of nodes (`/admin/content-types/{key}/nodes`), and the create and edit forms of a node — SHALL show above the content a horizontal section menu with the items "Settings" (leads to the edit form of the type), "Fields" (leads to the list of fields of this type), and "Nodes" (leads to the list of nodes of this type). The item of the current section SHALL be visually highlighted and marked as current for assistive technologies; on the create and edit pages of a field the current item SHALL be "Fields", on the pages of the list, create, and edit of a node — "Nodes". The menu SHALL be the same on all these pages, and above it there SHALL be a link back to the list of types. The creation page of a type does not show the menu. The menu of the sections of pages and blocks has no "Nodes" item.

Above the tabs the menu SHALL show a heading with the name of the type (`name`, the "Name" of the Settings tab, not the public `section_title`). The heading SHALL be the only title of the type pages (nodes list, fields list, edit form of the type, "Display", "SEO"): these pages SHALL NOT repeat a title of their own. The create and edit pages of a field and of a node SHALL NOT have a title of their own either, so the type name is their only level-1 heading.

Right after the last tab the menu SHALL show a "Preview" tab-styled link to the public list page of the type, `/{type slug}`, opening in a new browser tab. The link SHALL exist only for a type of kind `content` that has a non-empty slug; for pages, blocks, and a `content` type without a slug it SHALL NOT be shown. It is a simple link: if the site does not show the page, it responds with 404 by the rules of the public site. The link is shown to everyone who sees the menu, including a user with only the nodes tab.

#### Scenario: Menu on the edit form of a type
- **WHEN** the user opens `/admin/content-types/news/edit`
- **THEN** they see the menu with the items "Settings", "Fields", and "Nodes", where "Settings" is highlighted as current

#### Scenario: Menu on the list of fields
- **WHEN** the user opens `/admin/content-types/news/fields`
- **THEN** the menu is shown, "Fields" is current, and the link back to the list of types is above the menu

#### Scenario: Menu on the field forms
- **WHEN** the user opens the create or edit form of a field of the type `news`
- **THEN** the menu is shown, "Fields" is current

#### Scenario: Menu on the node pages
- **WHEN** the user opens the list, the create form, or the edit form of a node of the type `news`
- **THEN** the menu is shown, "Nodes" is current

#### Scenario: Switching between pages
- **WHEN** the user clicks "Fields" on the edit form of the type `news`, then "Nodes", then "Settings"
- **THEN** they land on `/admin/content-types/news/fields`, then on `/admin/content-types/news/nodes`, then on `/admin/content-types/news/edit`

#### Scenario: Creating a type
- **WHEN** the user opens `/admin/content-types/create`
- **THEN** the section menu is not shown

#### Scenario: Page type
- **WHEN** the user opens `/admin/page-types/home/edit`
- **THEN** the "Nodes" item is absent from the section menu

#### Scenario: Heading with the name of the type
- **WHEN** the user opens the edit form, "Display", "SEO", the fields list, or the node list of the type named "News"
- **THEN** the level-1 heading "News" is above the tabs, and there is no other title such as "Edit content type", "Display", or "Nodes of “News”" on the page

#### Scenario: Heading on the forms of a field and a node
- **WHEN** the user opens the create form of a node of the type "News"
- **THEN** the level-1 heading is "News", there is no "Create node" title, and there is exactly one level-1 heading

#### Scenario: Admin name, not public title
- **WHEN** the type has the name "News" and the public `section_title` "Latest news"
- **THEN** the heading above the tabs is "News"

#### Scenario: Preview of a type
- **WHEN** the user opens any tab of the type `news` with the slug `news` and clicks the "Preview" tab after "Nodes"
- **THEN** `/news` opens in a new tab

#### Scenario: No preview without a slug
- **WHEN** the user opens a tab of a `content` type without a slug, or of a page or block type
- **THEN** there is no "Preview" link

### Requirement: Tabs "Display" and "SEO" of the type
The menu of the type section SHALL contain the tab "Display" (`/admin/{section}/{key}/display`, where `{section}` is `content-types`, `page-types`, or `block-types`) for types of all kinds, between "Settings" and "Fields". For types `kind = content` and `kind = page` the menu SHALL contain, besides "Settings", "Display", "Fields", and "Nodes", the tab "SEO" (`/admin/content-types/{key}/seo` and `/admin/page-types/{key}/seo`, the fields `meta_title`, `meta_description`, `meta_image` of the type itself), which follows right after "Display"; types `block` have no "SEO" tab. The "Display" tab of a type `content` SHALL have two blocks: the section values (the fields `section_title`, `section_description`, `section_cover`) and "Public display"; the "Display" tab of types `page` and `block` SHALL have only the block "Public display" (they have no own section values). The "SEO" tab SHALL be a separate page with **one** form and one sticky bar "Cancel / Save": the `meta_*` values of the type and the block "Meta data" (see the requirement "Meta data block on the SEO tab") are saved in one atomic request. The settings page of the type (`/admin/{section}/{key}/edit`) SHALL contain only the main form of the type and the section "Node list", without value panels and without the block "Public display". The labels, the hints (the field description), and the length limits SHALL be taken from the field definitions. The input element SHALL correspond to the field type (short text, multiline text, formatted text, selection of an image from the media library). All values are optional; an empty value SHALL clear the saved one. The value tabs SHALL work only with the base language: there is no language switcher, the values are saved as base ones. Each tab SHALL have the same sticky action bar at the bottom of the window with "Cancel" and "Save" as the other forms of the admin panel; "Save" submits the form of the tab. The "Display" tab SHALL have **one** shared "Save" button that saves in one request both the section values (only for `content`) and `settings.public`; the saving SHALL be atomic: if any part does not pass validation, nothing is saved, and the errors are shown next to the corresponding fields (section values — next to the fields, public settings — in the block "Public display"). Saving the "Display" tab SHALL NOT change the values of the "SEO" tab, and saving "SEO" — the section values and the rest of `settings.public` (the block "Meta data" changes only `settings.public.meta`). The address `/admin/content-types/{key}/section` SHALL respond with 404. The address `/seo` for types `block` SHALL respond with 404.

#### Scenario: Menu of a content type
- **WHEN** the user opens any page of the type `news` of kind `content`
- **THEN** the type menu contains the tabs "Settings", "Display", "SEO", "Fields", "Nodes", and the current one is active

#### Scenario: Menu of a page or block type
- **WHEN** the user opens any page of a type of kind `page` or `block`
- **THEN** the type `page` has the tabs "Settings", "Display", "SEO", "Fields", "Nodes"; the type `block` — "Settings", "Display", "Fields", "Nodes" without "SEO"

#### Scenario: Opening the settings page
- **WHEN** the user opens the settings of the type `news` of kind `content`
- **THEN** the page contains the main form of the type and the section "Node list"; there are no value panels and no block "Public display" on it

#### Scenario: Opening the "Section" tab
- **WHEN** the user opens the "Display" tab (formerly "Section") of the type `news` of kind `content`
- **THEN** they see the block of section values with the current values of the base language, the block "Public display", and one action bar, and there are no SEO fields

#### Scenario: Opening the "Display" tab of a page type
- **WHEN** the user opens `/admin/page-types/home/display`
- **THEN** they see only the block "Public display" (the choice of page fields) and the action bar

#### Scenario: Saving section values
- **WHEN** the user fills in `section_title` "News", selects `section_cover` from the media library, and saves on the "Display" tab
- **THEN** the values are saved for the type, and after reloading the page they are shown

#### Scenario: Shared saving of section values and public settings
- **WHEN** the user changes `section_title` and the choice of page fields and clicks the single "Save" button
- **THEN** both the section values and `settings.public` are saved, and after reloading both are shown

#### Scenario: Error in one part does not save the other
- **WHEN** the user changes `section_title` and enters a negative number of nodes per page
- **THEN** saving is rejected, an error is shown next to the count field, and `section_title` remains the previous one

#### Scenario: Invalid section value
- **WHEN** the value of `section_title` exceeds the admissible length
- **THEN** saving is rejected, an error is shown next to the field, and the value and `settings.public` remain the previous ones

#### Scenario: Sticky action bar on the tabs
- **WHEN** the user opens the "Display" or "SEO" tab in a window 400 px high (at 1280 and 390 px wide)
- **THEN** the panel with "Cancel" and "Save" is fully visible at the bottom of the window

#### Scenario: Saving SEO does not touch the section
- **WHEN** the user changes `meta_title` on the "SEO" tab and saves
- **THEN** the `section_*` values and `settings.public` of the type remain unchanged

#### Scenario: Clearing a value
- **WHEN** the user clears `meta_description` on the "SEO" tab and saves
- **THEN** the saved value is deleted

#### Scenario: Invalid value
- **WHEN** the value of `meta_title` exceeds the admissible length
- **THEN** saving is rejected, an error is shown next to the field, and the values remain the previous ones

#### Scenario: Old tab address
- **WHEN** the user opens `/admin/content-types/news/section`
- **THEN** the response is "not found"

#### Scenario: SEO of a page type
- **WHEN** the user opens `/admin/page-types/home/seo`
- **THEN** they see the form of the type's values `meta_title`, `meta_description`, `meta_image` with an action bar and the block "Meta data", and saving the values does not change the values of the nodes of this type

#### Scenario: Page or block type
- **WHEN** the user opens the settings of a type of kind `block`
- **THEN** there is no "SEO" tab in the menu, and the address `/admin/block-types/{key}/seo` responds with 404; the "Display" tab exists (a type `page` has the "SEO" tab, see "SEO of a page type")

### Requirement: Block "Public display" on the "Display" tab
The "Display" tab of a type SHALL contain the block "Public display" for all kinds. For `block` and `page` it contains only the choice of page fields; for `content` — also the card fields, sorting (field + direction), the number of nodes per page, and the paginator toggle. The choice of meta fields (`settings.public.meta`) SHALL be absent on this tab: it lives on the "SEO" tab. Only the added fields (`is_system = false`) are offered for the choice; `title`, meta fields, and service attributes are not offered, because they are always available to the template. The block SHALL show the saved state with the default values and SHALL be saved together with the section values by the single "Save" button of the tab, updating only the key `public` of the settings and not changing the saved `settings.public.meta`. The form of the settings of the type does NOT contain this block.

#### Scenario: Default values
- **WHEN** the "Display" tab of a type `content` without public settings is opened
- **THEN** the toggle "All added fields" for the page is enabled, the card has no added fields, the sorting is "Publication date" in descending order, the count is "0" (all)

#### Scenario: Explicit choice of page fields
- **WHEN** the administrator turns off "All added fields", checks `price`, and saves
- **THEN** after reopening only `price` is checked, and a new field of the type does not appear among the checked ones

#### Scenario: Returning to "all"
- **WHEN** the administrator turns on "All added fields" and saves
- **THEN** the key `page_fields` is not stored, and a new field automatically enters the page

#### Scenario: Other keys are not erased
- **WHEN** the tab is saved with changed public settings
- **THEN** `settings.list` and the other keys of `settings` remain unchanged

#### Scenario: Saving does not erase meta settings
- **WHEN** the type has `settings.public.meta`, and the administrator changes the choice of page fields and saves the "Display" tab
- **THEN** `settings.public.meta` remains unchanged

#### Scenario: Choice of meta fields
- **WHEN** the administrator opens the "Display" tab of a type `content` or `page`
- **THEN** there is no choice of meta fields ("Title", "Description", "Image") on it: it is on the "SEO" tab

#### Scenario: Admissible fields for each choice
- **WHEN** a save request of the "Display" tab contains `settings.public.meta`
- **THEN** the saving is rejected with a validation error, nothing is changed; the admissible fields for each choice are described in the requirement "Meta data block on the SEO tab"

#### Scenario: Inadmissible field
- **WHEN** a request of the "Display" tab contains `settings.public.meta.image` with the key of a "string" field or of a non-existent field
- **THEN** saving is rejected, nothing is changed

#### Scenario: Meta data without a choice
- **WHEN** the administrator saves the "Display" tab of a type that has no `settings.public.meta`
- **THEN** the key `meta` does not appear in `settings.public`

#### Scenario: Block type
- **WHEN** the administrator opens the "Display" tab of a type of kind `block`
- **THEN** there is no sub-block "Meta data", and a request with `settings.public.meta` for such a type is rejected

#### Scenario: Settings form without the public block
- **WHEN** the user opens the settings form of a type of any kind
- **THEN** there is no block "Public display" on it

### Requirement: Number of nodes and paginator in the form
The field of the number of nodes SHALL accept an integer ≥ 0 (default `0` — all); at `0` the paginator toggle SHALL be disabled and not affect the result. The section SHALL show validation errors next to the corresponding fields.

#### Scenario: Paginator with "all"
- **WHEN** the number of nodes field has `0`
- **THEN** the "Paginator" toggle is disabled

#### Scenario: Invalid count
- **WHEN** a negative number or a number greater than the admissible bound is entered and "Save" is clicked
- **THEN** an error is shown next to the field, and the settings are not saved

### Requirement: Errors of a reserved key and of slug collisions in the type forms
The create form of a type SHALL reject a key from the list of reserved ones and show an error next to the key field. The create and edit forms of a type SHALL reject a slug that matches the slug of a node of kind `page` or a reserved site path, and show an error next to the slug field; the other entered values SHALL remain in the form. For the page and block sections the key SHALL be checked in the same way as for content. The messages SHALL be displayed in the interface language.

#### Scenario: Reserved key
- **WHEN** the user enters the key `pages` (or `content`, `blocks`, `fields`, `menus`, `errors`) in the create form of a type of any section and submits the form
- **THEN** the type is not created, an error "This key is reserved" is shown next to the key field, and the entered text remains in the form

#### Scenario: Slug matches a page
- **WHEN** the user enters the slug `about`, and a node of kind `page` already has the slug `about`
- **THEN** the type is not created, and an error is shown next to the slug field

#### Scenario: Slug matches a reserved path
- **WHEN** the user enters the slug `admin` in the edit form of a type
- **THEN** the change is not saved, and an error is shown next to the slug field

#### Scenario: Saving without changing the slug
- **WHEN** an existing type with a slug that once matched the slug of a page is saved with the unchanged slug and a new name
- **THEN** the change is saved

### Requirement: Meta data block on the SEO tab
The "SEO" tab of types `content` and `page` SHALL contain under the form of the `meta_*` values of the type the block "Meta data", framed by a border as an independent section, but without its own button: it is part of the shared form of the tab and is saved by the single "Save" button of the sticky panel together with the `meta_*` values. The block SHALL have three optional choices, each — one item or "Not selected": "Title" — the added fields of the type of the type "string"; "Description" — the added fields of types "text" and "formatted text"; "Image" — the added fields of types "image" and "YouTube video". The selected key SHALL be stored in `settings.public.meta` (`title`, `description`, `image`); an unselected item is not stored. Saving the tab (`PUT /admin/content-types/{key}/values` and `/admin/page-types/{key}/values`) SHALL in one transaction write the `meta_*` values and change in the settings only `settings.public.meta`, not changing the other keys of `settings.public` and `settings`, leave the user on the tab, and show a success message; an empty choice of all three SHALL remove the key `meta`, and an empty `public` — is not stored. An inadmissible field (a type outside the list, a non-existent or system one) SHALL be rejected with an error next to the block without changes in the type and without saving the `meta_*` values. For types `block` there is no block, and the addresses `…/seo` and `…/values` respond with 404. The site uses the value as the meta data of the page (the requirement of the public site "Page meta data…").

#### Scenario: Block on the SEO tab
- **WHEN** the user opens the "SEO" tab of the type `news` (`content`) or of the type `home` (`page`)
- **THEN** under the form of the `meta_*` values they see a separate block "Meta data" with a frame and three choices "Title", "Description", "Image", without its own button, and at the bottom of the window — one sticky panel "Cancel / Save"

#### Scenario: Choice of meta fields
- **WHEN** the administrator selects the field `headline` for "Title", `summary` for "Description", and `cover` for "Image" and saves the tab
- **THEN** after reopening the same fields are selected, and `settings.public.meta` has the keys `title`, `description`, `image`

#### Scenario: List of the choice "Title"
- **WHEN** the administrator opens the choice "Title"
- **THEN** they see "Not selected" and the added fields of type "string", without the service item `title`

#### Scenario: Admissible fields for each choice
- **WHEN** the administrator opens the choice "Description" or "Image"
- **THEN** "Description" has only the fields "text" and "formatted text", "Image" — "image" and "YouTube video"

#### Scenario: Inadmissible field
- **WHEN** a request saves `settings.public.meta.image` with the key of a "string" field, a non-existent field, or the key `title`, or `settings.public.meta.title` with the value `title`
- **THEN** saving is rejected with an error next to the block, nothing is changed

#### Scenario: The rest of the settings does not change
- **WHEN** the type has `settings.public.page_fields` and `settings.list`, and the administrator saves the tab
- **THEN** `settings.public.page_fields` and `settings.list` remain unchanged

#### Scenario: Meta data without a choice
- **WHEN** the administrator leaves all three choices "Not selected" and saves
- **THEN** the key `meta` is not stored in `settings.public`

#### Scenario: Shared saving of values and meta data
- **WHEN** the administrator changes `meta_title` of the type and the choice "Description" in the block and clicks the single "Save" button
- **THEN** both `meta_title` and `settings.public.meta` are saved, and after reloading both are shown

#### Scenario: Error in one part does not save the other
- **WHEN** a request contains a valid `meta_title` value and an inadmissible field in `settings.public.meta.image`
- **THEN** saving is rejected, an error is shown next to the block, and the `meta_title` value and `settings.public.meta` remain the previous ones

#### Scenario: Type of block
- **WHEN** a save request of `…/values` or the opening of `…/seo` arrives for a type of kind `block`
- **THEN** the response is 404

### Requirement: Slug of a nested type in the type forms
The slug field of the create and edit forms of a type of kind `content` SHALL accept several segments separated by `/` and explain the format in its hint (lowercase Latin letters, digits and hyphens in each segment, segments separated by `/`). When a parent type that has a slug is selected, the form SHALL show the prefix the slug has to start with (the parent's slug and `/`), and SHALL say so in the hint when the parent has no slug. The create and edit requests SHALL reject a slug that breaks the hierarchy of type slugs (a prefix other than the parent's slug, or a parent without a slug) and a change or clearing of the slug of a type whose child types have slugs, with an error next to the slug field; the other entered values SHALL remain in the form. Changing the parent type SHALL re-check the slug the same way. The messages SHALL be displayed in the interface language. The page-type and block-type forms have no slug and SHALL NOT change.

#### Scenario: Slug of several segments
- **WHEN** the user enters the slug `common/institutions` and no parent type, and submits the create form
- **THEN** the type is created with this slug

#### Scenario: Prefix hint for a parent with a slug
- **WHEN** the user selects a parent type with the slug `common` in the form
- **THEN** the form shows that the slug has to start with `common/`

#### Scenario: Hint for a parent without a slug
- **WHEN** the user selects a parent type without a slug
- **THEN** the form says that the parent has to have a slug before a child can have one

#### Scenario: Slug without the prefix of the parent
- **WHEN** the user selects the parent with the slug `common`, enters the slug `institutions`, and submits the form
- **THEN** the type is not created, an error is shown next to the slug field, and the entered values remain

#### Scenario: Changing the slug of a parent that has children with slugs
- **WHEN** the user changes the slug of a type whose child type has a slug, and saves
- **THEN** the change is not saved, and an error next to the slug field says that the slugs of the child types must be changed first

#### Scenario: Changing the parent
- **WHEN** the user changes the parent of a type with the slug `common/institutions` to a type with another slug and saves
- **THEN** the change is not saved, and an error is shown next to the slug field

#### Scenario: Reserved first segment
- **WHEN** the user enters the slug `admin/reports`
- **THEN** the type is not created, and an error is shown next to the slug field that the slug is reserved

### Requirement: Block "Archive" on the "Display" tab
The "Display" tab of a type of kind `content` SHALL contain the group "Archive" with the choice of the archive field and the sort of the archive list (field and direction). The group SHALL be a bordered `fieldset` with a `legend`, like the other groups of the tab. The archive field choice SHALL offer an empty option "Do not use", the service dates "Unpublishing date" (`unpublished_at`) and "Creation date" (`created_at`), and the added fields of type `date` and `datetime`; other fields SHALL NOT be offered. The sort controls SHALL be shown only while an archive field is chosen and SHALL offer the same fields as the main sort. The group SHALL NOT exist for kinds `page` and `block`. The state SHALL be saved with `settings.public` by the single "Save" button of the tab, without changing the other keys of `settings`, and a cleared choice SHALL remove the `archive` key. The default state is "Do not use".

Saving SHALL be rejected with a validation error, and nothing changed, when `settings.public.archive` has an unknown key, when `field` is not an offered field, when `sort` has an unknown field or direction, when `sort` is given without `field`, or when the type is not of kind `content`.

#### Scenario: Default state
- **WHEN** the "Display" tab of a type `content` without an archive is opened
- **THEN** the group "Archive" shows "Do not use" and no sort controls

#### Scenario: Group is a fieldset
- **WHEN** the "Display" tab of a type `content` is opened
- **THEN** the group "Archive" is a `fieldset` whose accessible name is its `legend` "Archive"

#### Scenario: Offered fields
- **WHEN** a type has the fields `date` (datetime), `starts` (date), `price` (integer), and `title`
- **THEN** the archive field choice offers "Do not use", "Unpublishing date", "Creation date", `date`, and `starts`, and does not offer `price`, `title`, or the publication and update dates

#### Scenario: Choosing a field
- **WHEN** the administrator chooses `date`, sets the archive sort to `date` in descending order, and saves
- **THEN** after reopening the tab the same choice is shown and `settings.public.archive` holds `field = date` and `sort = {date, desc}`

#### Scenario: Clearing the field
- **WHEN** the administrator chooses "Do not use" and saves
- **THEN** `settings.public` has no `archive` key and the sort controls of the group are hidden

#### Scenario: Other keys are not erased
- **WHEN** the tab is saved with a changed archive
- **THEN** `settings.list`, `settings.public.meta`, and the other keys of `settings.public` remain unchanged

#### Scenario: Inadmissible field
- **WHEN** a request contains `settings.public.archive.field` with the key of a `string` field, of a missing field, or of `published_at`
- **THEN** saving is rejected and nothing is changed

#### Scenario: Not a list kind
- **WHEN** the "Display" tab of a type of kind `page` or `block` is opened, or a request with `settings.public.archive` is sent for such a type
- **THEN** the tab has no group "Archive" and the request is rejected

### Requirement: Limiting the width of the slug column
The slug column of the types table of the content types section SHALL have a limited width: a long slug SHALL be cut into one line with an ellipsis, without stretching the table or pushing the other columns. The full slug SHALL be available in a hint.

#### Scenario: Long slug
- **WHEN** a type has a slug longer than the width of the column
- **THEN** the slug takes one line and ends with an ellipsis, and the other columns remain in place

#### Scenario: Full slug in the hint
- **WHEN** the user hovers over a cut slug
- **THEN** they see the full slug

### Requirement: Clone item in the row menu
The "⋮" menu of each row in the content, page and block type tables SHALL have a "Clone" item, placed after "Nodes"/"Preview" and before the divider that precedes "Delete". Selecting it SHALL open the clone dialog for that type. The item SHALL be available only to users who have the role right `types`.

#### Scenario: Item is present
- **WHEN** the user opens the "⋮" menu in the row of a type
- **THEN** the menu contains the active item "Clone" with a translated label

#### Scenario: Dialog opens
- **WHEN** the user selects "Clone" in the row of the type with the key `news`
- **THEN** a dialog opens with the fields name, key and (for content types) slug

### Requirement: Clone dialog and validation
The clone dialog SHALL ask for `name`, `key`, `parent type` and, only for content types, `slug`. `name`, `key` and `slug` SHALL start empty so that the user must choose new values; `parent type` SHALL be pre-filled with the parent of the source type and the user MAY change it or choose "No parent type" (only types of the same kind are offered). The values SHALL be validated by the same rules as the create form of a type of that kind (required name, key format, uniqueness and reserved keys, slug format, uniqueness, reserved paths, a parent of the same kind, and the slug hierarchy for the chosen parent). Errors SHALL be shown next to the fields and the entered values SHALL be kept.

#### Scenario: Key collision
- **WHEN** the user submits the dialog with a key that already belongs to another type
- **THEN** no type is created and an error is shown next to the key field

#### Scenario: Reserved key
- **WHEN** the user submits a key from the reserved type keys
- **THEN** the error "This key is reserved" is shown next to the key field

#### Scenario: Slug of a content type
- **WHEN** the user submits a slug that is already used by another type, by a page or by a node address
- **THEN** no type is created and the usual slug error is shown next to the slug field

#### Scenario: Page and block types have no slug
- **WHEN** the user opens the clone dialog in the page or block section
- **THEN** the dialog has no slug field

### Requirement: Result of cloning
Cloning SHALL create a new type of the same kind and with the parent type chosen in the dialog, named, keyed and slugged as entered, with an empty description, and SHALL copy every field definition of the source type to it: key, name, description, value type, multiplicity, maximum items, requiredness, translatability, label visibility, order and the field settings. The system fields of the new type SHALL be those every new type of the kind gets, and their editable attributes (name, description, order, label visibility, settings) SHALL equal those of the source. The type settings (node list settings and public display settings) of the source SHALL be copied to the new type unchanged. Cloning SHALL also copy the theme templates bound to the key of the source type, in every theme that has them, to the key of the new type, as described in the requirement "Theme templates of a cloned type". The operation SHALL be atomic: on any failure no type, no field and no theme file is created. Nodes, field values, the type's own values (SEO), the description, role levels and favorites SHALL NOT be copied, and the source type SHALL stay unchanged. After success the user SHALL be taken to the fields page of the new type with a `success` message.

#### Scenario: Fields are copied
- **WHEN** the type `news` has the fields `title`, `body` (required, translatable) and `cover` (an image with settings), and the user clones it as `press`
- **THEN** the type `press` exists with the same three fields, the same order and the same required, translatable and settings values, and the user sees the fields page of `press`

#### Scenario: Type settings are copied
- **WHEN** the type `news` has node list settings (visible columns, sort) and public display settings (list fields, per page) and is cloned
- **THEN** the new type has the same settings

#### Scenario: Parent can be changed
- **WHEN** the source has the parent `common` and the user clears the parent in the dialog
- **THEN** the new type has no parent

#### Scenario: Nothing else is copied
- **WHEN** the type `news` has nodes, SEO values and a description and is cloned
- **THEN** the new type has no nodes, no values of its own and an empty description, and `news` keeps all its nodes and values

#### Scenario: Kind is kept
- **WHEN** a page type is cloned from the page types section
- **THEN** the new type is of kind `page` and appears in the page types table

#### Scenario: Atomic failure
- **WHEN** creating any field of the new type fails
- **THEN** the new type is not created either, no theme file is created and an error is reported

#### Scenario: Source in another section
- **WHEN** a request clones a type through the route of a section other than the kind of the type
- **THEN** the response is 404

#### Scenario: Access
- **WHEN** a user without the role right `types` sends a clone request
- **THEN** the response is 403

### Requirement: Theme templates of a cloned type
When a type is cloned, the system SHALL copy, in every theme under `themes/`, the templates bound to the key of the source type to the key of the new type, and SHALL copy only what that theme has:
- the directory `views/{source}/` SHALL be copied to `views/{new}/` with its files, except the files bound to one node, whose names contain `--` followed by the node id (for example `preview--7.blade.php`);
- the file `views/blocks/block-{source}.blade.php` SHALL be copied to `views/blocks/block-{new}.blade.php`; the files bound to one block, `views/blocks/block-{source}--{slug}.blade.php`, SHALL NOT be copied.

Files SHALL be copied as they are: their content SHALL NOT be read or changed. A theme without such templates SHALL be left untouched, and the source templates SHALL stay unchanged. The copy SHALL NOT overwrite anything: if a target directory or file already exists, or a file cannot be written, the clone SHALL fail as a whole (no type, no field, no theme file remains, and the files already created by this clone are removed) and an error SHALL be reported to the user, who stays on the clone dialog's page with the entered values kept. Templates of page types (`pages/page-{source}`) and the type keys written inside theme code are not covered.

#### Scenario: Type directory is copied in every theme
- **WHEN** two themes have the directory `views/news/` with `list`, `preview` and `details` templates and the type `news` is cloned as `press`
- **THEN** both themes get `views/press/` with the same three files, and `views/news/` is unchanged

#### Scenario: Node-bound files are skipped
- **WHEN** `views/news/` contains `details.blade.php` and `details--7.blade.php` and the type is cloned as `press`
- **THEN** `views/press/` contains `details.blade.php` and does not contain `details--7.blade.php`

#### Scenario: Block template is copied
- **WHEN** the block type `contacts` has `views/blocks/block-contacts.blade.php` and `views/blocks/block-contacts--footer.blade.php` and is cloned as `office`
- **THEN** `views/blocks/block-office.blade.php` exists with the content of `block-contacts.blade.php`, and there is no `block-office--footer.blade.php`

#### Scenario: Content is not changed
- **WHEN** a copied template contains the word `news` in its comments or markup
- **THEN** the copy has exactly the same bytes as the source

#### Scenario: Nothing to copy
- **WHEN** no theme has templates bound to the key of the cloned type
- **THEN** the clone succeeds and no theme file is created

#### Scenario: Target already exists
- **WHEN** `views/press/` already exists in a theme and the type `news` is cloned as `press`
- **THEN** the clone fails with an error, the type `press` is not created, nothing in the existing `views/press/` is changed, and the files this clone already copied into other themes are removed

#### Scenario: Theme directory is not writable
- **WHEN** a theme directory cannot be written during the clone
- **THEN** the clone fails with an error, the type is not created and no file copied by this clone remains

### Requirement: "Child types" tab of the type section
The section menu of an existing type of any kind SHALL have, for a user with the `types` right, a "Child types" tab between "Nodes" and the "Preview" link (in the block section, which has no SEO tab, it follows "Nodes" as well). The tab label SHALL include the number of **direct** child types of the type, for example "Child types (2)". The tab SHALL be shown on every page that shows the section menu and SHALL NOT depend on the count: it is shown also when the type has no children. A user without the `types` right (who sees only the "Nodes" tab) SHALL NOT see it.

The tab SHALL lead to `{section}/{key}/children` (`/admin/content-types/{key}/children`, `/admin/page-types/{key}/children`, `/admin/block-types/{key}/children`), which requires the `types` right (403 without it) and answers 404 for a type of another kind. The page SHALL show the section menu with "Child types" as the current tab (and the type name as its heading) and a table of the **direct** child types of the type only: not the type itself, not deeper descendants, not unrelated types.

The table SHALL be the types table of the section with the same columns, the same search, sorting and column visibility, the same empty-state behavior, and the same row actions (the "Settings" button and the menu with the destinations of the section, "Clone" and "Delete") and favorites star as the main types table. Above the table there SHALL be an active "Create" button, also in the empty state. Deleting or cloning a row SHALL work as in the main table and keep the user on the tab.

#### Scenario: Tab with a count
- **WHEN** the user opens any tab of the type `common`, which has two direct child types
- **THEN** the section menu shows "Child types (2)" between "Nodes" and "Preview"

#### Scenario: Tab for a type without children
- **WHEN** the user opens a tab of a type with no child types
- **THEN** the section menu shows "Child types (0)"

#### Scenario: Count of direct children only
- **WHEN** `common` has the child `institutions`, which has its own child `deep`
- **THEN** the tab of `common` shows "Child types (1)", and the tab of `institutions` shows "Child types (1)"

#### Scenario: Only direct children are listed
- **WHEN** the user opens `/admin/content-types/common/children` for the case of the previous scenario
- **THEN** the table has the single row `institutions`; neither `common` nor `deep` is listed

#### Scenario: Current tab
- **WHEN** the user opens `/admin/content-types/common/children`
- **THEN** the heading is the name of `common`, and "Child types" is marked as the current tab

#### Scenario: Same row menu as in the main table
- **WHEN** the user opens the "⋮" menu of a row on the "Child types" tab
- **THEN** it has the same items as in the main types table of the section, including "Clone" and "Delete"

#### Scenario: Search inside the tab
- **WHEN** the user types a part of a child's name on the "Child types" tab
- **THEN** only the matching children of this type remain

#### Scenario: Empty tab
- **WHEN** the user opens the "Child types" tab of a type without children
- **THEN** the empty state of the table is shown together with the "Create" button

#### Scenario: Page and block sections
- **WHEN** the user opens `/admin/page-types/{key}/children` or `/admin/block-types/{key}/children`
- **THEN** the tab works the same way for the types of that kind

#### Scenario: No tab without the types right
- **WHEN** a user without the `types` right opens the node list of a type
- **THEN** the section menu has only the "Nodes" tab, and `/admin/content-types/{key}/children` answers 403

#### Scenario: Another kind
- **WHEN** the user opens `/admin/content-types/{key}/children` for the key of a page type
- **THEN** the response is 404

### Requirement: Creating a child type from the "Child types" tab
The "Create" button of the "Child types" tab SHALL open the create form of the section with the query parameter `parent_type={key of the type}`. The create form SHALL then have that type selected as the parent type, and the slug-prefix hint of the form SHALL be shown for it as for any selected parent. The parameter SHALL be honored only when it names a type that is offered in the parent list of the form; any other value SHALL be ignored, and the form SHALL open with no parent selected. After saving, the user SHALL be redirected to the types list of the section as for any new type.

#### Scenario: Parent preselected
- **WHEN** the user clicks "Create" on the "Child types" tab of the content type `common` whose slug is `common`
- **THEN** `/admin/content-types/create?parent_type=common` opens with "Common" selected as the parent type and the hint that the slug must start with `common/`

#### Scenario: Unknown parent in the address
- **WHEN** the user opens `/admin/content-types/create?parent_type=missing`
- **THEN** the form opens with no parent type selected

#### Scenario: Parent of another kind
- **WHEN** the user opens `/admin/content-types/create?parent_type={key of a page type}`
- **THEN** the form opens with no parent type selected

#### Scenario: Saving
- **WHEN** the user fills the form opened from the tab and saves
- **THEN** the new type is created as a child of that type, and the user lands on the types list with a success message

### Requirement: Breadcrumb trail above the section tabs
Above the tabs of the section menu, in place of the lone link back to the types list, the menu SHALL show a breadcrumb trail for a user with the `types` right: the link to the types list of the section ("Content Types", "Page Types" or "Block Types"), then the ancestors of the type from the root down to its direct parent, each a link to the settings page of that ancestor in the same section. The trail SHALL NOT include the type itself (its name is the heading). For a type without a parent the trail is the link to the types list only. A user without the `types` right SHALL see no trail, as before.

#### Scenario: Root type
- **WHEN** the user opens a tab of a type that has no parent
- **THEN** the trail is the single link back to the list of types

#### Scenario: Nested type
- **WHEN** the user opens a tab of `deep`, whose parent is `institutions`, whose parent is `common`
- **THEN** the trail reads "Content Types / Common / Institutions", and each item is a link (the types list, `/admin/content-types/common/edit`, `/admin/content-types/institutions/edit`)

#### Scenario: Going up
- **WHEN** the user clicks "Common" in the trail of `institutions`
- **THEN** the settings page of `common` opens

#### Scenario: No trail without the types right
- **WHEN** a user without the `types` right opens the node list of a nested type
- **THEN** no breadcrumb trail is shown

### Requirement: "Sitemap" tab of the type
The section menu of an existing type of kind `content` or `page` SHALL have a "Sitemap" tab (`/admin/content-types/{key}/sitemap`, `/admin/page-types/{key}/sitemap`) right after "SEO" and before "Fields", for a user with the `types` right, and the same destination SHALL be an item "Sitemap" of the row menu in the same position (the section menu and the row menu keep the same destinations). A type of kind `block` has no such tab, and `/admin/block-types/{key}/sitemap` SHALL answer 404 (also for saving). Opening or saving the tab without the `types` right SHALL answer 403. The tab SHALL be a separate page with **one** form and one sticky bar "Cancel / Save", with the type name as its heading above the tabs.

The form SHALL have two bordered blocks. The block "XML sitemap settings" SHALL have the checkboxes "Content type" (only for kind `content`) and "Content", the select "Change frequency" (`always`, `hourly`, `daily`, `weekly`, `monthly`, `yearly`, `never`) and the select "URL priority" (`0.0` to `1.0` in steps of `0.1`). The block "Web sitemap settings" SHALL have the checkboxes "Content type" (only for kind `content`) and "Content". When the type has no stored settings, the checkboxes SHALL be off and the selects SHALL show `weekly` and `0.5`; stored settings SHALL be shown as saved. A type of kind `content` without a slug SHALL show a hint that it has no public address and so does not appear in the maps; saving stays possible. Saving (`PUT /admin/content-types/{key}/sitemap`, `/admin/page-types/{key}/sitemap`) SHALL store `settings.sitemap` by the rules of the capability `type-sitemap`, keep the other keys of `settings`, and answer with a success message; a validation error SHALL show at the field and save nothing. The saved tab SHALL show the same values after reload. A cloned type SHALL receive the sitemap settings of its source along with the other type settings.

#### Scenario: Menu of a content type
- **WHEN** the user opens any page of the type `news` of kind `content`
- **THEN** the section menu has "Settings", "Display", "SEO", "Sitemap", "Fields", "Nodes" in this order, and the row menu has "Sitemap" after "SEO"

#### Scenario: Page type
- **WHEN** the user opens the "Sitemap" tab of a page type
- **THEN** both blocks have only the "Content" checkbox and there is no "Content type" checkbox

#### Scenario: Block type
- **WHEN** the user opens `/admin/block-types/{key}/sitemap` or the block types menu
- **THEN** the address answers 404 and there is no "Sitemap" tab or item

#### Scenario: Without the types right
- **WHEN** a user without the `types` right opens `/admin/content-types/news/sitemap`
- **THEN** the response is 403

#### Scenario: Defaults of an unconfigured type
- **WHEN** the user opens the tab of a type with no sitemap settings
- **THEN** all four checkboxes are off, and the selects show `weekly` and `0.5`

#### Scenario: Saving and reopening
- **WHEN** the user turns on "Content type" in the XML block, picks `monthly` and `0.8`, turns on "Content" in the web block, and saves
- **THEN** a success message is shown, and after reopening the tab the same values are shown

#### Scenario: Type without a slug
- **WHEN** the user opens the tab of a `content` type without a slug
- **THEN** a hint says it has no public address and will not appear in the maps, and the form still saves

#### Scenario: Other settings survive
- **WHEN** the type has `settings.list` and `settings.public` and the user saves the tab
- **THEN** those keys are unchanged

#### Scenario: Clone copies the settings
- **WHEN** the type `news` has sitemap settings and is cloned as `press`
- **THEN** `press` has the same sitemap settings

### Requirement: Translation mode of the Display and SEO tabs
The "Display" tab (the section values `section_title`, `section_description`, `section_cover`) and the "SEO" tab (the type's `meta_*` values) of a type SHALL follow the translation mode contract for the values the tab edits. In the translation mode the form SHALL keep the layout of the base form with only those values editable, prefilled from the base when empty; the shared settings of the tab (public display settings, archive, meta data options) SHALL stay in place, shown disabled, and SHALL NOT be changed. Saving SHALL change only the translations of the values of the current tab, SHALL NOT store a value equal to the base and SHALL remove a value that is emptied. The switcher SHALL mark a language when the type has any stored translated value in it on either tab. Types of kind `block` have no such values and SHALL show no switcher. The right to translate SHALL be the right to configure the type.

#### Scenario: Section values in a translation
- **WHEN** the user opens the Display tab of a content type with `?lang=en`
- **THEN** the section title, description and cover are editable and prefilled from the base, and the public display settings stay in place, disabled

#### Scenario: Saving a translated section title
- **WHEN** the user changes the section title to "News" and saves in English
- **THEN** the English section title is stored and the base title and the display settings are unchanged

#### Scenario: SEO tab
- **WHEN** the user opens the SEO tab of a page type with `?lang=en`
- **THEN** the meta title, meta description and meta image are editable, and the meta data block stays in place, disabled

#### Scenario: Block types
- **WHEN** the user opens the settings of a block type
- **THEN** no language switcher is shown

#### Scenario: Marker
- **WHEN** the type has an English `meta_title` and no other translation
- **THEN** `en` is marked on both tabs

### Requirement: File names and descriptions in the translation mode of a type
In the translation mode of the Display and SEO tabs, the files of the type's image values (the section cover and the meta image) SHALL let the user edit the display name and the description of each file in the current language, under the same rules as in a node translation: prefilled from the translated or the base value, stored only when different from the base, removed when emptied, the original name and the base description unchanged.

#### Scenario: Description of the meta image
- **WHEN** the user opens the SEO tab of a type with `?lang=en`, changes the description of the meta image and saves
- **THEN** the English description of that file is stored and the base description and the original name are unchanged

#### Scenario: Description of the section cover
- **WHEN** the user opens the Display tab with `?lang=en` for a type that has a section cover
- **THEN** the cover is shown with an editable English name and description prefilled from the base ones
