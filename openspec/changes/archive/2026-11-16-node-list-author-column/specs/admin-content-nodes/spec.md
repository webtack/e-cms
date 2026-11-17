# Spec Delta

## MODIFIED Requirements

### Requirement: Columns of the node table
The table SHALL always show the column `title` as non-hideable. The composition and order of the columns SHALL be set by `settings.list.visible` of the type: the key `title` (the position of the `title` column), the keys of the type's fields, and the service keys of the node: `slug` (the slug), `status` (the state: the badge "Published", "Scheduled", "Expired", or "Disabled"), `is_active` ("Yes"/"No" in the interface language), `created_at` (the creation date), `updated_at` (the update date), `published_at` (the publication date), `unpublished_at` (the unpublishing date), `views` (the number of views, an integer without separators; 0 is shown as 0), and `author` (the author of the node: their name next to a round avatar, which is their initials; an empty cell when the author is unknown). The dates SHALL be output in the format `Y-m-d H:i` (UTC); an empty `unpublished_at` — as an empty cell. A service column SHALL be shown only when its key is in `visible`, and exactly at the position given there. The column `title` SHALL stand at the position of its key in `visible`, and if the key is absent there — first; it cannot be hidden. If `visible` is absent or empty, the table SHALL show `title`, then the slug, the state, the publication date, and the author. A reference of the settings to a field that the type no longer has SHALL be ignored without an error. The value of a multiple field SHALL be given as a list and output in the cell as a column — each value on a new line (also boolean "Yes"/"No", term names, and the shortened text of `richtext`); an empty list — an empty cell. A boolean value SHALL be output as "Yes"/"No" in the interface language, an absent one — as an empty cell. The value of an `image` field SHALL be output as a thumbnail (the image of the file in a reduced form), which is a link to the file and opens in a new tab (`target="_blank"`, `rel="noopener noreferrer"`). The value of a `file` field SHALL be output by the original file name, which is a link to the file in a new tab; for `pdf` files the link SHALL open in the browser, for other types — it SHALL offer a download (the `download` attribute). For a file that is not in the media library, the stored row SHALL be shown without a link. For a multiple `image` or `file` field each file is output on a separate line of the column. The value is taken from the language `default`. The state SHALL be determined as follows: "Disabled" if `is_active = false` (regardless of the dates); otherwise "Scheduled" if `published_at` is later than the current moment; otherwise "Expired" if `unpublished_at` is set and is not later than the current moment; otherwise "Published". The columns `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, and `views` SHALL be sortable; `slug` and `author` — not. The personal hiding of columns by the user (the "Columns" menu) SHALL work on top of this composition and SHALL not change the settings of the type.

#### Scenario: Default settings
- **WHEN** the type has no list settings
- **THEN** the table shows the columns `title`, slug, the state, the publication date, and the author

#### Scenario: Visible fields from the settings
- **WHEN** `settings.list.visible` of the type is `price`, `tags`, and the node has `price` = 12.5 and `tags` = `["a", "b"]`
- **THEN** its row shows the columns `title`, `price` with the value 12.5, and `tags` with the values "a" and "b" on separate lines of the column; there is no slug, state, or publication date, because they are not in `visible`

#### Scenario: Service columns in the settings order
- **WHEN** `settings.list.visible` of the type is `status`, `price`, `published_at`
- **THEN** the columns go in the order `title`, state, `price`, publication date; there is no slug column

#### Scenario: All service columns
- **WHEN** `settings.list.visible` of the type is `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`
- **THEN** the table shows `title` and these five columns in the specified order; dates in the format `Y-m-d H:i`, `is_active` — "Yes" or "No"

#### Scenario: Views column
- **WHEN** `settings.list.visible` of the type contains `views`, and a node has 1250 views and another has none yet
- **THEN** the cells show `1250` and `0`, and the column is sortable

#### Scenario: Author column
- **WHEN** `settings.list.visible` of the type contains `author`, and a node was created by the user "Anna Petrenko"
- **THEN** the row shows "Anna Petrenko" with her initials in a round avatar in the author column, and the column is not sortable

#### Scenario: Author of a node is unknown
- **WHEN** the author of a node no longer exists or is not set, and `visible` contains `author`
- **THEN** the cell of this column is empty

#### Scenario: Author hidden by the user
- **WHEN** the user turns off the "Author" column in the "Columns" menu of the table
- **THEN** the column disappears for this user only and the settings of the type do not change

#### Scenario: Empty unpublishing date
- **WHEN** a node has no `unpublished_at`, and `visible` contains `unpublished_at`
- **THEN** the cell of this column is empty

#### Scenario: Position of title in the settings
- **WHEN** `settings.list.visible` is `price`, `title`, `status`
- **THEN** the columns go in the order `price`, `title`, state; `title` is present once and is not hidden

#### Scenario: Title without a key in the settings
- **WHEN** `settings.list.visible` is `price`, `status` and does not contain `title`
- **THEN** the columns go in the order `title`, `price`, state

#### Scenario: Deleted field in the settings
- **WHEN** `settings.list.visible` contains the key of a field that has already been deleted from the type
- **THEN** the page opens without an error, and the column of this field is absent

#### Scenario: Multiple values in a column
- **WHEN** a node has the multiple field `tags` with the values "a" and "b"
- **THEN** the row contains the list `["a", "b"]`, and the cell shows "a" and "b" one under the other

#### Scenario: Thumbnail of an image
- **WHEN** a node has an `image` field with a file
- **THEN** the cell shows a thumbnail that is a link to the file with `target="_blank"`

#### Scenario: File name
- **WHEN** a node has a `file` field with the file `report.docx`, whose original name is "Report.docx"
- **THEN** the cell shows "Report.docx" as a link to the file in a new tab with the `download` attribute

#### Scenario: PDF opens in the browser
- **WHEN** a node has a `file` field with a pdf file
- **THEN** the link opens in a new tab and has no `download` attribute

#### Scenario: Multiple image field
- **WHEN** a node has a multiple `image` field with three files
- **THEN** the cell shows three thumbnail links one under another

#### Scenario: File without a media library record
- **WHEN** the value of a `file` field references a file that is not in the media library
- **THEN** the cell shows the stored row without a link

#### Scenario: Boolean value
- **WHEN** a node has a field of type "boolean" with the value "true"
- **THEN** "Yes" is shown in the cell

#### Scenario: State "Published"
- **WHEN** a node is active, its `published_at` is not later than the current moment, and its `unpublished_at` is empty or later than the current moment
- **THEN** "Published" is shown in the state column

#### Scenario: State "Scheduled"
- **WHEN** a node is active, and its `published_at` is in the future
- **THEN** "Scheduled" is shown in the state column

#### Scenario: State "Expired"
- **WHEN** a node is active, its `published_at` is in the past, and its `unpublished_at` is not later than the current moment
- **THEN** "Expired" is shown in the state column

#### Scenario: State "Disabled"
- **WHEN** a node is disabled, and its `published_at` is in the future
- **THEN** "Disabled" is shown in the state column

#### Scenario: Disabled takes precedence over "Expired"
- **WHEN** a node is disabled, and its `unpublished_at` is in the past
- **THEN** "Disabled" is shown in the state column

#### Scenario: Sorting by the publication date
- **WHEN** the user sorts the table by the publication date column in descending order
- **THEN** the nodes go from the latest publication date to the earliest

#### Scenario: Sorting by state
- **WHEN** the user sorts by the state column in ascending order
- **THEN** first "Published" come, then "Scheduled", "Expired", and "Disabled"; for an equal state — by `title`

#### Scenario: Sorting by activity
- **WHEN** the user sorts by the `is_active` column in ascending order
- **THEN** the disabled nodes come before the active ones
