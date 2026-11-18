## MODIFIED Requirements

### Requirement: Меню дій рядка
Each row SHALL have a group of buttons: the main button «Налаштування» and next to it the menu button «⋮». «Налаштування» SHALL open the edit form of this type. The menu «⋮» in the section of content types SHALL have the items «Редагувати», «Поля», «Записи» and «Видалити» (in this order; «Видалити» is visually highlighted as a destructive action and separated from the others). «Редагувати» SHALL open the edit form of this type, «Поля» — the page of fields of this type, «Записи» — the list of records of this type, «Видалити» — start the deletion with confirmation. The sections of pages and blocks have no item «Записи».

#### Scenario: Composition of the group
- **WHEN** the user sees a row of a type
- **THEN** the active button «Налаштування» is visible in it, and next to it the menu button «⋮» with an accessible name

#### Scenario: Settings
- **WHEN** the user presses «Налаштування» in the row of a type with the key `news`
- **THEN** `/admin/content-types/news/edit` opens

#### Scenario: Composition of the menu
- **WHEN** the user opens the menu «⋮» in the row of a type in the section of content types
- **THEN** they see the active items «Редагувати», «Поля», «Записи», «Видалити» with translated labels

#### Scenario: Edit
- **WHEN** the user selects «Редагувати» in the row of a type with the key `news`
- **THEN** `/admin/content-types/news/edit` opens

#### Scenario: Items are inactive
- **WHEN** the user selects «Поля» in the row of a type with the key `news`
- **THEN** `/admin/content-types/news/fields` opens

#### Scenario: Records
- **WHEN** the user selects «Записи» in the row of a type with the key `news`
- **THEN** `/admin/content-types/news/nodes` opens

### Requirement: Горизонтальне меню розділу типу
The pages for managing an existing content type of the kind `content` — the edit form of the type (`/admin/content-types/{key}/edit`), the list of fields (`/admin/content-types/{key}/fields`), the create and edit forms of a field, the list of records (`/admin/content-types/{key}/nodes`) and the create and edit forms of a record — SHALL show above the content a horizontal section menu with the items «Налаштування» (leads to the edit form of the type), «Поля» (leads to the list of fields of this type) and «Записи» (leads to the list of records of this type). The item of the current section SHALL be visually highlighted and marked as current for assistive technologies; on the create and edit pages of a field the current one SHALL be «Поля», on the pages of the list, create and edit of a record — «Записи». The menu SHALL be the same on all these pages, and above it SHALL be a link back to the list of types. The creation page of a type does NOT show the menu. The section menus of pages and blocks have NO item «Записи».

#### Scenario: Menu on the edit form of a type
- **WHEN** the user opens `/admin/content-types/news/edit`
- **THEN** they see the menu with the items «Налаштування», «Поля» and «Записи», where «Налаштування» is highlighted as current

#### Scenario: Menu on the list of fields
- **WHEN** the user opens `/admin/content-types/news/fields`
- **THEN** the menu is shown, «Поля» is current, and the link back to the list of types is placed above the menu

#### Scenario: Menu on the forms of a field
- **WHEN** the user opens the create or edit form of a field of the type `news`
- **THEN** the menu is shown, «Поля» is current

#### Scenario: Menu on the pages of records
- **WHEN** the user opens the list, the create form or the edit form of a record of the type `news`
- **THEN** the menu is shown, «Записи» is current

#### Scenario: Switching between pages
- **WHEN** the user presses «Поля» on the edit form of the type `news`, then «Записи», then «Налаштування»
- **THEN** they land on `/admin/content-types/news/fields`, then on `/admin/content-types/news/nodes`, then on `/admin/content-types/news/edit`

#### Scenario: Creating a type
- **WHEN** the user opens `/admin/content-types/create`
- **THEN** the section menu is not shown

#### Scenario: Page type
- **WHEN** the user opens `/admin/page-types/home/edit`
- **THEN** the item «Записи» is not in the section menu
