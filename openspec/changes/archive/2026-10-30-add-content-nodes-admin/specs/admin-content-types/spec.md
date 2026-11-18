# Spec Delta

## MODIFIED Requirements

### Requirement: Table columns
The table SHALL show for each type: the name with the description, the key, the slug, the parent type key, the number of fields and the number of nodes. The description SHALL be shown in the name column as a separate line under the name (in a smaller muted font) and SHALL NOT have its own column; an empty description does not add a line. The table SHALL NOT have the kind column (`kind`). A missing slug or parent type is shown as an empty value. The name and key columns SHALL be non-hideable. In the content types section (`/admin/content-types`) the number of nodes SHALL be a link to the list of nodes of this type; in the page and block sections — a plain number.

#### Scenario: Counters
- **WHEN** a type has 3 fields (including the system `title`) and 5 nodes
- **THEN** its row shows 3 fields and 5 nodes

#### Scenario: Node counter as a link
- **WHEN** the user clicks the number of nodes in the row of the type `news` in the content types section
- **THEN** `/admin/content-types/news/nodes` opens

#### Scenario: Node counter without a link
- **WHEN** the user looks at the number of nodes in the page types table
- **THEN** it is a plain number without a link

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

### Requirement: Row action menu
Each row SHALL have a group of buttons: a main "Settings" button and next to it a "⋮" menu button. "Settings" SHALL open the edit form of this type. The "⋮" menu in the content types section SHALL have the items "Edit", "Fields", "Nodes" and "Delete" (in this order; "Delete" is visually highlighted as a destructive action and separated from the others). "Edit" SHALL open the edit form of this type, "Fields" — the fields page of this type, "Nodes" — the list of nodes of this type, "Delete" — start the deletion with confirmation. In the page and block sections there is no "Nodes" item.

#### Scenario: Composition of the group
- **WHEN** the user sees a type row
- **THEN** an active "Settings" button and next to it a "⋮" menu button with an accessible name are visible in it

#### Scenario: Settings
- **WHEN** the user clicks "Settings" in the row of the type with the key `news`
- **THEN** `/admin/content-types/news/edit` opens

#### Scenario: Composition of the menu
- **WHEN** the user opens the "⋮" menu in a type row in the content types section
- **THEN** they see the active items "Edit", "Fields", "Nodes", "Delete" with translated labels

#### Scenario: Edit
- **WHEN** the user chooses "Edit" in the row of the type with the key `news`
- **THEN** `/admin/content-types/news/edit` opens

#### Scenario: Items are inactive
- **WHEN** the user chooses "Fields" in the row of the type with the key `news`
- **THEN** `/admin/content-types/news/fields` opens

#### Scenario: Nodes
- **WHEN** the user chooses "Nodes" in the row of the type with the key `news`
- **THEN** `/admin/content-types/news/nodes` opens

### Requirement: Horizontal section menu of a type
The pages for managing an existing content type of the kind `content` — the type edit form (`/admin/content-types/{key}/edit`), the list of fields (`/admin/content-types/{key}/fields`), the field creation and edit forms, the list of nodes (`/admin/content-types/{key}/nodes`) and the node creation and edit forms — SHALL show above the content a horizontal section menu with the items "Settings" (leads to the type edit form), "Fields" (leads to the list of fields of this type) and "Nodes" (leads to the list of nodes of this type). The item of the current section SHALL be visually highlighted and marked as current for assistive technologies; on the field creation and edit pages the current item is "Fields", on the node list, creation and edit pages — "Nodes". The menu SHALL be the same on all these pages, and above it there SHALL be a link back to the list of types. The creation page of a type does NOT show the section menu. The section menus of the page and block sections do NOT have the "Nodes" item.

#### Scenario: Menu on the type edit form
- **WHEN** the user opens `/admin/content-types/news/edit`
- **THEN** they see a menu with the items "Settings", "Fields" and "Nodes", where "Settings" is highlighted as current

#### Scenario: Menu on the list of fields
- **WHEN** the user opens `/admin/content-types/news/fields`
- **THEN** the menu is shown, "Fields" is current, and the link back to the list of types is above the menu

#### Scenario: Menu on the field forms
- **WHEN** the user opens the field creation or edit form of the type `news`
- **THEN** the menu is shown, "Fields" is current

#### Scenario: Menu on the node pages
- **WHEN** the user opens the list, the creation form or the edit form of a node of the type `news`
- **THEN** the menu is shown, "Nodes" is current

#### Scenario: Switching between pages
- **WHEN** the user clicks "Fields" on the edit form of the type `news`, then "Nodes", then "Settings"
- **THEN** they land on `/admin/content-types/news/fields`, then on `/admin/content-types/news/nodes`, then on `/admin/content-types/news/edit`

#### Scenario: Creating a type
- **WHEN** the user opens `/admin/content-types/create`
- **THEN** the section menu is not shown

#### Scenario: Page type
- **WHEN** the user opens `/admin/page-types/home/edit`
- **THEN** there is no "Nodes" item in the section menu
