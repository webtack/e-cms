## ADDED Requirements

### Requirement: Limiting the width of the slug column
The slug column of the types table of the content types section SHALL have a limited width: a long slug SHALL be cut into one line with an ellipsis, without stretching the table or pushing the other columns. The full slug SHALL be available in a hint.

#### Scenario: Long slug
- **WHEN** a type has a slug longer than the width of the column
- **THEN** the slug takes one line and ends with an ellipsis, and the other columns remain in place

#### Scenario: Full slug in the hint
- **WHEN** the user hovers over a cut slug
- **THEN** they see the full slug

## MODIFIED Requirements

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
