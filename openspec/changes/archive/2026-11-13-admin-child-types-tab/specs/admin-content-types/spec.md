## MODIFIED Requirements

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

## ADDED Requirements

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
