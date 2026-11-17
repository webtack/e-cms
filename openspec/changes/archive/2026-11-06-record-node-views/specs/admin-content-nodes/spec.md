# Spec Delta

## MODIFIED Requirements

### Requirement: Columns of the node table
The table SHALL always show the column `title` as non-hideable. The composition and order of the columns SHALL be set by `settings.list.visible` of the type: the key `title` (the position of the `title` column), the keys of the type's fields, and the service keys of the node: `slug` (the slug), `status` (the state: the badge "Published", "Scheduled", "Expired", or "Disabled"), `is_active` ("Yes"/"No" in the interface language), `created_at` (the creation date), `updated_at` (the update date), `published_at` (the publication date), `unpublished_at` (the unpublishing date), and `views` (the number of views, an integer without separators; 0 is shown as 0). The dates SHALL be output in the format `Y-m-d H:i` (UTC); an empty `unpublished_at` — as an empty cell. A service column SHALL be shown only when its key is in `visible`, and exactly at the position given there. The column `title` SHALL stand at the position of its key in `visible`, and if the key is absent there — first; it cannot be hidden. If `visible` is absent or empty, the table SHALL show `title`, then the slug, the state, and the publication date. A reference of the settings to a field that the type no longer has SHALL be ignored without an error. The value of a multiple field SHALL be given as a list and output in the cell as a column — each value on a new line (also boolean "Yes"/"No", term names, and the shortened text of `richtext`); an empty list — an empty cell. A boolean value SHALL be output as "Yes"/"No" in the interface language, an absent one — as an empty cell. The value of an `image` field SHALL be output as a thumbnail (the image of the file in a reduced form), which is a link to the file and opens in a new tab (`target="_blank"`, `rel="noopener noreferrer"`). The value of a `file` field SHALL be output by the original file name, which is a link to the file in a new tab; for `pdf` files the link SHALL open in the browser, for other types — it SHALL offer a download (the `download` attribute). For a file that is not in the media library, the stored row SHALL be shown without a link. For a multiple `image` or `file` field each file is output on a separate line of the column. The value is taken from the language `default`. The state SHALL be determined as follows: "Disabled" if `is_active = false` (regardless of the dates); otherwise "Scheduled" if `published_at` is later than the current moment; otherwise "Expired" if `unpublished_at` is set and is not later than the current moment; otherwise "Published". The columns `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, and `views` SHALL be sortable; `slug` — not. The personal hiding of columns by the user (the "Columns" menu) SHALL work on top of this composition and SHALL not change the settings of the type.

#### Scenario: Default settings
- **WHEN** the type has no list settings
- **THEN** the table shows the columns `title`, slug, the state, and the publication date

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

### Requirement: Search and sorting of nodes
The search of the table SHALL search by the search fields from the settings of the type (`settings.list.search`), and without them — by all text fields of the type, case-insensitively. It SHALL be possible to sort by the columns of the fields of the type, including multiple ones: the sort key of a node SHALL be the first value of the field. The columns of file fields (`file`, `image`), `youtube`, and `term`, as well as the slug, are not sortable. The service columns `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, and `views` are sortable: by state in the order "Published", "Scheduled", "Expired", "Disabled" (reversed for descending), by `is_active` "No" before "Yes", by dates chronologically, by `views` numerically. Without an explicit sort in the address, the order SHALL be taken from `settings.list.sort` of the type (a field of the type or a service column from the list above, and a direction), and without it — by the creation order. The sorting by `created_at`, `updated_at`, and `unpublished_at` SHALL, for equal values, order the nodes by identifier; the order by `title` is not applied before it. Nodes without a value in the sorting column (in particular without `unpublished_at`) SHALL go last in both directions. For equal keys of an explicit sorting in the address by a field, `published_at`, `status`, `is_active`, and `views`, the nodes SHALL go by `title` in the language of the list in ascending order, and then by identifier. A page number out of range SHALL return the last page. Invalid address parameters SHALL be ignored without a page error.

#### Scenario: Search
- **WHEN** the user types "lamp" into the search of the table of nodes of the type `product`
- **THEN** the table keeps the nodes whose text fields contain "lamp", and the address contains `q=lamp`

#### Scenario: Search by the fields from the settings
- **WHEN** `settings.list.search` of the type is `["title"]`, and a node contains "lamp" only in the field `description`
- **THEN** the search for "lamp" does not find this node

#### Scenario: Sorting by a field
- **WHEN** the user sorts by the column `price` in descending order
- **THEN** the nodes go from the highest price, and the nodes without a price go last

#### Scenario: Equal values in a column
- **WHEN** the user sorts by the column `price`, and two nodes have the same price and the `title` "Beta" and "Alpha"
- **THEN** "Alpha" comes before "Beta" in both sorting directions

#### Scenario: Sorting by a multiple field
- **WHEN** the address contains `sort=tags`, and `tags` is a multiple text field
- **THEN** the nodes are ordered by the first value of `tags`, nodes without values go last

#### Scenario: Sorting by a service column
- **WHEN** the address contains `sort=slug`
- **THEN** the sorting is ignored, and the page opens

#### Scenario: File and youtube columns
- **WHEN** the type has the fields `image`, `file`, `youtube`, and `term`
- **THEN** their columns have `sortable: false`, and a `sort` by them in the address is ignored, and the page opens

#### Scenario: Sorting setting by a multiple field
- **WHEN** `settings.list.sort` of the type refers to a multiple field
- **THEN** the nodes without an explicit `sort` in the address are ordered by the first value of this field

#### Scenario: Sorting setting by a file field
- **WHEN** `settings.list.sort` of the type refers to the field `image`
- **THEN** this setting is discarded, and the default order applies

#### Scenario: Page out of range
- **WHEN** the address contains `page=999`, and there are only three pages
- **THEN** the third page is shown

#### Scenario: Default order
- **WHEN** the type has no sorting settings, and there is no `sort` in the address
- **THEN** the nodes go in the creation order

#### Scenario: Sorting setting by a field
- **WHEN** the type has in its sorting settings a sort by `title` in ascending order, and there is no `sort` in the address
- **THEN** the nodes go by `title` in ascending order

#### Scenario: Sorting setting by the creation date
- **WHEN** the type has in its settings `sort` by `created_at` in descending order, and there is no `sort` in the address
- **THEN** the nodes go from the newest by creation date to the oldest, without the influence of `title`

#### Scenario: Sorting setting by the update date
- **WHEN** the type has in its settings `sort` by `updated_at` in descending order, and there is no `sort` in the address
- **THEN** the nodes go from the latest updated to the earliest updated

#### Scenario: Nodes without an unpublishing date
- **WHEN** the user sorts by `unpublished_at` in ascending order, and some nodes do not have it
- **THEN** the nodes without an unpublishing date go last

#### Scenario: Sorting setting by the publication date
- **WHEN** the type has in its settings `sort` by `published_at` in descending order, and there is no `sort` in the address
- **THEN** the nodes go from the latest publication date to the earliest
