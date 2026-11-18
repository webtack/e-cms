# Spec Delta

## ADDED Requirements

### Requirement: Horizontal section menu of a content type
The pages for managing an existing content type of the kind `content` — the type edit form (`/admin/content-types/{key}/edit`), the list of fields (`/admin/content-types/{key}/fields`), and the field creation and edit forms — SHALL show above the content a horizontal section menu with the items "Settings" (leads to the type edit form) and "Fields" (leads to the list of fields of this type). The item of the current section SHALL be visually highlighted and marked as current for assistive technologies; on the field creation and edit pages the current item is "Fields". The menu SHALL be the same on all these pages, and above it there SHALL be a link back to the list of types. The creation page of a type does NOT show the menu.

#### Scenario: Menu on the type edit form
- **WHEN** the user opens `/admin/content-types/news/edit`
- **THEN** they see a menu with the items "Settings" and "Fields", where "Settings" is highlighted as current

#### Scenario: Menu on the list of fields
- **WHEN** the user opens `/admin/content-types/news/fields`
- **THEN** the menu is shown, "Fields" is current, and the link back to the list of types is above the menu

#### Scenario: Menu on the field forms
- **WHEN** the user opens the field creation or edit form of the type `news`
- **THEN** the menu is shown, "Fields" is current

#### Scenario: Switching between pages
- **WHEN** the user clicks "Fields" on the edit form of the type `news`, and then "Settings"
- **THEN** they land on `/admin/content-types/news/fields`, and then on `/admin/content-types/news/edit`

#### Scenario: Creating a type
- **WHEN** the user opens `/admin/content-types/create`
- **THEN** the section menu is not shown
