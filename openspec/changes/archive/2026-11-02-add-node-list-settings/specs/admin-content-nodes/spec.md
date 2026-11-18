# Spec Delta

## MODIFIED Requirements

### Requirement: Колонки таблиці записів
The table SHALL always show the column `title` not hideable. The composition and the order of the columns SHALL be set by `settings.list.visible` of the type: the key `title` (the position of the column `title`), the keys of the fields of the type and the service keys of the record: `slug` (slug), `status` (the state: the badge «Опубліковано», «Заплановано», «Завершено» or «Вимкнено»), `is_active` («Так»/«Ні» in the language of the interface), `created_at` (the creation date), `updated_at` (the update date), `published_at` (the publication date) and `unpublished_at` (the date of unpublishing). The dates SHALL be shown in the format `Y-m-d H:i` (UTC); an empty `unpublished_at` — as an empty cell. A service column SHALL be shown only when its key is in `visible`, and exactly at the position specified there. The column `title` SHALL stand at the position of its key in `visible`, and if the key is not there — first; it cannot be hidden. If `visible` is missing or empty, the table SHALL show `title`, then the slug, the state and the publication date. A setting that refers to a field the type no longer has SHALL be ignored without an error. The value of a multiple field SHALL be given as a list and shown in the cell as a column — each value from a new line (also the booleans «Так»/«Ні», the names of terms and the shortened text of `richtext`); an empty list — an empty cell. A boolean value SHALL be shown as «Так»/«Ні» in the language of the interface, a missing one — as an empty cell. The value of a field `image` SHALL be shown as a thumbnail (the image of the file in a reduced form), which is a link to the file and opens in a new tab (`target="_blank"`, `rel="noopener noreferrer"`). The value of a field `file` SHALL be shown by the original name of the file, which is a link to the file in a new tab; for `pdf` files the link SHALL open in the browser, for the other types — offer a download (the attribute `download`). For a file that is not in the media library, the saved string SHALL be shown without a link. For a multiple field `image` or `file` each file is shown on a separate line of the column. The value is taken from the language `default`. The status SHALL be determined as follows: «Вимкнено» if `is_active = false` (regardless of the dates); otherwise «Заплановано» if `published_at` is later than the current moment; otherwise «Завершено» if `unpublished_at` is set and is not later than the current moment; otherwise «Опубліковано». The columns `status`, `is_active`, `created_at`, `updated_at`, `published_at` and `unpublished_at` SHALL be sortable; `slug` — not. The personal hiding of columns by the user (the menu «Колонки») SHALL work on top of this composition and SHALL not change the settings of the type.

#### Scenario: Default settings
- **WHEN** the type has no settings of the list
- **THEN** the table shows the columns `title`, slug, the state and the publication date

#### Scenario: Visible fields from the settings
- **WHEN** `settings.list.visible` of the type is `price`, `tags`, and the record has `price` = 12.5 and `tags` = `["a", "b"]`
- **THEN** its row shows the columns `title`, `price` with the value 12.5 and `tags` with the values «a» and «b» on separate lines of the column; there is no slug, state or publication date, because they are not in `visible`

#### Scenario: Service columns in the order of the settings
- **WHEN** `settings.list.visible` of the type is `status`, `price`, `published_at`
- **THEN** the columns go in the order `title`, state, `price`, publication date; there is no column slug

#### Scenario: All service columns
- **WHEN** `settings.list.visible` of the type is `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`
- **THEN** the table shows `title` and these five columns in the specified order; the dates in the format `Y-m-d H:i`, `is_active` — «Так» or «Ні»

#### Scenario: Empty unpublishing date
- **WHEN** the record has no `unpublished_at`, and `visible` contains `unpublished_at`
- **THEN** the cell of this column is empty

#### Scenario: Position of title in the settings
- **WHEN** `settings.list.visible` is `price`, `title`, `status`
- **THEN** the columns go in the order `price`, `title`, state; `title` is present once and not hidden

#### Scenario: Title without a key in the settings
- **WHEN** `settings.list.visible` is `price`, `status` and does not contain `title`
- **THEN** the columns go in the order `title`, `price`, state

#### Scenario: Deleted field in the settings
- **WHEN** `settings.list.visible` contains the key of a field that has already been deleted from the type
- **THEN** the page opens without an error, and there is no column of this field

#### Scenario: Multiple values in a column
- **WHEN** the record has a multiple field `tags` with the values «a» and «b»
- **THEN** the row contains the list `["a", "b"]`, and the cell shows «a» and «b» one under another

#### Scenario: Thumbnail of an image
- **WHEN** the record has a field `image` with a file
- **THEN** the cell shows a thumbnail, which is a link to the file with `target="_blank"`

#### Scenario: Name of a file
- **WHEN** the record has a field `file` with the file `report.docx`, whose original name is «Report.docx»
- **THEN** the cell shows «Report.docx» as a link to the file in a new tab with the attribute `download`

#### Scenario: PDF opens in the browser
- **WHEN** the record has a field `file` with a pdf file
- **THEN** the link opens in a new tab and has no attribute `download`

#### Scenario: Multiple field of images
- **WHEN** the record has a multiple field `image` with three files
- **THEN** the cell shows three thumbnail-links one under another

#### Scenario: File without a record in the media library
- **WHEN** the value of the field `file` refers to a file that is not in the media library
- **THEN** the cell shows the saved string without a link

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

#### Scenario: Sorting by status
- **WHEN** the user sorts by the status column in ascending order
- **THEN** first come «Опубліковано», then «Заплановано», «Завершено» and «Вимкнено»; with an equal state — by `title`

#### Scenario: Sorting by activity
- **WHEN** the user sorts by the column `is_active` in ascending order
- **THEN** the disabled records go before the active ones

### Requirement: Пошук і сортування записів
The search of the table SHALL search by the search fields from the settings of the type (`settings.list.search`), and without them — by all text fields of the type, case-insensitively. Sorting SHALL be possible by the columns of the fields of the type, including multiple ones: the sort key of a record SHALL be the first value of the field. The columns of file fields (`file`, `image`), `youtube` and `term`, as well as slug, are not sortable. The service columns `status`, `is_active`, `created_at`, `updated_at`, `published_at` and `unpublished_at` are sortable: by the state in the order «Опубліковано», «Заплановано», «Завершено», «Вимкнено» (in descending order the reverse), by `is_active` «Ні» before «Так», by the dates chronologically. Without an explicit sorting in the address, the order SHALL be taken from `settings.list.sort` of the type (a field of the type or a service column from the list above, and a direction), and without it — by the order of creation. The sorting by `created_at`, `updated_at` and `unpublished_at` SHALL, for equal values, order the records by the identifier; the order by `title` is not applied to it. Records without a value in the sorting column (in particular without `unpublished_at`) SHALL go last in both directions. For equal keys of an explicit sorting in the address by a field, `published_at`, `status` and `is_active` the records SHALL go by `title` in the language of the list in ascending order, and then by the identifier. A page number out of range SHALL return the last page. Incorrect parameters of the address SHALL be ignored without an error of the page.

#### Scenario: Search
- **WHEN** the user enters «lamp» in the search of the table of records of the type `product`
- **THEN** the records whose text fields contain «lamp» remain in the table, and the address contains `q=lamp`

#### Scenario: Search by the fields from the settings
- **WHEN** `settings.list.search` of the type is `["title"]`, and a record contains «lamp» only in the field `description`
- **THEN** the search «lamp» does not find this record

#### Scenario: Sorting by a field
- **WHEN** the user sorts by the column `price` in descending order
- **THEN** the records go from the highest price, the records without a price — last

#### Scenario: Equal values in a column
- **WHEN** the user sorts by the column `price`, and two records have the same price and `title` «Beta» and «Alpha»
- **THEN** «Alpha» goes before «Beta» in both directions of the sorting

#### Scenario: Sorting by a multiple field
- **WHEN** the address contains `sort=tags`, and `tags` is a multiple text field
- **THEN** the records are ordered by the first value of `tags`, the records without values — last

#### Scenario: Sorting by a service column
- **WHEN** the address contains `sort=slug`
- **THEN** the sorting is ignored, the page opens

#### Scenario: Columns of file fields and youtube
- **WHEN** the type has the fields `image`, `file`, `youtube` and `term`
- **THEN** their columns have `sortable: false`, and `sort` by them in the address is ignored, the page opens

#### Scenario: Sorting setting by a multiple field
- **WHEN** `settings.list.sort` of the type refers to a multiple field
- **THEN** the records without an explicit `sort` in the address are ordered by the first value of this field

#### Scenario: Sorting setting by a file field
- **WHEN** `settings.list.sort` of the type refers to the field `image`
- **THEN** this setting is discarded, the default order applies

#### Scenario: Page out of range
- **WHEN** the address contains `page=999`, and there are three pages in total
- **THEN** the third page is shown

#### Scenario: Default order
- **WHEN** the type has no sorting settings, and there is no `sort` in the address
- **THEN** the records go in the order of creation

#### Scenario: Sorting setting by a field
- **WHEN** the type has in its settings a sorting by `title` in ascending order, and there is no `sort` in the address
- **THEN** the records go by `title` in ascending order

#### Scenario: Sorting setting by the creation date
- **WHEN** the type has in its settings `sort` by `created_at` in descending order, and there is no `sort` in the address
- **THEN** the records go from the newest by creation date to the oldest, without the influence of `title`

#### Scenario: Sorting setting by the update date
- **WHEN** the type has in its settings `sort` by `updated_at` in descending order, and there is no `sort` in the address
- **THEN** the records go from the latest updated to the earliest updated

#### Scenario: Records without an unpublishing date
- **WHEN** the user sorts by `unpublished_at` in ascending order, and some records do not have it
- **THEN** the records without an unpublishing date go last

#### Scenario: Sorting setting by the publication date
- **WHEN** the type has in its settings `sort` by `published_at` in descending order, and there is no `sort` in the address
- **THEN** the records go from the latest publication date to the earliest
