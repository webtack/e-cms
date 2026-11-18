## MODIFIED Requirements

### Requirement: Меню дій рядка
Each row SHALL have a group of buttons: the main button «Налаштування» and next to it the menu button «⋮». «Налаштування» SHALL open the edit form of this type. The menu «⋮» SHALL have the items «Редагувати», «Поля» and «Видалити» (in this order; «Видалити» is visually highlighted as a destructive action and separated from the others). «Редагувати» SHALL open the edit form of this type, «Поля» — the page of fields of this type, «Видалити» — start the deletion with confirmation.

#### Scenario: Composition of the group
- **WHEN** the user sees the row of a type
- **THEN** the active button «Налаштування» is visible in it, and next to it the menu button «⋮» with an accessible name

#### Scenario: Settings
- **WHEN** the user presses «Налаштування» in the row of a type with the key `news`
- **THEN** `/admin/content-types/news/edit` opens

#### Scenario: Composition of the menu
- **WHEN** the user opens the menu «⋮» in the row of a type
- **THEN** they see the active items «Редагувати», «Поля», «Видалити» with translated labels

#### Scenario: Edit
- **WHEN** the user selects «Редагувати» in the row of a type with the key `news`
- **THEN** `/admin/content-types/news/edit` opens

#### Scenario: Items are inactive
- **WHEN** the user selects «Поля» in the row of a type with the key `news`
- **THEN** `/admin/content-types/news/fields` opens
