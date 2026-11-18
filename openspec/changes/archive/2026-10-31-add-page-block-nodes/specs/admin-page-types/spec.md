# Spec Delta

## MODIFIED Requirements

### Requirement: Меню розділу типу сторінки
The pages for editing a page type, the list of fields, the create and edit of a field, and the records of the type SHALL have a horizontal section menu with the items «Налаштування» (`/admin/page-types/{key}/edit`), «Поля» (`/admin/page-types/{key}/fields`) and «Записи» (`/admin/page-types/{key}/nodes`), and a link back to the list of page types. The active item SHALL be highlighted; on the create and edit pages of a field the active one is «Поля», on the pages of records — «Записи». The creation page of a type has no menu.

#### Scenario: Switching sections
- **WHEN** the user on the edit page of a page type selects «Поля»
- **THEN** the list of fields of the type opens, and the item «Поля» is active

#### Scenario: Transition to records
- **WHEN** the user on the edit page of a page type selects «Записи»
- **THEN** `/admin/page-types/{key}/nodes` opens, and the item «Записи» is active

#### Scenario: Link back
- **WHEN** the user presses the link back on the page of fields of a page type
- **THEN** `/admin/page-types` opens

### Requirement: Меню дій рядка типу сторінки
Each row of the table of page types SHALL have a group of action buttons: the button «Налаштування» (the edit form) and a menu with the items «Редагувати», «Поля», «Записи» and «Видалити», where «Видалити» is separated as a destructive action. The number of records in the table row SHALL be a link to the list of records of this type. The button «Створити» above the table SHALL lead to the creation form of a page type.

#### Scenario: Transition to fields
- **WHEN** the user selects the item «Поля» in the row menu
- **THEN** `/admin/page-types/{key}/fields` opens

#### Scenario: Transition to records
- **WHEN** the user selects the item «Записи» in the row menu or presses the number of records
- **THEN** `/admin/page-types/{key}/nodes` opens

#### Scenario: Create button
- **WHEN** the user presses «Створити» above the table
- **THEN** `/admin/page-types/create` opens
