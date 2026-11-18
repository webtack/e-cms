## RENAMED Requirements

- FROM: `### Requirement: Панелі «Розділ» і «SEO» на сторінці налаштувань типу контенту`
- TO: `### Requirement: Вкладки «Відображення» і «SEO» типу`

- FROM: `### Requirement: Секція «Публічне відображення» у формі типу`
- TO: `### Requirement: Секція «Публічне відображення» на вкладці «Відображення»`

## MODIFIED Requirements

### Requirement: Вкладки «Відображення» і «SEO» типу
The menu of the section of a type SHALL contain the tab «Відображення» ("Display", `/admin/{section}/{key}/display`, where `{section}` is `content-types`, `page-types` or `block-types`) for the types of all kinds, between «Налаштування» ("Settings") and «Поля» ("Fields"). For a type `kind = content` the menu SHALL contain, besides «Налаштування», «Відображення», «Поля» and «Записи» ("Records"), the tab «SEO» (`/admin/content-types/{key}/seo`, the fields `meta_title`, `meta_description`, `meta_image`), which follows immediately after «Відображення». The tab «Відображення» of a type `content` SHALL have two blocks: the values of the section (the fields `section_title`, `section_description`, `section_cover`) and «Публічне відображення»; the tab «Відображення» of the types `page` and `block` SHALL have only the block «Публічне відображення» (they have no own values of the section). The tab «SEO» SHALL be a separate page with its own form and save button, which saves only the SEO fields. The settings page of a type (`/admin/{section}/{key}/edit`) SHALL contain only the main form of the type and the section «Список записів» ("List of records"), without panels of values and without the block «Публічне відображення». The labels, the hints (the description of the field) and the length limits SHALL be taken from the definitions of the fields. The input element SHALL correspond to the type of the field (short text, multi-line text, formatted text, a choice of an image from the media library). All values are optional; an empty value SHALL clear the saved one. The tabs of values SHALL work only with the base language: there is no language switch, the values are saved as base ones. Each tab SHALL have the same sticky action bar at the bottom of the window with «Скасувати» ("Cancel") and «Зберегти» ("Save") as the other forms of the admin; «Зберегти» submits the form of the tab. The tab «Відображення» SHALL have **one** shared button «Зберегти», which in one request saves both the values of the section (only for `content`) and `settings.public`; the saving SHALL be atomic: if any part fails validation, nothing is saved, and the errors are shown next to the corresponding fields (the values of the section — next to the fields, the public settings — in the block «Публічне відображення»). Saving the tab «Відображення» SHALL not change the values of the tab «SEO», and saving «SEO» — the values of the section and `settings.public`. The address `/admin/content-types/{key}/section` SHALL respond with 404. The address `/seo` for the types `page` and `block` SHALL respond with 404.

#### Scenario: Menu of a content type
- **WHEN** the user opens any page of the type `news` of the kind `content`
- **THEN** the menu of the type contains the tabs «Налаштування», «Відображення», «SEO», «Поля», «Записи», and the current one is active

#### Scenario: Menu of a page or block type
- **WHEN** the user opens any page of a type of the kind `page` or `block`
- **THEN** the menu contains «Налаштування», «Відображення», «Поля», «Записи», there is no tab «SEO»

#### Scenario: Opening the settings page
- **WHEN** the user opens the settings of the type `news` of the kind `content`
- **THEN** the page contains the main form of the type and the section «Список записів»; there are no panels of values and no block «Публічне відображення» on it

#### Scenario: Opening the tab «Розділ» (now «Відображення»)
- **WHEN** the user opens the tab «Відображення» (formerly «Розділ») of the type `news` of the kind `content`
- **THEN** they see the block of values of the section with the current values of the base language, the block «Публічне відображення» and one action bar, and there are no SEO fields

#### Scenario: Opening the tab «Відображення» of a page type
- **WHEN** the user opens `/admin/page-types/home/display`
- **THEN** they see only the block «Публічне відображення» (the choice of the fields of the page) and the action bar

#### Scenario: Saving values of the section
- **WHEN** the user fills in `section_title` «Новини», chooses `section_cover` from the media library and saves on the tab «Відображення»
- **THEN** the values are saved for the type, after reloading the page they are shown

#### Scenario: Shared saving of the values of the section and the public settings
- **WHEN** the user changes `section_title` and the choice of the fields of the page and presses the single button «Зберегти»
- **THEN** both the values of the section and `settings.public` are saved, after reloading both are shown

#### Scenario: An error in one part does not save the other
- **WHEN** the user changes `section_title` and enters a negative number of records per page
- **THEN** the saving is rejected, an error is shown next to the quantity field, and `section_title` remains the previous one

#### Scenario: Invalid value of the section
- **WHEN** the value of `section_title` exceeds the permitted length
- **THEN** the saving is rejected, an error is shown next to the field, the value and `settings.public` remain the previous ones

#### Scenario: Sticky action bar on the tabs
- **WHEN** the user opens the tab «Відображення» or «SEO» in a window 400 px high (at 1280 and 390 px wide)
- **THEN** the panel with «Скасувати» and «Зберегти» is fully visible at the bottom of the window

#### Scenario: Saving SEO does not touch the section
- **WHEN** the user changes `meta_title` on the tab «SEO» and saves
- **THEN** the values `section_*` and `settings.public` of the type remain unchanged

#### Scenario: Clearing a value
- **WHEN** the user clears `meta_description` on the tab «SEO» and saves
- **THEN** the saved value is deleted

#### Scenario: Invalid value
- **WHEN** the value of `meta_title` exceeds the permitted length
- **THEN** the saving is rejected, an error is shown next to the field, the values remain the previous ones

#### Scenario: Old address of the tab
- **WHEN** the user opens `/admin/content-types/news/section`
- **THEN** the response is «не знайдено» ("not found")

#### Scenario: Tab of a page or block type
- **WHEN** the user opens the settings of a type of the kind `page` or `block`
- **THEN** there is no tab «SEO» in the menu, and the address `/seo` of these kinds responds with 404; the tab «Відображення» is present

### Requirement: Секція «Публічне відображення» на вкладці «Відображення»
The tab «Відображення» of a type SHALL contain the block «Публічне відображення» for all kinds. For `page` and `block` it contains only the choice of the fields of the page; for `content` — also the fields of the card, sorting (the field + the direction), the number of records per page and the switch of the paginator. For the choice only the added fields are offered (`is_system = false`); `title`, the metafields and the service attributes are not offered, because they are always available to the template. The block SHALL display the saved state with the default values and SHALL be saved together with the values of the section by the single button «Зберегти» of the tab, updating only the key `public` of the settings. The settings form of the type does NOT contain this block.

#### Scenario: Default values
- **WHEN** the tab «Відображення» of a type `content` without public settings is opened
- **THEN** the switch «Усі додані поля» («All added fields») for the page is enabled, the card has no added fields, the sorting is «Дата публікації» («Publication date») in descending order, the quantity is «0» (all)

#### Scenario: Explicit choice of the fields of the page
- **WHEN** the administrator turns off «Усі додані поля», marks `price` and saves
- **THEN** after reopening only `price` is marked, and a new field of the type does not appear among the marked ones

#### Scenario: Return to «all»
- **WHEN** the administrator turns on «Усі додані поля» and saves
- **THEN** the key `page_fields` is not saved, and a new field automatically enters the page

#### Scenario: Other keys are not overwritten
- **WHEN** the tab is saved with changed public settings
- **THEN** `settings.list` and the other keys of `settings` remain unchanged

#### Scenario: Settings form without the public block
- **WHEN** the user opens the settings form of a type of any kind
- **THEN** there is no block «Публічне відображення» on it

### Requirement: Збереження змін типу
Submitting the form of editing SHALL update the name, the slug, the parent type, the description and the settings of the list of records of the type by the same rules as the creation; the uniqueness of the slug SHALL be checked excluding the type itself. The key and the kind SHALL NOT change, even if they are passed in the request. An empty slug clears the value. The type itself or its descendant SHALL NOT be selectable as the parent. The settings of the list of records SHALL be saved in `settings.list` (`visible`, `search`, `sort`) and update only this key, without changing the other keys of `settings`. The keys in `visible` and `search` SHALL belong to the fields of the type or (only in `visible`) to the key `title` or to the service keys `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`; the sorting field — to a field of the type or a service key from this list, except `slug`; the direction — `asc` or `desc`; unknown keys SHALL be rejected with an error next to the section. The choice of the sorting «Дата створення» («Creation date») in ascending order SHALL not save `sort`, and an empty choice of columns and search — shall not save the corresponding keys. The edit form SHALL NOT change `settings.public`: this key is saved only by the tab «Відображення», and an edit request with `settings.public` SHALL be rejected with a validation error. After success the user SHALL land on `/admin/content-types` with a success message.

#### Scenario: Successful change
- **WHEN** the user changes the name and the slug of an existing type to free valid values and saves
- **THEN** the changes are saved, the key and the kind did not change, the user is redirected to the list with a success message

#### Scenario: Saving one's own slug
- **WHEN** the user saves the form without changing the slug of the type
- **THEN** there is no uniqueness error

#### Scenario: Slug of another type
- **WHEN** the user enters a slug that another type already has
- **THEN** the changes are not saved, an error is shown next to the slug field

#### Scenario: Clearing the slug
- **WHEN** the user erases the slug and saves
- **THEN** the slug of the type is empty

#### Scenario: Key in the request is ignored
- **WHEN** another `key` or `kind` is passed in the edit request
- **THEN** the key and the kind of the type remain unchanged

#### Scenario: Cycle in the hierarchy
- **WHEN** the type itself or its descendant is chosen as the parent
- **THEN** the changes are not saved, an error is shown next to the field of the parent type

#### Scenario: Saving columns, search and sorting
- **WHEN** the user leaves `title` first and chooses the columns `price`, state, `is_active`, `created_at`, `unpublished_at` in this order, the search by `title`, the sorting «Дата оновлення» («Update date») in descending order and saves
- **THEN** `settings.list` of the type is `visible: ["title", "price", "status", "is_active", "created_at", "unpublished_at"]`, `search: ["title"]`, `sort: {field: "updated_at", direction: "desc"}`, and the list of records shows these columns and this order

#### Scenario: Position of title is kept
- **WHEN** the user puts `title` on the second position after `price` and saves
- **THEN** `settings.list.visible` begins with `["price", "title"]`

#### Scenario: Default sorting is not saved
- **WHEN** the user leaves the sorting «Дата створення» in ascending order and saves
- **THEN** there is no key `sort` in `settings.list`

#### Scenario: Unknown field in the settings of the list
- **WHEN** the request contains in `visible` a key that is not among the fields of the type and the service keys, or in `sort.field` — `slug`
- **THEN** the changes are not saved, an error is shown next to the section «Список записів»

#### Scenario: Other keys of settings are kept
- **WHEN** the type has another key in `settings` (in particular `public`), and the user saves the form with the changed list
- **THEN** the other key of `settings` remains unchanged

#### Scenario: Public settings in the edit form
- **WHEN** the edit request of the type contains `settings.public`
- **THEN** the changes are not saved, the response contains a validation error, `settings.public` of the type is not changed

#### Scenario: Types of pages and blocks
- **WHEN** the user saves the settings of the list of records in a type of the kind `page` or `block`
- **THEN** they are saved and take effect in the list of records of the same section by the same rules
