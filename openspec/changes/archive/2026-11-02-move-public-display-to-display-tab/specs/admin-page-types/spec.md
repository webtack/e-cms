## MODIFIED Requirements

### Requirement: Меню розділу типу сторінки
The edit pages of a page type, the tab «Відображення» ("Display"), the list of fields, the creation and editing of a field, as well as the records of the type SHALL have a horizontal menu of the section with the items «Налаштування» ("Settings", `/admin/page-types/{key}/edit`), «Відображення» ("Display", `/admin/page-types/{key}/display`), «Поля» ("Fields", `/admin/page-types/{key}/fields`) and «Записи» ("Records", `/admin/page-types/{key}/nodes`), and a link back to the list of page types. The active item SHALL be highlighted; on the pages of creating and editing a field the active item is «Поля», on the pages of records — «Записи», on the tab «Відображення» — «Відображення». On the page of creating a type there is no menu. The tab «Відображення» of a page type contains only the block «Публічне відображення» ("Public display", the choice of the fields of the page) and is saved by its own button «Зберегти» ("Save").

#### Scenario: Switching sections
- **WHEN** the user on the edit page of a page type chooses «Поля»
- **THEN** the list of fields of the type opens, and the item «Поля» is active

#### Scenario: Going to records
- **WHEN** the user on the edit page of a page type chooses «Записи»
- **THEN** `/admin/page-types/{key}/nodes` opens, and the item «Записи» is active

#### Scenario: Going to display
- **WHEN** the user on the edit page of a page type chooses «Відображення»
- **THEN** `/admin/page-types/{key}/display` opens, the item «Відображення» is active, and the block «Публічне відображення» contains no fields of the card, sorting and quantity

#### Scenario: Link back
- **WHEN** the user clicks the link back on the page of fields of a page type
- **THEN** `/admin/page-types` opens
