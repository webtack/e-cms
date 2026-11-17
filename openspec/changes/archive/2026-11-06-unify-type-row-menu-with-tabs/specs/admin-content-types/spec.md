## MODIFIED Requirements

### Requirement: Row action menu
Each row SHALL have a group of buttons: the main button "Settings" and next to it the "⋮" menu button. "Settings" SHALL open the edit form of this type. The "⋮" menu in the content types section SHALL have the items "Edit", "Display", "SEO", "Fields", "Nodes", and "Delete" (in this order; "Delete" is visually highlighted as a destructive action and separated from the others). "Edit" SHALL open the edit form of this type, "Display" — the "Display" tab of this type, "SEO" — the "SEO" tab of this type, "Fields" — the fields page of this type, "Nodes" — the list of nodes of this type, "Delete" — start the deletion with confirmation. Every destination of the section menu of an existing type (see "Horizontal menu of the type section") that is available for the kind SHALL also be an item of the row menu, and the other way round, so the row menu and the section menu never differ in their destinations. The page and block sections have the same row menu, except that the block section has no "SEO" item (see `admin-page-types` and `admin-block-types`).

#### Scenario: Composition of the group
- **WHEN** the user sees the row of a type
- **THEN** the active "Settings" button and next to it the "⋮" menu button with an accessible name are visible in it

#### Scenario: Settings
- **WHEN** the user clicks "Settings" in the row of the type with the key `news`
- **THEN** `/admin/content-types/news/edit` opens

#### Scenario: Composition of the menu
- **WHEN** the user opens the "⋮" menu in the row of a type in the content types section
- **THEN** they see the active items "Edit", "Display", "SEO", "Fields", "Nodes", "Delete" in this order with translated labels

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
