# Spec Delta

## MODIFIED Requirements

### Requirement: Меню розділу типу блоку
The pages for editing a block type, the list of fields, the create and edit of a field, and the records of the type SHALL have a horizontal section menu with the items «Налаштування» (`/admin/block-types/{key}/edit`), «Поля» (`/admin/block-types/{key}/fields`) and «Записи» (`/admin/block-types/{key}/nodes`), and a link back to the list of block types. The active item SHALL be highlighted; on the create and edit pages of a field the active one is «Поля», on the pages of records — «Записи». The creation page of a type has no menu.

#### Scenario: Switching sections
- **WHEN** the user on the edit page of a block type selects «Поля»
- **THEN** the list of fields of the type opens, and the item «Поля» is active

#### Scenario: Transition to records
- **WHEN** the user on the edit page of a block type selects «Записи»
- **THEN** `/admin/block-types/{key}/nodes` opens, and the item «Записи» is active

#### Scenario: Link back
- **WHEN** the user presses the link back on the page of fields of a block type
- **THEN** `/admin/block-types` opens

### Requirement: Меню дій рядка типу блоку
Each row of the table of block types SHALL have a group of action buttons: the button «Налаштування» (the edit form) and a menu with the items «Редагувати», «Поля», «Записи» and «Видалити», where «Видалити» is separated as a destructive action. The number of records in the table row SHALL be a link to the list of records of this type. The button «Створити» above the table SHALL lead to the creation form of a block type.

#### Scenario: Transition to fields
- **WHEN** the user selects the item «Поля» in the row menu
- **THEN** `/admin/block-types/{key}/fields` opens

#### Scenario: Transition to records
- **WHEN** the user selects the item «Записи» in the row menu or presses the number of records
- **THEN** `/admin/block-types/{key}/nodes` opens

#### Scenario: Create button
- **WHEN** the user presses «Створити» above the table
- **THEN** `/admin/block-types/create` opens
