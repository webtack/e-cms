## MODIFIED Requirements

### Requirement: Меню дій рядка поля
Each row of the fields table SHALL have a group of buttons: the main button «Редагувати» and next to it the menu button «⋮». «Редагувати» SHALL open the edit form of this field. The menu «⋮» SHALL have the items «Редагувати» and «Видалити» (in this order; «Видалити» is visually highlighted as a destructive action and separated). For a system field the item «Видалити» SHALL be inactive; for a field that has values it stays active, but the dialog does not allow confirming the deletion (see "Deleting a field"). The main button «Редагувати» SHALL be active for any field, including a system one.

#### Scenario: Composition of the group
- **WHEN** the user sees the row of a regular field
- **THEN** the active button «Редагувати» is visible in it, and next to it the menu button «⋮» with an accessible name

#### Scenario: Edit from the main button
- **WHEN** the user presses «Редагувати» in the row of the field `summary` of the type `news`
- **THEN** `/admin/content-types/news/fields/summary/edit` opens

#### Scenario: Composition of the menu
- **WHEN** the user opens the menu «⋮» in the row of a regular field
- **THEN** they see the active items «Редагувати» and «Видалити»

#### Scenario: System field
- **WHEN** the user sees the row of the field `title` and opens its menu «⋮»
- **THEN** the button «Редагувати» is active, in the menu the item «Видалити» is inactive, and «Редагувати» is active
