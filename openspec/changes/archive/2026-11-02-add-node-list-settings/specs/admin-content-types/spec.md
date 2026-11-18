# Spec Delta

## MODIFIED Requirements

### Requirement: Форма редагування типу
The admin SHALL have the page `/admin/content-types/{key}/edit`, accessible only to authenticated users with a confirmed email, with a form pre-filled with the current values of a type of the kind `content`: the name, the slug, the parent type, the description, and also the settings of the list of records (the section «Список записів»). The key SHALL be shown only as read-only with an explanation that it is immutable. The kind is absent from the form. For a non-existent key and for a type of the kind `page` or `block` the page SHALL respond «не знайдено». The form SHALL have a save button and a link «Скасувати».

The section «Список записів» SHALL allow: (1) choosing the columns of the list among the fields of the type that have values in the records, and the service columns «Slug», «Стан», «Активний», «Дата створення», «Дата оновлення», «Дата публікації», «Дата зняття з публікації», and setting their order by drag-and-drop (the column `title` is always present and is not switched off, but its position can be changed; the order is changed also from the keyboard, and there are no «up/down» buttons); (2) choosing the search fields among the text fields of the type; (3) choosing the default sorting — the field and the direction. The options of the sort field SHALL be: the service «Стан», «Активний», «Дата створення», «Дата оновлення», «Дата публікації», «Дата зняття з публікації» and the fields of the type that can be sorted (without the file ones, `youtube` and `term`); slug is not sortable. The initial choice for a type without settings SHALL be «Дата створення» ascending, and for the columns — `title` first, then slug, the state and the publication date, as in the list without settings. The section SHALL be the same for the types of all kinds (`content`, `page`, `block`).

#### Scenario: Opening the form
- **WHEN** the user opens `/admin/content-types/news/edit` for an existing type `content`
- **THEN** they see the form with the current name, slug, parent type and description, the section «Список записів» with the current settings, and the key `news` is read-only only

#### Scenario: Type without list settings
- **WHEN** the user opens the settings of a type that has no `settings.list`
- **THEN** the section has the columns slug, the state and the publication date selected, the search across all text fields, and the sorting «Дата створення» ascending

#### Scenario: Drag-and-drop of columns
- **WHEN** the user drags the column `title` to the third position or moves it from the keyboard
- **THEN** the order in the form changes, and the checkbox of `title` remains marked and disabled for change

#### Scenario: Sorting options
- **WHEN** the type has the fields `price` (number), `image` (file) and `tags` (term)
- **THEN** among the sorting options there are «Стан», «Активний», «Дата створення», «Дата оновлення», «Дата публікації», «Дата зняття з публікації» and `price`, and there are no `image`, `tags` and slug

#### Scenario: Non-existent type
- **WHEN** the user opens the edit form for a non-existent key
- **THEN** the response is «не знайдено»

#### Scenario: Type of another kind
- **WHEN** the user opens the edit form for the key of a type of the kind `page` or `block`
- **THEN** the response is «не знайдено»

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens the edit form or submits it
- **THEN** they are redirected to the login page, the type is not changed

### Requirement: Збереження змін типу
Submitting the edit form SHALL update the name, the slug, the parent type, the description and the settings of the list of records of the type by the same rules as the creation; the uniqueness of the slug SHALL be checked excluding the type itself. The key and the kind SHALL NOT change, even if they are passed in the request. An empty slug clears the value. The parent type SHALL NOT be able to be the type itself or its descendant. The settings of the list of records SHALL be stored in `settings.list` (`visible`, `search`, `sort`) and SHALL update only this key, without changing the other keys of `settings`. The keys in `visible` and `search` SHALL belong to the fields of the type or (only in `visible`) to the key `title` or the service keys `slug`, `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`; the sort field — to a field of the type or a service key from this list, except `slug`; the direction — `asc` or `desc`; unknown keys SHALL be rejected with an error next to the section. The choice of sorting «Дата створення» ascending SHALL not store `sort`, and an empty choice of columns and search — not store the corresponding keys. After success the user SHALL land on `/admin/content-types` with a success message.

#### Scenario: Successful change
- **WHEN** the user changes the name and the slug of an existing type to free valid values and saves
- **THEN** the changes are saved, the key and the kind did not change, the user is redirected to the list with a success message

#### Scenario: Saving one's own slug
- **WHEN** the user saves the form without changing the slug of the type
- **THEN** there is no uniqueness error

#### Scenario: Slug of another type
- **WHEN** the user enters a slug that already belongs to another type
- **THEN** the changes are not saved, an error is shown next to the slug field

#### Scenario: Clearing the slug
- **WHEN** the user erases the slug and saves
- **THEN** the slug of the type is empty

#### Scenario: Key in the request is ignored
- **WHEN** the edit request contains a different `key` or `kind`
- **THEN** the key and the kind of the type remain unchanged

#### Scenario: Cycle in the hierarchy
- **WHEN** the type itself or its descendant is chosen as the parent type
- **THEN** the changes are not saved, an error is shown next to the parent type field

#### Scenario: Saving columns, search and sorting
- **WHEN** the user keeps `title` first, chooses the columns `price`, the state, `is_active`, `created_at`, `unpublished_at` in this order, the search by `title`, the sorting «Дата оновлення» descending, and saves
- **THEN** `settings.list` of the type is `visible: ["title", "price", "status", "is_active", "created_at", "unpublished_at"]`, `search: ["title"]`, `sort: {field: "updated_at", direction: "desc"}`, and the list of records shows these columns and this order

#### Scenario: Position of title is kept
- **WHEN** the user puts `title` in the second position after `price` and saves
- **THEN** `settings.list.visible` starts with `["price", "title"]`

#### Scenario: Default sorting is not stored
- **WHEN** the user leaves the sorting «Дата створення» ascending and saves
- **THEN** there is no key `sort` in `settings.list`

#### Scenario: Unknown field in the list settings
- **WHEN** the request contains in `visible` a key that is not among the fields of the type and the service keys, or `slug` in `sort.field`
- **THEN** the changes are not saved, an error is shown next to the section «Список записів»

#### Scenario: Other keys of settings are kept
- **WHEN** the type has another key in `settings`, and the user saves the form with a changed list
- **THEN** the other key of `settings` remains unchanged

#### Scenario: Types of pages and blocks
- **WHEN** the user saves the settings of the list of records in a type of the kind `page` or `block`
- **THEN** they are saved and take effect in the list of records of the same section by the same rules
