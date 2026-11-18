# Spec Delta

## Purpose

Gives the admin the management of nodes of content types of the kind `content`: a list in a data table, creation, editing and deletion of a node with a dynamic form by the fields of the type. Works only with the base language (`default`).

## ADDED Requirements

### Requirement: Node list page
The admin SHALL have a page `/admin/content-types/{key}/nodes`, available only to authenticated users with a confirmed email, which shows the nodes of the content type of the kind `content` with this key in the data table in server mode: search, sorting, pagination and page size are performed by the server, and the table state is kept in the address. The table SHALL NOT have filters. The page title SHALL name the type, and above the table there SHALL be the horizontal section menu of the type with the current item "Nodes". For a key that is not among the types of the kind `content` (including the key of a type `page` or `block`), the page SHALL respond 404. The page SHALL open also for a type without nodes.

#### Scenario: Opening the page
- **WHEN** an authenticated user opens `/admin/content-types/news/nodes`
- **THEN** they see the table of nodes of the type `news`, a title with the name of the type and the section menu where "Nodes" is current; there is no filter button in the table toolbar

#### Scenario: Only nodes of this type
- **WHEN** nodes of the types `news` and `product` exist, and the user opens the list of the type `news`
- **THEN** the table contains only nodes of the type `news`

#### Scenario: Empty state
- **WHEN** the type has no nodes
- **THEN** the page opens with the standard message of the table about the absence of data, and the "Create" button is visible

#### Scenario: Type of another kind
- **WHEN** the user opens `/admin/content-types/home/nodes` for the key of a type of the kind `page`
- **THEN** the server responds 404

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/content-types/news/nodes`
- **THEN** they are redirected to the login page

### Requirement: Columns of the node table
The table SHALL always show the column `title` first and non-hideable, and the column of the node slug. Besides them, it SHALL show the visible fields from the list settings of the type (`settings.list.visible`) in the order specified there; without such settings — only `title`. A reference in the settings to a field that the type no longer has SHALL be ignored without an error. The value of a multiple field SHALL be output separated by commas, a boolean — as "Yes"/"No" in the interface language, a missing value — as an empty cell. The value is taken from the language `default`.

#### Scenario: Default settings
- **WHEN** the type has no list settings
- **THEN** the table shows the columns `title` and slug

#### Scenario: Visible fields from the settings
- **WHEN** the `settings.list.visible` of the type is `title`, `price`, `tags`, and the node has `price` = 12.5 and `tags` = `["a", "b"]`
- **THEN** its row shows the columns `title`, `price` with the value 12.5, `tags` with the value "a, b" and the slug

#### Scenario: Deleted field in the settings
- **WHEN** `settings.list.visible` contains the key of a field that has already been deleted from the type
- **THEN** the page opens without an error, and the column of this field is absent

#### Scenario: Boolean value
- **WHEN** a node has a field of the type "boolean" with the value "true"
- **THEN** the cell shows "Yes"

### Requirement: Search and sorting of nodes
The table search SHALL search by the search fields from the settings of the type, and without them — by all text fields of the type, case-insensitively. It SHALL be possible to sort by the columns of single-valued fields; the slug column and multiple fields are not sortable. Without an explicit sorting the order SHALL be taken from the list settings of the type, and without them — by the creation order. Nodes without a value in the sorting column SHALL go last in both directions. A page number out of range SHALL return the last page. Invalid address parameters SHALL be ignored without a page error.

#### Scenario: Search
- **WHEN** the user types "lamp" in the search of the node table of the type `product`
- **THEN** the table keeps the nodes whose text fields contain "lamp", and the address contains `q=lamp`

#### Scenario: Sorting by a field
- **WHEN** the user sorts by the column `price` in descending order
- **THEN** the nodes go from the highest price, nodes without a price go last

#### Scenario: Sorting by a multiple field
- **WHEN** the address contains `sort=tags`, and `tags` is a multiple field
- **THEN** the sorting is ignored, and the page opens

#### Scenario: Page beyond the range
- **WHEN** the address contains `page=999`, and there are only three pages
- **THEN** the third page is shown

#### Scenario: Default order
- **WHEN** the type has in its settings a sorting by `title` in ascending order, and the address has no `sort`
- **THEN** the nodes go by `title` in ascending order

### Requirement: Creating a node from the table
Above the node table there SHALL be an active "Create" button in the toolbar, which opens the form for creating a node of this type. Each row SHALL have a group of buttons: the main button "Edit" and next to it a "⋮" menu button with the items "Edit" and "Delete" (in this order; "Delete" is visually highlighted as a destructive action and separated). Both "Edit" controls SHALL open the edit form of this node.

#### Scenario: Going to the creation form
- **WHEN** the user clicks "Create" on the nodes page of the type `news`
- **THEN** `/admin/content-types/news/nodes/create` opens

#### Scenario: Edit from a row
- **WHEN** the user clicks "Edit" in the row of a node with the `uuid` `0190a1b2-…` of the type `news`
- **THEN** `/admin/content-types/news/nodes/0190a1b2-…/edit` opens

#### Scenario: Composition of the row menu
- **WHEN** the user opens the "⋮" menu in a node row
- **THEN** they see the items "Edit" and "Delete" with translated labels

### Requirement: Node form
The creation form (`/admin/content-types/{key}/nodes/create`) and the edit form (`/admin/content-types/{key}/nodes/{uuid}/edit`) SHALL show above the form the section menu of the type and SHALL contain a slug field and one input element for each field of the type in the order of weight (`weight`), with the field name as the label; required fields SHALL be marked. An input element SHALL correspond to the value type: "short text" — a single-line field, "text" — a multi-line one, "integer" and "decimal number" — a numeric field, "boolean" — a checkbox, "date" — a date picker, "date and time" — a date and time picker. A multiple field SHALL be edited as a list of items, to which items can be added, from which they can be removed, and in which their order can be changed (by dragging and from the keyboard). The form does not show a language choice. The edit form SHALL be filled with the current values of the node in the language `default`, and its slug SHALL be the current slug.

#### Scenario: Creation form
- **WHEN** the user opens the creation form of a node of a type with the fields `title` (weight 0), `price` (integer, weight 10) and `body` (text, weight 20)
- **THEN** they see the empty fields slug, `title`, `price` and `body` in this order, `title` marked as required

#### Scenario: Elements by type
- **WHEN** the type has fields of the types "boolean", "date" and "date and time"
- **THEN** they are shown as a checkbox, a date picker and a date and time picker respectively

#### Scenario: Multiple field
- **WHEN** the type has the multiple field `tags`, and the user adds two items and removes the first
- **THEN** one item remains in the list, and adding a new item adds an empty input row

#### Scenario: Changing the order of items
- **WHEN** the user rearranges the items of the multiple field `tags` from `a, b, c` to `c, a, b` and saves
- **THEN** after reopening the edit form the items go in the order `c, a, b`, and in the node list the value is shown as "c, a, b"

#### Scenario: Edit form
- **WHEN** the user opens the edit form of a node with the slug `pro-nas`, the `title` "Про нас" and `tags` `["a", "b"]`
- **THEN** the slug field contains `pro-nas`, `title` — "Про нас", and the list `tags` — two items "a" and "b"

#### Scenario: Node of another type
- **WHEN** the user opens the edit form for the `uuid` of a node that belongs to another type
- **THEN** the server responds 404

### Requirement: Saving a new node
Submitting the creation form SHALL create a node of the type with the entered values in the language `default`. An empty slug SHALL lead to an automatic slug from `title`; an explicitly entered slug SHALL be checked for format and uniqueness among all nodes and SHALL not be changed. After success the user SHALL be redirected to the list of nodes of this type with a success message, and the node SHALL appear in the table. Validation errors SHALL return to the form and be shown next to the corresponding fields, in particular next to individual items of a multiple field; the entered data SHALL be kept in the form in this case.

#### Scenario: Successful creation
- **WHEN** the user enters the `title` "Про нас" and submits the form
- **THEN** the node is saved with the slug `pro-nas`, the user sees the list of nodes, a success toast and a new row in the table

#### Scenario: Explicit slug
- **WHEN** the user enters the slug `about` and the `title` "Про нас"
- **THEN** a node with the slug `about` is saved

#### Scenario: Taken slug
- **WHEN** the user enters a slug that a node of any type or kind already has
- **THEN** the node is not created, an error is shown next to the slug field, and the rest of the entered data remains in the form

#### Scenario: Invalid slug format
- **WHEN** the user enters the slug `Про нас`
- **THEN** the node is not created, and an error is shown next to the slug field

#### Scenario: Required field
- **WHEN** the user submits the form with an empty `title`
- **THEN** the node is not created, and an error is shown next to the `title` field

#### Scenario: Error of an item of a multiple field
- **WHEN** the multiple numeric field `scores` gets a non-numeric value as its second item
- **THEN** the node is not created, and the error is shown next to the second item of the list

#### Scenario: Value according to the field settings
- **WHEN** the field has a maximum of 5 in its settings, and 7 is entered
- **THEN** the node is not created, and an error is shown next to the field

### Requirement: Saving changes to a node
Submitting the edit form SHALL replace the values of all fields of the type in the language `default` with the entered ones (the order of the items of a multiple field is kept as in the form) (a cleared field SHALL lose its value), and SHALL allow changing the slug of the node to a free valid value; the `uuid` and the type of the node SHALL remain unchanged. An unchanged slug SHALL NOT be considered a duplicate of itself. After success the user SHALL be redirected to the list of nodes with a success message. Errors SHALL be shown in the same way as on creation. The values of other languages SHALL remain unchanged.

#### Scenario: Changing a value
- **WHEN** the user changes the `title` of a node and saves
- **THEN** the new `title` is shown in the list, the slug remained the same

#### Scenario: Clearing an optional field
- **WHEN** the user clears the optional field `summary` and saves
- **THEN** the value of `summary` of the node is absent

#### Scenario: Changing the slug
- **WHEN** the user changes the slug of a node to the free `about-us`
- **THEN** the node still opens at the same edit address (by `uuid`), its `uuid` has not changed, and the form of this node open in another tab is saved without a 404 error

#### Scenario: Saving without changing the slug
- **WHEN** the user saves the form without changing the slug
- **THEN** the save succeeds, and there is no uniqueness error

#### Scenario: Slug of another node
- **WHEN** the user enters a slug that another node has
- **THEN** the changes are not saved, and an error is shown next to the slug field

#### Scenario: Translations
- **WHEN** the node has a translation of `title` in the language `en`, and the user changes `title` in the form
- **THEN** only the value `default` changes, the translation `en` remains

### Requirement: Deleting a node
The "Delete" item in the row menu SHALL open a confirmation dialog that names the node (by `title`, and if it is absent — by the slug), with the buttons "Cancel" and "Delete". The confirmation SHALL delete the node together with all its values in all languages, keep the user on the list of nodes of this type and show a success message. Cancellation SHALL change nothing. A node of another type SHALL NOT be deleted through the address of this type.

#### Scenario: Confirmation
- **WHEN** the user chooses "Delete" in the row of the node "Про нас" and confirms
- **THEN** the node disappears from the table, a success message is shown, and the values of the node are deleted

#### Scenario: Cancellation
- **WHEN** the user chooses "Delete", and then "Cancel"
- **THEN** the dialog closes, the node remains

#### Scenario: Node of another type
- **WHEN** a deletion request is sent to the address of the type `news` with the `uuid` of a node of the type `product`
- **THEN** the server responds 404, the node remains

### Requirement: Addressing a node in the admin
All admin addresses that concern an existing node (the edit form, update, deletion) SHALL identify the node by its `uuid`, not by the slug. The slug of a node SHALL be only data of the node for the public site: the admin shows it in the table and allows changing it in the form, but does not use it for addressing. An unknown `uuid` or the `uuid` of a node of another type SHALL give 404.

#### Scenario: Address by uuid
- **WHEN** a node has the `uuid` `0190a1b2-…` and the slug `pro-nas`
- **THEN** its edit form opens at `/admin/content-types/news/nodes/0190a1b2-…/edit`, and the address with the slug `pro-nas` instead of the `uuid` responds 404

#### Scenario: Stability when the slug changes
- **WHEN** the slug of a node changes
- **THEN** the admin addresses of this node remain the same

### Requirement: Node section only for the content kind
The node routes SHALL exist only in the section `/admin/content-types/**`. The sections `/admin/page-types/**` and `/admin/block-types/**` SHALL NOT have node routes: a request to them responds 404. The breadcrumbs of the node pages SHALL lead through the item "Content Types".

#### Scenario: Page section
- **WHEN** the user opens `/admin/page-types/home/nodes`
- **THEN** the server responds 404

#### Scenario: Breadcrumbs
- **WHEN** the user opens the node creation form
- **THEN** the breadcrumbs are: "Admin" → "Content Types" (a link) → "Create node"
