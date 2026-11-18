# Spec Delta

## MODIFIED Requirements

### Requirement: Меню розділу типу сторінки
The edit pages of a page type, the tabs «Відображення» ("Display") and «SEO», the list of fields, the creation and editing of a field, and the records of the type SHALL have a horizontal section menu with the items «Налаштування» ("Settings", `/admin/page-types/{key}/edit`), «Відображення» (`/admin/page-types/{key}/display`), «SEO» (`/admin/page-types/{key}/seo`), «Поля» ("Fields", `/admin/page-types/{key}/fields`) and «Записи» ("Records", `/admin/page-types/{key}/nodes`), and a link back to the list of page types. The active item SHALL be highlighted; on the pages of creating and editing a field the active item is «Поля», on the pages of records — «Записи», on the tab «Відображення» — «Відображення», on the tab «SEO» — «SEO». On the page of creating a type there is no menu. The tab «Відображення» of a page type contains only the block «Публічне відображення» (the choice of the fields of the page) and is saved by its own button «Зберегти». The tab «SEO» of a page type works the same way as for a content type (the values `meta_title`, `meta_description`, `meta_image` of the type itself and the block «Мета-дані», see `admin-content-types`), at the addresses `/admin/page-types/{key}/seo`, `PUT /admin/page-types/{key}/values`; the values and the block are saved in one request by the single button.

#### Scenario: Switching the sections
- **WHEN** the user on the edit page of a page type chooses «Поля»
- **THEN** the list of fields of the type opens, and the item «Поля» is active

#### Scenario: Going to the records
- **WHEN** the user on the edit page of a page type chooses «Записи»
- **THEN** `/admin/page-types/{key}/nodes` opens, and the item «Записи» is active

#### Scenario: Going to the display tab
- **WHEN** the user on the edit page of a page type chooses «Відображення»
- **THEN** `/admin/page-types/{key}/display` opens, the item «Відображення» is active, and the block «Публічне відображення» contains no fields of the card, sorting or the number

#### Scenario: Going to SEO
- **WHEN** the user on the edit page of a page type chooses «SEO»
- **THEN** `/admin/page-types/{key}/seo` opens, the item «SEO» is active, and the form of the values `meta_*` of the type and the block «Мета-дані» are visible

#### Scenario: Saving the SEO of a page type
- **WHEN** the user enters `meta_description` on the tab «SEO» of a page type and saves
- **THEN** the value is saved for the type, the user stays on the tab, and the values `meta_*` of the records of this type are not changed

#### Scenario: Link back
- **WHEN** the user presses the link back on the page of the fields of a page type
- **THEN** `/admin/page-types` opens
