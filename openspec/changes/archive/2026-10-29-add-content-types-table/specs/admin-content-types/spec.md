## ADDED Requirements

### Requirement: Content types table page
The admin SHALL have a page `/admin/content-types`, available only to authenticated users with a confirmed email, which shows all content types in the data table in server mode: search, filters, sorting, pagination and page size are performed by the server, and the state lives in the address.

#### Scenario: Opening the page
- **WHEN** an authenticated user opens `/admin/content-types`
- **THEN** they see the table of content types, and the title matches the interface language

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/content-types`
- **THEN** they are redirected to the login page

#### Scenario: State restoration
- **WHEN** the user applied a search, a sorting and a second page, and then reloaded the page
- **THEN** the table shows the same set of rows

### Requirement: Table columns
The table SHALL show for each type: the name, the key, the kind (`kind`), the parent type key, the number of fields, the number of nodes and the description. The kind SHALL be shown with its translated name; a missing parent type or description SHALL be shown as an empty value. The description column SHALL be hidden by default; the name and key columns SHALL be non-hideable.

#### Scenario: Counters
- **WHEN** a type has 3 fields (including the system `title`) and 5 nodes
- **THEN** its row shows 3 fields and 5 nodes

#### Scenario: Type without a parent
- **WHEN** a type has no `parent_type`
- **THEN** the parent type column shows an empty value

### Requirement: Search, filters and sorting
The table SHALL support search by name, key and description; filters by name, key, description, kind (a choice from the list of kinds), parent type, and the counters of fields and nodes; sorting by name, key, kind, parent type and the counters. Without an explicit sorting the types SHALL be ordered by name in ascending order.

#### Scenario: Search by key
- **WHEN** the user types part of a type key
- **THEN** only the types whose name, key or description contains this string remain, case-insensitively

#### Scenario: Filter by kind
- **WHEN** the user adds the filter "kind equals page"
- **THEN** only the types of the kind `page` are shown, and the address contains this filter

#### Scenario: Filter by a counter
- **WHEN** the user adds the filter "number of nodes is at least 1"
- **THEN** only the types that have at least one node are shown

#### Scenario: Default sorting
- **WHEN** the user opens the page without sorting parameters
- **THEN** the types are ordered by name in ascending order

### Requirement: Create button
Above the table there SHALL be a "Create" button in the toolbar. In this change it is only a skeleton: it is displayed but inactive until the type creation form appears.

#### Scenario: Button is visible
- **WHEN** the user opens the page
- **THEN** the "Create" button with a translated label is visible in the toolbar, it is marked as inactive and does not open any page

### Requirement: Row action menu
Each row SHALL have a "⋮" menu with the items "Edit", "Fields" and "Delete" (in this order; "Delete" is visually highlighted as a destructive action and separated from the others). In this change the items are only a skeleton: they are displayed but inactive until the corresponding pages and operations appear.

#### Scenario: Menu content
- **WHEN** the user opens the "⋮" menu in a type row
- **THEN** they see the items "Edit", "Fields", "Delete" with translated labels

#### Scenario: Items are inactive
- **WHEN** the user chooses any menu item
- **THEN** nothing happens: no navigation is performed, and the data of the type is not changed or deleted

### Requirement: Empty state
If there are no types or the filters found nothing, the page SHALL show the standard message of the data table without an error; the "Create" button remains visible.

#### Scenario: No types
- **WHEN** the system has no content types at all
- **THEN** the page opens, the table shows a message about the absence of data, and the "Create" button is visible
