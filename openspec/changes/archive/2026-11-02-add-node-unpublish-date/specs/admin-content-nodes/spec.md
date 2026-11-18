# Spec Delta

## MODIFIED Requirements

### Requirement: Колонки таблиці записів
The table SHALL always show the column `title` first and not hideable, and the column of the slug of the record. Right after the slug there SHALL be the column of the state of the record (the badge «Опубліковано», «Заплановано», «Завершено» or «Вимкнено», not sortable) and the publication date column (`published_at`), by which one can sort. The columns of the unpublish date (`unpublished_at`) SHALL NOT be there. Besides them, it SHALL show the visible fields from the list settings of the type (`settings.list.visible`) in the order specified there; without such settings — only `title`. A setting that refers to a field the type no longer has SHALL be ignored without an error. The value of a multiple field SHALL be shown separated by commas, a boolean — as «Так»/«Ні» in the language of the interface, a missing value — as an empty cell. The value is taken from the language `default`. The status SHALL be determined as follows: «Вимкнено» if `is_active = false` (regardless of the dates); otherwise «Заплановано» if `published_at` is later than the current moment; otherwise «Завершено» if `unpublished_at` is set and is not later than the current moment; otherwise «Опубліковано».

#### Scenario: Default settings
- **WHEN** the type has no list settings
- **THEN** the table shows the columns `title`, slug, the state and the publication date

#### Scenario: Visible fields from the settings
- **WHEN** `settings.list.visible` of the type is `title`, `price`, `tags`, and the record has `price` = 12.5 and `tags` = `["a", "b"]`
- **THEN** its row shows the columns `title`, `price` with the value 12.5, `tags` with the value «a, b», slug, the state and the publication date

#### Scenario: Deleted field in the settings
- **WHEN** `settings.list.visible` contains the key of a field that has already been deleted from the type
- **THEN** the page opens without an error, and there is no column of this field

#### Scenario: Boolean value
- **WHEN** the record has a field of the type «булеве» with the value «істина»
- **THEN** the cell shows «Так»

#### Scenario: Status «Опубліковано»
- **WHEN** the record is active, its `published_at` is not later than the current moment, and its `unpublished_at` is empty or later than the current moment
- **THEN** the status column shows «Опубліковано»

#### Scenario: Status «Заплановано»
- **WHEN** the record is active, and its `published_at` is in the future
- **THEN** the status column shows «Заплановано»

#### Scenario: Status «Завершено»
- **WHEN** the record is active, its `published_at` is in the past, and its `unpublished_at` is not later than the current moment
- **THEN** the status column shows «Завершено»

#### Scenario: Status «Вимкнено»
- **WHEN** the record is disabled, and its `published_at` is in the future
- **THEN** the status column shows «Вимкнено»

#### Scenario: «Вимкнено» takes priority over «Завершено»
- **WHEN** the record is disabled, and its `unpublished_at` is in the past
- **THEN** the status column shows «Вимкнено»

#### Scenario: Sorting by publication date
- **WHEN** the user sorts the table by the column of the publication date in descending order
- **THEN** the records go from the latest publication date to the earliest

### Requirement: Збереження нового запису
Submitting the creation form SHALL create a record of the type with the entered values in the language `default`. An empty slug SHALL lead to an automatic slug from `title`; an explicitly entered slug SHALL be checked for format and uniqueness among all records and SHALL not change. The form SHALL also accept `is_active`, `published_at` and `unpublished_at`; an empty or not sent publication date SHALL be replaced by the current moment, an empty or not sent unpublish date SHALL mean «no unpublish date», and the author of the record SHALL become the current user regardless of what was sent. The unpublish date SHALL be later than the publication date (for an empty publication date — than the current moment). After success the user SHALL be redirected to the list of records of this type with a success message, and the record SHALL appear in the table. Validation errors SHALL return to the form and be shown next to the corresponding fields, including next to individual elements of a multiple field; the entered data SHALL be kept in the form.

#### Scenario: Successful creation
- **WHEN** the user enters `title` «Про нас» and submits the form
- **THEN** the record is saved with the slug `pro-nas`, the user sees the list of records, a success toast and a new row in the table

#### Scenario: Explicit slug
- **WHEN** the user enters the slug `about` and `title` «Про нас»
- **THEN** a record with the slug `about` is saved

#### Scenario: Occupied slug
- **WHEN** the user enters a slug that already belongs to a record of any type or kind
- **THEN** the record is not created, an error is shown next to the slug field, and the rest of the entered data remains in the form

#### Scenario: Invalid slug format
- **WHEN** the user enters the slug `Про нас`
- **THEN** the record is not created, an error is shown next to the slug field

#### Scenario: Required field
- **WHEN** the user submits the form with an empty `title`
- **THEN** the record is not created, an error is shown next to the field `title`

#### Scenario: Error of an element of a multiple field
- **WHEN** the multiple numeric field `scores` gets a non-numeric value as its second element
- **THEN** the record is not created, the error is shown next to the second element of the list

#### Scenario: Value by the field settings
- **WHEN** the field has a maximum of 5 in its settings, and 7 is entered
- **THEN** the record is not created, an error is shown next to the field

#### Scenario: Default publication date
- **WHEN** the user creates a record without changing the publication date
- **THEN** the record is saved with a publication date equal to the moment of creation (to the minute), and without an unpublish date

#### Scenario: Deferred publication
- **WHEN** the user sets a publication date in the future and saves
- **THEN** the record is saved with this date, and in the table its status is «Заплановано»

#### Scenario: Invalid publication date
- **WHEN** the user enters unrecognizable text in the publication date field
- **THEN** the record is not created, an error is shown next to the date field

#### Scenario: Creation with an unpublish date
- **WHEN** the user sets a publication date and a later unpublish date and saves
- **THEN** the record is saved with both dates

#### Scenario: Unpublish date not later than the publication date
- **WHEN** the user sets an unpublish date that equals or precedes the publication date
- **THEN** the record is not created, an error is shown next to the unpublish date field, and the rest of the entered data remains in the form

#### Scenario: Unpublish date without a publication date
- **WHEN** the user leaves the publication date empty and sets an unpublish date in the past
- **THEN** the record is not created, an error is shown next to the unpublish date field

#### Scenario: Invalid unpublish date
- **WHEN** the user enters unrecognizable text in the unpublish date field
- **THEN** the record is not created, an error is shown next to the unpublish date field

#### Scenario: Author is not substituted
- **WHEN** the creation request contains the `user_id` of another user
- **THEN** the author of the record becomes the current user

### Requirement: Збереження змін запису
Submitting the edit form SHALL replace the values of all fields of the type in the language `default` with the entered ones (the order of the elements of a multiple field is kept as in the form) (a cleared field SHALL lose its value), and SHALL allow changing the slug of the record to a free valid value, as well as `is_active`, `published_at` and `unpublished_at`; `uuid`, the type of the record and the author SHALL remain unchanged (the sent `user_id` SHALL be ignored), and a cleared publication date SHALL give a validation error. A cleared unpublish date SHALL remove it (the record is again without an unpublish date), and a set one SHALL be later than the publication date. Every successful saving SHALL update the `updated_at` of the record, even if only the values of the fields changed. An unchanged slug SHALL not be considered a duplicate of itself. After success the user SHALL be redirected to the list of records with a success message. Errors SHALL be shown the same way as on creation. The values of other languages SHALL remain unchanged.

#### Scenario: Changing a value
- **WHEN** the user changes the `title` of a record and saves
- **THEN** the new `title` is shown in the list, the slug remained the same

#### Scenario: Clearing an optional field
- **WHEN** the user clears the optional field `summary` and saves
- **THEN** the value of `summary` is absent in the record

#### Scenario: Changing the slug
- **WHEN** the user changes the slug of a record to the free `about-us`
- **THEN** the record still opens by the same edit address (by `uuid`), its `uuid` did not change, and the form of this record opened in another tab is saved without a 404 error

#### Scenario: Saving without changing the slug
- **WHEN** the user saves the form without changing the slug
- **THEN** the saving succeeds, there is no uniqueness error

#### Scenario: Slug of another record
- **WHEN** the user enters a slug that belongs to another record
- **THEN** the changes are not saved, an error is shown next to the slug field

#### Scenario: Translations
- **WHEN** the record has a translation of `title` in `en`, and the user changes `title` in the form
- **THEN** only the value `default` changes, the translation `en` remains

#### Scenario: Disabling a record
- **WHEN** the user turns off the switch «Показувати на сайті» and saves
- **THEN** the record has `is_active = false`, its publication and unpublish dates did not change, and in the table the status is «Вимкнено»

#### Scenario: Changing the publication date
- **WHEN** the user changes the publication date of a record and saves
- **THEN** after reopening the edit form shows the new date

#### Scenario: Cleared publication date
- **WHEN** the user clears the publication date field and saves
- **THEN** the changes are not saved, an error is shown next to the date field

#### Scenario: Setting an unpublish date
- **WHEN** the user sets an unpublish date later than the publication date and saves
- **THEN** after reopening the edit form shows this date

#### Scenario: Ending the display
- **WHEN** the user sets an unpublish date in the past (later than the publication date) on an active record and saves
- **THEN** in the table the state of the record is «Завершено»

#### Scenario: Clearing the unpublish date
- **WHEN** the user clears the unpublish date field and saves
- **THEN** the record is without an unpublish date, and the form after reopening shows an empty field

#### Scenario: Unpublish date not later than the publication date
- **WHEN** the user sets an unpublish date that equals or precedes the publication date, and saves
- **THEN** the changes are not saved, an error is shown next to the unpublish date field

#### Scenario: Author unchanged
- **WHEN** the update request contains the `user_id` of another user
- **THEN** the author of the record remains the previous one

#### Scenario: Updating the change time
- **WHEN** the user changes only the value of the field `title` and saves
- **THEN** the `updated_at` of the record is updated

### Requirement: Деталі запису у формі
The block «Деталі» SHALL contain, in this order: the switch «Показувати на сайті» (`is_active`; enabled in the creation form), the badge of the current state of the record, the field «Дата публікації» (`published_at`, a date and time selection; filled with the current moment in the creation form), the field «Показувати до» (`unpublished_at`, a date and time selection; may be empty, empty in the creation form), the slug field (with the same hints and rules as before), «Оновлено» (`updated_at`, read-only, only in the edit form) and «Автор» (the name of the author, read-only; in the creation form — the current user). The creation date (`created_at`) SHALL NOT be shown. The badge SHALL display the state by the current state of the form (before saving): a disabled switch gives «Вимкнено», an enabled one with a publication date in the future — «Заплановано», an enabled one with an unpublish date not later than the current moment (and a publication date not in the future) — «Завершено», otherwise — «Опубліковано». The editable fields SHALL show validation errors inside the block.

#### Scenario: Service block on creation
- **WHEN** the user opens the creation form
- **THEN** in the block the switch «Показувати на сайті» is enabled, the publication date is filled with the current moment, the field «Показувати до» is empty, the slug is empty, there is no «Оновлено», and the current user is shown as the author

#### Scenario: Service block on editing
- **WHEN** the user opens the edit form of a record created by another user
- **THEN** «Автор» shows that user, and «Оновлено» — the time of the last change of the record, neither is editable

#### Scenario: Badge reacts to the form
- **WHEN** the user turns off the switch «Показувати на сайті»
- **THEN** the badge immediately shows «Вимкнено», although the record is not saved yet

#### Scenario: Badge «Заплановано»
- **WHEN** the user enters a publication date in the future with the switch enabled
- **THEN** the badge shows «Заплановано»

#### Scenario: Badge «Завершено»
- **WHEN** the user enters an unpublish date in the past with the switch enabled
- **THEN** the badge shows «Завершено»

#### Scenario: Field «Показувати до»
- **WHEN** the user opens the edit form of a record with an unpublish date
- **THEN** the field «Показувати до» is filled with this date, and after clearing the field and saving the record remains without an unpublish date

#### Scenario: Error of the unpublish date
- **WHEN** the server rejects the unpublish date
- **THEN** the error is shown inside the block next to the field «Показувати до»

#### Scenario: Creation date
- **WHEN** the user opens the edit form
- **THEN** there is no creation date in the form
