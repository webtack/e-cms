## MODIFIED Requirements

### Requirement: Row action menu of a page type
Each row of the page types table SHALL have a group of action buttons: a "Settings" button (the edit form) and a menu with the items "Edit", "Display", "SEO", "Fields", "Nodes", and "Delete" (in this order), where "Delete" is separated as a destructive action. Every destination of the section menu of a page type SHALL also be an item of the row menu. The number of records in the table row SHALL be a link to the list of records of this type. The "Create" button above the table SHALL lead to the creation form of a page type.

#### Scenario: Composition of the menu
- **WHEN** the user opens the "⋮" menu in the row of a page type
- **THEN** they see the items "Edit", "Display", "SEO", "Fields", "Nodes", "Delete" in this order

#### Scenario: Navigation to display
- **WHEN** the user selects the "Display" item in the row menu
- **THEN** `/admin/page-types/{key}/display` opens

#### Scenario: Navigation to SEO
- **WHEN** the user selects the "SEO" item in the row menu
- **THEN** `/admin/page-types/{key}/seo` opens

#### Scenario: Navigation to fields
- **WHEN** the user selects the "Fields" item in the row menu
- **THEN** `/admin/page-types/{key}/fields` opens

#### Scenario: Navigation to nodes
- **WHEN** the user selects the "Nodes" item in the row menu or clicks the number of records
- **THEN** `/admin/page-types/{key}/nodes` opens

#### Scenario: Create button
- **WHEN** the user clicks "Create" above the table
- **THEN** `/admin/page-types/create` opens
