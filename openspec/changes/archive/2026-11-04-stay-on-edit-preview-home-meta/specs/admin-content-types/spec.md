# Spec Delta

## MODIFIED Requirements

### Requirement: Збереження змін типу
Submitting the edit form SHALL update the name, the slug, the parent type, the description and the settings of the list of records of the type by the same rules as the creation; the uniqueness of the slug SHALL be checked excluding the type itself. The key and the kind SHALL NOT change, even if they are passed in the request. An empty slug clears the value. A type itself or its descendant SHALL not be chosen as the parent. The settings of the list of records SHALL be saved in `settings.list` (`visible`, `search`, `sort`) and update only this key, without changing the other keys of `settings`. The keys in `visible` and `search` SHALL belong to the fields of the type or (only in `visible`) to the key `title` or the service keys `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`; the sort field — to a field of the type or a service key from this list, except `slug`; the direction — `asc` or `desc`; unknown keys SHALL be rejected with an error next to the section. The choice of sorting «Дата створення» ("Creation date") ascending SHALL not save `sort`, and an empty choice of columns and search — does not save the corresponding keys. The edit form SHALL NOT change `settings.public`: this key is saved only by the tab «Відображення», and an edit request with `settings.public` SHALL be rejected with a validation error. After success the user SHALL stay on the edit form of the type with a success message.

#### Scenario: Successful change
- **WHEN** the user changes the name and the slug of an existing type to free permitted values and saves
- **THEN** the changes are saved, the key and the kind did not change, and the user stayed on the edit form of the type with a success message

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
- **WHEN** the edit request passes a different `key` or `kind`
- **THEN** the key and the kind of the type remain unchanged

#### Scenario: Cycle in the hierarchy
- **WHEN** the type itself or its descendant is chosen as the parent
- **THEN** the changes are not saved, an error is shown next to the field of the parent type

#### Scenario: Saving columns, search and sorting
- **WHEN** the user leaves `title` first, chooses the columns `price`, state, `is_active`, `created_at`, `unpublished_at` in this order, the search by `title`, the sorting «Дата оновлення» ("Update date") descending, and saves
- **THEN** `settings.list` of the type is `visible: ["title", "price", "status", "is_active", "created_at", "unpublished_at"]`, `search: ["title"]`, `sort: {field: "updated_at", direction: "desc"}`, and the list of records shows these columns and this order

#### Scenario: Position of title is kept
- **WHEN** the user puts `title` on the second position after `price` and saves
- **THEN** `settings.list.visible` starts with `["price", "title"]`

#### Scenario: Default sorting is not saved
- **WHEN** the user leaves the sorting «Дата створення» ascending and saves
- **THEN** there is no key `sort` in `settings.list`

#### Scenario: Unknown field in the settings of the list
- **WHEN** the request contains in `visible` a key that is absent among the fields of the type and the service keys, or `slug` as `sort.field`
- **THEN** the changes are not saved, an error is shown next to the section «Список записів» ("List of records")

#### Scenario: Other keys of settings are kept
- **WHEN** the type has another key in `settings` (in particular `public`), and the user saves the form with the changed list
- **THEN** the other key of `settings` remains unchanged

#### Scenario: Public settings in the edit form
- **WHEN** the edit request of the type contains `settings.public`
- **THEN** the changes are not saved, the response contains a validation error, `settings.public` of the type is not changed

#### Scenario: Page and block types
- **WHEN** the user saves the settings of the list of records in a type of kind `page` or `block`
- **THEN** they are saved and take effect in the list of records of the same section by the same rules

### Requirement: Секція «Публічне відображення» на вкладці «Відображення»
The tab «Відображення» of the type SHALL contain the block «Публічне відображення» for all kinds. For `block` it contains only the choice of the fields of the page; for `page` — the choice of the fields of the page and the sub-block «Мета-дані» ("Meta data"); for `content` — also the fields of the card, the sorting (field + direction), the number of records per page, the switch of the paginator and the sub-block «Мета-дані». Only added fields (`is_system = false`) are offered for the choice; `title`, the meta fields and service attributes are not offered, because they are always available to the template. The sub-block «Мета-дані» SHALL have three optional choices, each — with one added field or «Не вибрано» ("Not selected"): «Заголовок» ("Heading") (fields of the type «рядок» ("string")), «Опис» ("Description") (fields of the types «текст» ("text") and «форматований текст» ("formatted text")) and «Зображення» ("Image") (fields of the types «зображення» ("image") and «відео YouTube»); the chosen key SHALL be saved in `settings.public.meta` (`title`, `description`, `image`), and an unselected one — is not saved. The values of the fields are used by the public site as the metadata of the page (see the requirement of the public site «Мета-дані за замовчуванням» — "Default metadata"). The block SHALL show the saved state with the default values and SHALL be saved together with the values of the section by the single button «Зберегти» of the tab, updating only the key `public` of the settings. The settings form of the type does NOT contain this block.

#### Scenario: Default values
- **WHEN** the tab «Відображення» of the type `content` without public settings is opened
- **THEN** the switch «Усі додані поля» ("All added fields") for the page is on, the card has no added fields, the sorting is «Дата публікації» ("Publication date") descending, the number is «0» (all)

#### Scenario: Explicit choice of the fields of the page
- **WHEN** the administrator turns off «Усі додані поля», marks `price` and saves
- **THEN** after reopening only `price` is marked, and a new field of the type does not appear among the marked ones

#### Scenario: Returning to «all»
- **WHEN** the administrator turns on «Усі додані поля» and saves
- **THEN** the key `page_fields` is not saved, and a new field automatically enters the page

#### Scenario: Other keys are not overwritten
- **WHEN** the tab is saved with changed public settings
- **THEN** `settings.list` and the other keys of `settings` remain unchanged

#### Scenario: Choice of meta fields
- **WHEN** the administrator of a type `content` chooses the field `headline` for «Заголовок», `summary` for «Опис», `cover` for «Зображення» and saves
- **THEN** after reopening the same fields are selected, and `settings.public.meta` has the keys `title`, `description`, `image`

#### Scenario: Permitted fields for each choice
- **WHEN** the administrator opens the choice «Заголовок», «Опис» or «Зображення»
- **THEN** «Заголовок» has only fields of the type «рядок», «Опис» — «текст» and «форматований текст», «Зображення» — «зображення» and «відео YouTube»

#### Scenario: Invalid field
- **WHEN** the request saves `settings.public.meta.image` with the key of a field of the type «рядок» or of a non-existent field
- **THEN** the saving is rejected with an error next to the block, nothing is changed

#### Scenario: Meta data without a choice
- **WHEN** no meta field is chosen
- **THEN** the key `meta` is not saved in `settings.public`

#### Scenario: Type of block
- **WHEN** the administrator opens the tab «Відображення» of a type of kind `block`
- **THEN** there is no sub-block «Мета-дані», and a request with `settings.public.meta` for such a type is rejected

#### Scenario: Settings form without the public block
- **WHEN** the user opens the settings form of a type of any kind
- **THEN** there is no block «Публічне відображення» on it
