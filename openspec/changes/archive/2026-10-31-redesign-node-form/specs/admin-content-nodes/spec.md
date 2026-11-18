## MODIFIED Requirements

### Requirement: Columns of the table of records
The table SHALL always show the column `title` first and not hideable, and the column of the slug of the record. Right after the slug there SHALL be the status column of the record (the badge «Опубліковано», «Заплановано» or «Вимкнено», not sortable) and the publication date column (`published_at`), by which one can sort. Besides them, it SHALL show the visible fields from the list settings of the type (`settings.list.visible`) in the order specified there; without such settings — only `title`. A setting that refers to a field the type no longer has SHALL be ignored without an error. The value of a multiple field SHALL be shown separated by commas, a boolean — as «Так»/«Ні» in the language of the interface, a missing value — as an empty cell. The value is taken from the language `default`. The status SHALL be determined as follows: «Вимкнено» if `is_active = false` (regardless of the date); otherwise «Заплановано» if `published_at` is later than the current moment; otherwise «Опубліковано».

#### Scenario: Default settings
- **WHEN** the type has no list settings
- **THEN** the table shows the columns `title`, slug, status and publication date

#### Scenario: Visible fields from the settings
- **WHEN** `settings.list.visible` of the type is `title`, `price`, `tags`, and the record has `price` = 12.5 and `tags` = `["a", "b"]`
- **THEN** its row shows the columns `title`, `price` with the value 12.5, `tags` with the value «a, b», slug, status and publication date

#### Scenario: Deleted field in the settings
- **WHEN** `settings.list.visible` contains the key of a field that has already been deleted from the type
- **THEN** the page opens without an error, and there is no column of this field

#### Scenario: Boolean value
- **WHEN** the record has a field of the type «булеве» with the value «істина»
- **THEN** the cell shows «Так»

#### Scenario: Status «Опубліковано»
- **WHEN** the record is active, and its `published_at` is not later than the current moment
- **THEN** the status column shows «Опубліковано»

#### Scenario: Status «Заплановано»
- **WHEN** the record is active, and its `published_at` is in the future
- **THEN** the status column shows «Заплановано»

#### Scenario: Status «Вимкнено»
- **WHEN** the record is disabled, and its `published_at` is in the future
- **THEN** the status column shows «Вимкнено»

#### Scenario: Sorting by the publication date
- **WHEN** the user sorts the table by the publication date column in descending order
- **THEN** the records go from the latest publication date to the earliest

### Requirement: Form of a record
The creation form (`/admin/content-types/{key}/nodes/create`) and the edit form (`/admin/content-types/{key}/nodes/{uuid}/edit`) SHALL show above the form the section menu of the type and one input element for each field of the type in the order of the weight (`weight`), with the name of the field as the label; required fields SHALL be marked. The service attributes of the record (the slug and others, see the requirement about the details of the record) SHALL not be among the fields of the type, but in a separate service column. The input element SHALL correspond to the value type: «короткий текст» — a single-line field, «текст» — a multi-line one, «ціле число» and «десяткове число» — numeric fields, «булеве» — a checkbox, «дата» — a date selection, «дата й час» — a date and time selection. A multiple field SHALL be edited as a list of elements, to which elements can be added, from which they can be removed, and in which their order can be changed (by dragging and from the keyboard). The form does not show a language selection. The edit form SHALL be filled with the current values of the record in the language `default`, and the slug in it — with the current slug.

#### Scenario: Creation form
- **WHEN** the user opens the creation form of a record of a type with the fields `title` (weight 0), `price` (integer, weight 10) and `body` (text, weight 20)
- **THEN** they see the empty fields `title`, `price` and `body` in this order, `title` is marked as required, and the empty slug field is in the service column

#### Scenario: Elements by type
- **WHEN** the type has fields of the types «булеве», «дата» and «дата й час»
- **THEN** they are shown as a checkbox, a date selection and a date and time selection respectively

#### Scenario: Multiple field
- **WHEN** the type has a multiple field `tags`, and the user adds two elements and removes the first
- **THEN** one element remains in the list, and adding a new element adds an empty input row

#### Scenario: Changing the order of elements
- **WHEN** the user rearranges the elements of the multiple field `tags` from `a, b, c` to `c, a, b` and saves
- **THEN** after reopening the edit form the elements are in the order `c, a, b`, and in the list of records the value is shown as «c, a, b»

#### Scenario: Edit form
- **WHEN** the user opens the edit form of a record with the slug `pro-nas`, `title` «Про нас» and `tags` `["a", "b"]`
- **THEN** the slug field in the service column contains `pro-nas`, `title` — «Про нас», and the list `tags` — two elements «a» and «b»

#### Scenario: Record of another type
- **WHEN** the user opens the edit form for the `uuid` of a record that belongs to another type
- **THEN** the server responds with 404

### Requirement: Saving a new record
Submitting the creation form SHALL create a record of the type with the entered values in the language `default`. An empty slug SHALL lead to an automatic slug from `title`; an explicitly entered slug SHALL be checked for format and uniqueness among all records and SHALL not change. The form SHALL also accept `is_active` and `published_at`; an empty or not sent publication date SHALL be replaced by the current moment, and the author of the record SHALL become the current user regardless of what was sent. After success the user SHALL be redirected to the list of records of this type with a success message, and the record SHALL appear in the table. Validation errors SHALL return to the form and be shown next to the corresponding fields, including next to individual elements of a multiple field; the entered data SHALL be kept in the form.

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
- **THEN** the record is saved with a publication date equal to the moment of creation (to the minute)

#### Scenario: Deferred publication
- **WHEN** the user sets a publication date in the future and saves
- **THEN** the record is saved with this date, and in the table its status is «Заплановано»

#### Scenario: Invalid publication date
- **WHEN** the user enters unrecognizable text in the publication date field
- **THEN** the record is not created, an error is shown next to the date field

#### Scenario: Author is not substituted
- **WHEN** the creation request contains the `user_id` of another user
- **THEN** the author of the record becomes the current user

### Requirement: Saving changes of a record
Submitting the edit form SHALL replace the values of all fields of the type in the language `default` with the entered ones (the order of the elements of a multiple field is kept as in the form) (a cleared field SHALL lose its value), and SHALL allow changing the slug of the record to a free valid value, as well as `is_active` and `published_at`; `uuid`, the type of the record and the author SHALL remain unchanged (the sent `user_id` SHALL be ignored), and a cleared publication date SHALL give a validation error. Every successful saving SHALL update the `updated_at` of the record, even if only the values of the fields changed. An unchanged slug SHALL not be considered a duplicate of itself. After success the user SHALL be redirected to the list of records with a success message. Errors SHALL be shown the same way as on creation. The values of other languages SHALL remain unchanged.

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
- **THEN** the record has `is_active = false`, its publication date did not change, and in the table the status is «Вимкнено»

#### Scenario: Changing the publication date
- **WHEN** the user changes the publication date of a record and saves
- **THEN** after reopening the edit form shows the new date

#### Scenario: Cleared publication date
- **WHEN** the user clears the publication date field and saves
- **THEN** the changes are not saved, an error is shown next to the date field

#### Scenario: Author unchanged
- **WHEN** the update request contains the `user_id` of another user
- **THEN** the author of the record remains the previous one

#### Scenario: Updating the change time
- **WHEN** the user changes only the value of the field `title` and saves
- **THEN** the `updated_at` of the record is updated

### Requirement: Container of each field of the record form
The create and edit forms of a record SHALL place each field of the type in its own container with a frame, whose caption (the legend) is the name of the field with the required mark, styled in the same way as the block of the file settings in the field form. A boolean field SHALL show the checkbox with its label inside the frame. Hints and error messages SHALL remain inside the container of their own field. The buttons «Скасувати» and «Зберегти» SHALL remain outside the containers, in the sticky action bar at the bottom of the window. The containers do not change the order of the fields, the required marks and the behaviour of the multiple fields. The service column, unlike the fields of the type, SHALL be one block «Деталі» (not separate frames per attribute).

#### Scenario: Each field in a frame
- **WHEN** the user opens the creation form of a record of a type with the fields `title` and `body`
- **THEN** `title` and `body` are each in their own container with a frame and a caption-name, and the buttons «Скасувати» and «Зберегти» are in the sticky bar at the bottom of the window

#### Scenario: Errors inside the container
- **WHEN** the user saves the form with an empty required field
- **THEN** the error message is shown inside the container of this field

#### Scenario: Boolean field
- **WHEN** the type has a boolean field
- **THEN** its checkbox and label are in one line inside the frame

### Requirement: Item limit of a multiple field in the record form
The record form SHALL respect the maximum number of items of a multiple field if it is greater than 0. The counter «кількість / ліміт» SHALL be shown in one line with the label of the field (next to it, not below it). When there are as many items as allowed, the button «Додати елемент» (in non-file fields) SHALL be unavailable, and the zone of multiple file upload (`image`, `file`) SHALL NOT be shown; instead, an explanation of the reached limit SHALL be displayed; after removing an item the zone SHALL reappear. If the user selects more files than there are free places, only the first files in the order of selection SHALL be uploaded within the free places, and the rest SHALL be rejected with a message that names the limit. A field without a limit (`0`) SHALL behave as before. The server SHALL reject the excess regardless of the form, and the error SHALL be shown next to the field.

#### Scenario: Counter
- **WHEN** the user opens the record form with a multiple field with a limit of 3 and two items
- **THEN** «2 / 3» is visible next to the label of the field

#### Scenario: Counter in one line with the label
- **WHEN** a multiple field has a limit, including a required field with the mark «*»
- **THEN** the label and the counter stand in one line, the counter does not wrap under the label

#### Scenario: Limit reached in a string field
- **WHEN** a multiple string field with a limit of 2 already has two items
- **THEN** the button «Додати елемент» is unavailable

#### Scenario: Limit reached in an image field
- **WHEN** a multiple field `image` with a limit of 2 already has two images
- **THEN** there is no upload zone, and an explanation that the limit is reached is shown instead

#### Scenario: More files selected than places
- **WHEN** the field `image` with a limit of 3 already has one image, and the user selects four allowed files
- **THEN** only the first two are uploaded, the list has three items, and the rest is named in the limit message

#### Scenario: Removal frees a place
- **WHEN** the limit is reached, and the user removes one item
- **THEN** adding is available again, and in the field of images or files the upload zone reappears

#### Scenario: No limit
- **WHEN** the field has a limit of 0
- **THEN** there is no counter with a limit, and items can be added without restriction

#### Scenario: Server rejects the excess
- **WHEN** a save request bypasses the form and sends more items than the limit
- **THEN** the save is rejected, and an error is shown next to the field

## ADDED Requirements

### Requirement: Two-column layout of the record form
On screens from `lg` (1024 px) wide the create and edit form SHALL have two columns of about 70/30: on the left the fields of the type, on the right the block «Деталі», which stays in view when a long form is scrolled. The columns SHALL share one form: submitting saves both the fields of the type and the service attributes in one request. Wide elements (the `richtext` editor, the file widgets, the `youtube` card) SHALL not push the left column apart or overlap the right one.

#### Scenario: Desktop
- **WHEN** the user opens the form of a record in a window 1280 px wide
- **THEN** the fields of the type are on the left, the block «Деталі» on the right, and both are visible at the same time

#### Scenario: Scrolling
- **WHEN** the user scrolls a long form down on the desktop
- **THEN** the block «Деталі» remains visible in the window

#### Scenario: Wide youtube field
- **WHEN** the record contains a field `youtube` (single or multiple) with a long title and description, and the window is 1280 px wide
- **THEN** the container of the field does not go beyond the left column, and the block «Деталі» is not overlapped by anything

#### Scenario: One request
- **WHEN** the user changes `title` and the status, and then presses «Зберегти»
- **THEN** both changes are saved in one submission of the form

### Requirement: Details of a record in the form
The block «Деталі» SHALL contain, in this order: the switch «Показувати на сайті» (`is_active`; enabled in the creation form), the badge of the current status of the record, the field «Дата публікації» (`published_at`, a date and time selection; filled with the current moment in the creation form), the slug field (with the same hints and rules as before), «Оновлено» (`updated_at`, read-only, only in the edit form) and «Автор» (the name of the author, read-only; in the creation form — the current user). The creation date (`created_at`) SHALL NOT be shown. The badge SHALL display the status by the current state of the form (before saving): a disabled switch gives «Вимкнено», an enabled one with a future date — «Заплановано», otherwise — «Опубліковано». The editable fields SHALL show validation errors inside the block.

#### Scenario: Service block on creation
- **WHEN** the user opens the creation form
- **THEN** in the block the switch «Показувати на сайті» is enabled, the publication date is filled with the current moment, the slug is empty, there is no «Оновлено», and the current user is shown as the author

#### Scenario: Service block on editing
- **WHEN** the user opens the edit form of a record created by another user
- **THEN** «Автор» shows that user, and «Оновлено» — the time of the last change of the record, neither is editable

#### Scenario: Badge reacts to the form
- **WHEN** the user turns off the switch «Показувати на сайті»
- **THEN** the badge immediately shows «Вимкнено», although the record is not saved yet

#### Scenario: Badge «Заплановано»
- **WHEN** the user enters a publication date in the future with the switch enabled
- **THEN** the badge shows «Заплановано»

#### Scenario: Creation date
- **WHEN** the user opens the edit form
- **THEN** there is no creation date in the form

### Requirement: Details panel on narrow screens
On screens narrower than `lg` the block «Деталі» SHALL open as a slide-out panel, not be a column. The sticky action bar at the bottom of the window SHALL, besides «Скасувати» and «Зберегти», contain the details button (on `lg` and wider there is no such button). The button SHALL be an icon without visible text and without the status badge, SHALL have the accessible name «Деталі», and SHALL show an error indicator if any service field has a validation error. Closing the panel SHALL NOT lose the values entered in it, and submitting the form SHALL send them the same way as on the desktop. While the panel is closed, the service fields SHALL NOT be duplicated on the page. After a failed saving with an error of a service field, the panel SHALL stay closed, but the button SHALL show the error indicator; opening the panel shows the message next to the corresponding field. The buttons «Скасувати» and «Зберегти» remain available in the bar.

#### Scenario: Mobile view
- **WHEN** the user opens the form in a window 390 px wide
- **THEN** they see the fields of the type in one column, and at the bottom of the screen the bar with the icon button of the details and the buttons «Скасувати» and «Зберегти», without a column on the right

#### Scenario: Opening the panel
- **WHEN** the user presses the icon button of the details
- **THEN** a panel opens with the switch, the publication date, the slug, «Оновлено» and «Автор»

#### Scenario: Values survive closing
- **WHEN** the user changes the slug in the panel, closes it and presses «Зберегти»
- **THEN** the record is saved with the new slug

#### Scenario: Error of a service field
- **WHEN** the user on a narrow screen enters an occupied slug and presses «Зберегти»
- **THEN** the record is not saved, and the details button shows the error indicator

#### Scenario: Message in the panel
- **WHEN** after a failed saving with a slug error the user opens the panel
- **THEN** an error message is shown next to the slug field

#### Scenario: Button without text
- **WHEN** the user opens the form on a narrow screen
- **THEN** the details button contains no visible text and no status badge, but has the accessible name «Деталі»

#### Scenario: Rotating the screen
- **WHEN** on a narrow screen the user changed the values in the panel and enlarged the window to a width from `lg`
- **THEN** the changed values are shown in the right column without loss
