# Spec Delta

## MODIFIED Requirements

### Requirement: Збереження змін запису
Submitting the edit form SHALL replace the values of all fields of the type in the language `default` with the entered ones (the order of the elements of a multiple field is kept as in the form) (a cleared field SHALL lose its value) and SHALL allow changing the slug of the record to a free permitted value, as well as `is_active`, `published_at` and `unpublished_at`; `uuid`, the type of the record and the author SHALL remain unchanged (a submitted `user_id` SHALL be ignored), and a cleared publication date SHALL give a validation error. A cleared unpublishing date SHALL remove it (the record again has no unpublishing date), and a set one SHALL be later than the publication date. Each successful saving SHALL update the `updated_at` of the record, even if only the values of the fields changed. An unchanged slug SHALL not be considered a duplicate of itself. After success the user SHALL stay on the edit form of the record with a success message. Errors SHALL be shown the same way as at creation. The values of other languages SHALL remain unchanged.

#### Scenario: Changing a value
- **WHEN** the user changes the `title` of the record and saves
- **THEN** the user stayed on the edit form, the new `title` is shown in the form and in the list of records, the slug remained the same

#### Scenario: Clearing an optional field
- **WHEN** the user clears the optional field `summary` and saves
- **THEN** the value of `summary` of the record is absent

#### Scenario: Changing the slug
- **WHEN** the user changes the slug of the record to the free `about-us`
- **THEN** the record still opens at the same edit address (by `uuid`), its `uuid` did not change, and a form of this record opened in another tab is saved without a 404 error

#### Scenario: Saving without changing the slug
- **WHEN** the user saves the form without changing the slug
- **THEN** the saving succeeds, there is no uniqueness error

#### Scenario: Slug of another record
- **WHEN** the user enters a slug that another record has
- **THEN** the changes are not saved, an error is shown next to the slug field

#### Scenario: Translations
- **WHEN** the record has a translation of `title` in `en`, and the user changes `title` in the form
- **THEN** only the value `default` changes, the translation `en` remains

#### Scenario: Disabling a record
- **WHEN** the user turns off the switch «Показувати на сайті» ("Show on the site") and saves
- **THEN** the record has `is_active = false`, its publication and unpublishing dates did not change, and in the table the state is «Вимкнено» ("Disabled")

#### Scenario: Changing the publication date
- **WHEN** the user changes the publication date of the record and saves
- **THEN** after reopening the edit form shows the new date

#### Scenario: Cleared publication date
- **WHEN** the user clears the field of the publication date and saves
- **THEN** the changes are not saved, an error is shown next to the date field

#### Scenario: Setting an unpublishing date
- **WHEN** the user sets an unpublishing date later than the publication date and saves
- **THEN** after reopening the edit form shows this date

#### Scenario: Ending the display
- **WHEN** the user sets an unpublishing date in the past (later than the publication date) for an active record and saves
- **THEN** in the table the state of the record is «Завершено» ("Finished")

#### Scenario: Clearing the unpublishing date
- **WHEN** the user clears the field of the unpublishing date and saves
- **THEN** the record has no unpublishing date, and the form after reopening shows an empty field

#### Scenario: Unpublishing date not later than the publication date
- **WHEN** the user sets an unpublishing date equal to or earlier than the publication date and saves
- **THEN** the changes are not saved, an error is shown next to the unpublishing date field

#### Scenario: Author is unchanged
- **WHEN** the update request contains the `user_id` of another user
- **THEN** the author of the record remains the previous one

#### Scenario: Updating the time of change
- **WHEN** the user changes only the value of the field `title` and saves
- **THEN** `updated_at` of the record is updated

### Requirement: Створення запису з таблиці
Above the table of records there SHALL be an active button «Створити» ("Create") in the toolbar, which opens the form of creating a record of this type. Each row SHALL have a group of buttons: the main button «Редагувати» ("Edit") and next to it the menu button «⋮» with the items «Редагувати», «Перегляд» ("Preview") (only for records that have a public address) and «Видалити» ("Delete") (in this order; «Видалити» is visually marked as a destructive action and separated). Both «Редагувати» SHALL open the edit form of this record. The item «Перегляд» SHALL be a link to the public address of the record (the same address as the button «Перегляд» of the edit form: `/{slug}` for a page, `/{slug of the type}/{slug of the record}` for a record `content`), which opens in a new tab; for a record of type `block` and for a record of type `content` without a public slug there SHALL be NO item.

#### Scenario: Going to the creation form
- **WHEN** the user presses «Створити» on the page of records of the type `news`
- **THEN** `/admin/content-types/news/nodes/create` opens

#### Scenario: Edit from a row
- **WHEN** the user presses «Редагувати» in the row of a record with `uuid` `0190a1b2-…` of the type `news`
- **THEN** `/admin/content-types/news/nodes/0190a1b2-…/edit` opens

#### Scenario: Contents of the row menu
- **WHEN** the user opens the menu «⋮» in the row of a record
- **THEN** they see the items «Редагувати», «Перегляд» and «Видалити» with translated labels

#### Scenario: Preview of a page from a row
- **WHEN** the user chooses «Перегляд» in the row of a page with the slug `about`
- **THEN** `/about` opens in a new tab

#### Scenario: Preview of a content record from a row
- **WHEN** the user chooses «Перегляд» in the row of the record `hello` of the type with the slug `news`
- **THEN** `/news/hello` opens in a new tab

#### Scenario: Record without a public address
- **WHEN** the user opens the menu «⋮» in the row of a record of a block or of a record of the type `content` without a public slug
- **THEN** there is no item «Перегляд», «Редагувати» and «Видалити» remain
