## MODIFIED Requirements

### Requirement: Меню розділу типу блоку
The edit pages of a block type, the tab «Відображення» ("Display"), the list of fields, the creation and editing of a field, as well as the records of the type SHALL have a horizontal menu of the section with the items «Налаштування» ("Settings", `/admin/block-types/{key}/edit`), «Відображення» ("Display", `/admin/block-types/{key}/display`), «Поля» ("Fields", `/admin/block-types/{key}/fields`) and «Записи» ("Records", `/admin/block-types/{key}/nodes`), and a link back to the list of block types. The active item SHALL be highlighted; on the pages of creating and editing a field the active item is «Поля», on the pages of records — «Записи», on the tab «Відображення» — «Відображення». On the page of creating a type there is no menu. The tab «Відображення» of a block type contains only the block «Публічне відображення» ("Public display", the choice of the fields of the page) and is saved by its own button «Зберегти» ("Save").

#### Scenario: Switching sections
- **WHEN** the user on the edit page of a block type chooses «Поля»
- **THEN** the list of fields of the type opens, and the item «Поля» is active

#### Scenario: Going to records
- **WHEN** the user on the edit page of a block type chooses «Записи»
- **THEN** `/admin/block-types/{key}/nodes` opens, and the item «Записи» is active

#### Scenario: Going to display
- **WHEN** the user on the edit page of a block type chooses «Відображення»
- **THEN** `/admin/block-types/{key}/display` opens, the item «Відображення» is active, and the block «Публічне відображення» contains no fields of the card, sorting and quantity

#### Scenario: Link back
- **WHEN** the user clicks the link back on the page of fields of a block type
- **THEN** `/admin/block-types` opens
