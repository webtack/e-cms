# admin-content-nodes Specification

## Purpose
Gives the admin panel management of the nodes of content types of kind `content`: the list in a data table, creation, editing, and deletion of a node with a dynamic form by the fields of the type. Works only with the base language (`default`).

## Requirements

### Requirement: Node list page
The admin panel SHALL have the node list pages `/admin/content-types/{key}/nodes`, `/admin/page-types/{key}/nodes`, and `/admin/block-types/{key}/nodes`, available only to authenticated users with a verified email. Each shows the nodes of the type with this key in a data table in server mode: search, sorting, pagination, and page size are performed by the server, and the state of the table is kept in the address. The section SHALL accept only types of its own kind (`content`, `page`, `block` respectively). The table SHALL NOT have filters. The page SHALL have no title of its own: the heading is the name of the type shown by the horizontal section menu of the type above the tabs (see `admin-content-types`), with the current item "Nodes", and the favorites star of the type SHALL stand next to this heading. For a key that is not among the types of the section's kind (in particular a key of a type of another kind), the page SHALL respond with 404. The page SHALL open also for a type without nodes.

#### Scenario: Opening the page
- **WHEN** an authenticated user opens `/admin/content-types/news/nodes`
- **THEN** they see the table of nodes of the type `news`, a heading with the name of the type above the section menu, where "Nodes" is current, and no title "Nodes of “…”"; there is no filter button in the table toolbar

#### Scenario: Favorites star
- **WHEN** the user opens the node list of a type
- **THEN** the favorites star is next to the heading with the name of the type, and clicking it adds the type to the favorites or removes it, as before

#### Scenario: Nodes of pages and blocks
- **WHEN** an authenticated user opens `/admin/page-types/home/nodes` for a type of kind `page` or `/admin/block-types/hero/nodes` for a type of kind `block`
- **THEN** they see the table of nodes of this type, a heading with the name of the type, and the section menu, where "Nodes" is current

#### Scenario: Nodes of this type only
- **WHEN** there are nodes of the types `news` and `product`, and the user opens the list of the type `news`
- **THEN** the table contains only the nodes of the type `news`

#### Scenario: Empty state
- **WHEN** a type has no nodes
- **THEN** the page opens with the standard message of the table about the absence of data, and the "Create" button is visible

#### Scenario: Type of another kind
- **WHEN** the user opens `/admin/content-types/home/nodes` for the key of a type of kind `page`, or `/admin/page-types/hero/nodes` for the key of a type of kind `block`
- **THEN** the server responds with 404

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/content-types/news/nodes` or `/admin/page-types/home/nodes`
- **THEN** they are redirected to the login page

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

### Requirement: Search and sorting of nodes
The search of the table SHALL search by the search fields from the settings of the type (`settings.list.search`), and without them — by all text fields of the type, and in both cases also by the slug of the node, case-insensitively; the slug is always searched and is not a setting. A match in any of them is enough. It SHALL be possible to sort by the columns of the fields of the type, including multiple ones: the sort key of a node SHALL be the first value of the field. The columns of file fields (`file`, `image`), `youtube`, and `term`, as well as the slug, are not sortable. The service columns `status`, `is_active`, `created_at`, `updated_at`, `published_at`, `unpublished_at`, and `views` are sortable: by state in the order "Published", "Scheduled", "Expired", "Disabled" (reversed for descending), by `is_active` "No" before "Yes", by dates chronologically, by `views` numerically. Without an explicit sort in the address, the order SHALL be taken from `settings.list.sort` of the type (a field of the type or a service column from the list above, and a direction), and without it — by the creation order. The sorting by `created_at`, `updated_at`, and `unpublished_at` SHALL, for equal values, order the nodes by identifier; the order by `title` is not applied before it. Nodes without a value in the sorting column (in particular without `unpublished_at`) SHALL go last in both directions. For equal keys of an explicit sorting in the address by a field, `published_at`, `status`, `is_active`, and `views`, the nodes SHALL go by `title` in the language of the list in ascending order, and then by identifier. A page number out of range SHALL return the last page. Invalid address parameters SHALL be ignored without a page error.

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

#### Scenario: Search by the slug
- **WHEN** the user types "summer-sale" into the search of the table, and a node has the slug `summer-sale-2026` while "summer-sale" is in none of its field values
- **THEN** the table keeps this node

#### Scenario: Slug is searched even with search fields in the settings
- **WHEN** `settings.list.search` of the type is `["title"]`, and the user types the slug of a node whose title does not contain it
- **THEN** the table keeps this node

#### Scenario: Slug wildcards are literal
- **WHEN** the user types `%` or `_` into the search
- **THEN** only nodes whose slug or searched fields contain this character literally are kept

#### Scenario: Type without text fields
- **WHEN** the type has no text fields and the user types part of a node slug
- **THEN** the table keeps the nodes whose slug contains it

### Requirement: Creating a node from the table
Above the table of nodes there SHALL be an active "Create" button in the toolbar, which opens the form for creating a node of this type. Each row SHALL have a group of buttons: the main "Edit" button and next to it the "⋮" menu button with the items "Edit", "Preview" (only for nodes that have a public address), and "Delete" (in this order; "Delete" is visually highlighted as a destructive action and separated). Both "Edit" SHALL open the edit form of this node. The "Preview" item SHALL be a link to the public address of the node (the same address as the "Preview" button of the edit form: `/{slug}` for a page, `/{type slug}/{node slug}` for a `content` node), which opens in a new tab; for a node of type `block` and for a node of type `content` without a public slug there SHALL be NO item.

#### Scenario: Navigation to the create form
- **WHEN** the user clicks "Create" on the page of nodes of the type `news`
- **THEN** `/admin/content-types/news/nodes/create` opens

#### Scenario: Edit from the row
- **WHEN** the user clicks "Edit" in the row of the node with the `uuid` `0190a1b2-…` of the type `news`
- **THEN** `/admin/content-types/news/nodes/0190a1b2-…/edit` opens

#### Scenario: Composition of the row menu
- **WHEN** the user opens the "⋮" menu in the row of a node
- **THEN** they see the items "Edit", "Preview", and "Delete" with translated labels

#### Scenario: Preview of a page from the row
- **WHEN** the user selects "Preview" in the row of a page with the slug `about`
- **THEN** `/about` opens in a new tab

#### Scenario: Preview of a content node from the row
- **WHEN** the user selects "Preview" in the row of the node `hello` of a type with the slug `news`
- **THEN** `/news/hello` opens in a new tab

#### Scenario: Node without a public address
- **WHEN** the user opens the "⋮" menu in the row of a node of a block or of a node of type `content` without a public slug
- **THEN** there is no "Preview" item; "Edit" and "Delete" remain

### Requirement: Node form
The create form (`/admin/content-types/{key}/nodes/create`) and the edit form (`/admin/content-types/{key}/nodes/{uuid}/edit`) SHALL show above the form the section menu of the type and one input element for each field of the node of the type in weight order (`weight`), with the field name as the label; required fields SHALL be marked. Fields that belong only to the type itself (`section_*`) SHALL be absent from the node form, and the `meta_*` fields SHALL be shown in the "SEO" section of the right column (see the requirement about the "SEO" section), not among the main fields. The service attributes of the node (the slug and others, see the requirement about node details) SHALL not be among the fields of the type, but in a separate service column. The input element SHALL correspond to the value type: "short text" — a single-line field, "text" — a multiline one, "integer" and "decimal number" — a numeric field, "boolean" — a checkbox, "date" — a date selection, "date and time" — a date and time selection. A multiple field SHALL be edited as a list of elements, to which elements can be added, from which they can be deleted, and in which their order can be changed (by dragging and from the keyboard). The form shows no language selection. The edit form SHALL be filled with the current values of the node in the language `default`, and its slug — with the current slug.

#### Scenario: Create form
- **WHEN** the user opens the create form of a node of a type with the fields `title` (weight 0), `price` (integer, weight 10), and `body` (text, weight 20)
- **THEN** they see the empty fields `title`, `price`, and `body` in this order, `title` is marked as required, and the empty slug field is in the service column

#### Scenario: Elements by type
- **WHEN** the type has fields of the types "boolean", "date", and "date and time"
- **THEN** they are shown as a checkbox, a date selection, and a date-and-time selection respectively

#### Scenario: Multiple field
- **WHEN** the type has the multiple field `tags`, and the user adds two elements and deletes the first one
- **THEN** one element remains in the list, and adding a new element adds an empty input row

#### Scenario: Changing the order of elements
- **WHEN** the user rearranges the elements of the multiple field `tags` from `a, b, c` to `c, a, b` and saves
- **THEN** after reopening the edit form the elements go in the order `c, a, b`, and in the list of nodes the value is shown as "c, a, b"

#### Scenario: Edit form
- **WHEN** the user opens the edit form of a node with the slug `about-us`, `title` "About us", and `tags` `["a", "b"]`
- **THEN** the slug field in the service column contains `about-us`, `title` — "About us", and the list `tags` — two elements "a" and "b"

#### Scenario: Section fields not in the node form
- **WHEN** the user opens the form of a node of a type of kind `content`
- **THEN** the fields `section_title`, `section_description`, `section_cover` are absent from the form

#### Scenario: Node of another type
- **WHEN** the user opens the edit form for the `uuid` of a node that belongs to another type
- **THEN** the server responds with 404

### Requirement: Saving a new node
Submitting the create form SHALL create a node of the type with the entered values in the language `default`. An empty slug SHALL lead to an automatic slug from the `title`; an explicitly entered slug SHALL be checked for format and uniqueness among all nodes and not changed. The form SHALL also accept `is_active`, `published_at`, and `unpublished_at`; an empty or not sent publication date SHALL be replaced by the current moment, an empty or not sent unpublishing date SHALL mean "no unpublishing date", and the author of the node SHALL become the current user regardless of what was sent. The unpublishing date SHALL be later than the publication date (for an empty publication date — the current moment). After success the user SHALL be redirected to the list of nodes of this type with a success message, and the node SHALL appear in the table. Validation errors SHALL return to the form and be shown next to the corresponding fields, including next to the individual elements of a multiple field; the entered data SHALL be kept in the form.

#### Scenario: Successful creation
- **WHEN** the user enters `title` "About us" and submits the form
- **THEN** the node is saved with the slug `about-us`, the user sees the list of nodes, a success toast, and a new row in the table

#### Scenario: Explicit slug
- **WHEN** the user enters the slug `about` and the `title` "About us"
- **THEN** the node with the slug `about` is saved

#### Scenario: Taken slug
- **WHEN** the user enters a slug that a node of any type or kind already has
- **THEN** the node is not created, an error is shown next to the slug field, and the rest of the entered data remains in the form

#### Scenario: Invalid slug format
- **WHEN** the user enters the slug `About us`
- **THEN** the node is not created, and an error is shown next to the slug field

#### Scenario: Required field
- **WHEN** the user submits the form with an empty `title`
- **THEN** the node is not created, and an error is shown next to the `title` field

#### Scenario: Error of an element of a multiple field
- **WHEN** the multiple numeric field `scores` receives a non-numeric value as its second element
- **THEN** the node is not created, and an error is shown next to the second element of the list

#### Scenario: Value by the settings of the field
- **WHEN** the field has a maximum of 5 in its settings, and 7 is entered
- **THEN** the node is not created, and an error is shown next to the field

#### Scenario: Default publication date
- **WHEN** the user creates a node without changing the publication date
- **THEN** the node is saved with a publication date equal to the moment of creation (to the minute), and without an unpublishing date

#### Scenario: Deferred publication
- **WHEN** the user sets a publication date in the future and saves
- **THEN** the node is saved with this date, and its state in the table is "Scheduled"

#### Scenario: Invalid publication date
- **WHEN** the user enters unrecognizable text into the publication date field
- **THEN** the node is not created, and an error is shown next to the date field

#### Scenario: Creation with an unpublishing date
- **WHEN** the user sets a publication date and a later unpublishing date and saves
- **THEN** the node is saved with both dates

#### Scenario: Unpublishing date not later than the publication date
- **WHEN** the user sets an unpublishing date that equals or precedes the publication date
- **THEN** the node is not created, an error is shown next to the unpublishing date field, and the rest of the entered data remains in the form

#### Scenario: Unpublishing date without a publication date
- **WHEN** the user leaves the publication date empty and sets an unpublishing date in the past
- **THEN** the node is not created, and an error is shown next to the unpublishing date field

#### Scenario: Invalid unpublishing date
- **WHEN** the user enters unrecognizable text into the unpublishing date field
- **THEN** the node is not created, and an error is shown next to the unpublishing date field

#### Scenario: Author is not substituted
- **WHEN** a creation request contains the `user_id` of another user
- **THEN** the current user becomes the author of the node

### Requirement: Saving changes of a node
Submitting the edit form SHALL replace the values of all fields of the type in the language `default` with the entered ones (the order of the elements of a multiple field is kept as in the form; a cleared field SHALL lose its value), and SHALL allow changing the slug of the node to a free admissible value, as well as `is_active`, `published_at`, and `unpublished_at`; the `uuid`, the type of the node, and the author SHALL remain unchanged (a sent `user_id` SHALL be ignored), and a cleared publication date SHALL give a validation error. A cleared unpublishing date SHALL remove it (the node is again without an unpublishing date), and a set one SHALL be later than the publication date. Each successful save SHALL update the `updated_at` of the node, even if only the values of the fields changed. An unchanged slug SHALL not be considered a duplicate of itself. After success the user SHALL remain on the edit form of the node with a success message. The errors SHALL be shown in the same way as on creation. The values of other languages SHALL remain unchanged.

#### Scenario: Changing a value
- **WHEN** the user changes the `title` of a node and saves
- **THEN** the user remains on the edit form, the new `title` is shown in the form and in the list of nodes, and the slug remains the same

#### Scenario: Clearing an optional field
- **WHEN** the user clears the optional field `summary` and saves
- **THEN** the value of `summary` is absent from the node

#### Scenario: Changing the slug
- **WHEN** the user changes the slug of a node to the free `about-us`
- **THEN** the node still opens by the same edit address (by `uuid`), its `uuid` has not changed, and the form of this node open in another tab is preserved without a 404 error

#### Scenario: Saving without changing the slug
- **WHEN** the user saves the form without changing the slug
- **THEN** the saving is successful, and there is no uniqueness error

#### Scenario: Slug of another node
- **WHEN** the user enters a slug that another node has
- **THEN** the changes are not saved, and an error is shown next to the slug field

#### Scenario: Translations
- **WHEN** the node has a translation of `title` in the language `en`, and the user changes `title` in the form
- **THEN** only the value `default` changes, the translation `en` remains

#### Scenario: Disabling a node
- **WHEN** the user turns off the toggle "Show on the site" and saves
- **THEN** the node has `is_active = false`, its publication and unpublishing dates have not changed, and in the table the state is "Disabled"

#### Scenario: Changing the publication date
- **WHEN** the user changes the publication date of a node and saves
- **THEN** the edit form shows the new date after reopening

#### Scenario: Cleared publication date
- **WHEN** the user clears the publication date field and saves
- **THEN** the changes are not saved, and an error is shown next to the date field

#### Scenario: Setting an unpublishing date
- **WHEN** the user sets an unpublishing date later than the publication date and saves
- **THEN** the edit form shows this date after reopening

#### Scenario: End of display
- **WHEN** the user sets an unpublishing date in the past (later than the publication date) for an active node and saves
- **THEN** the state of the node in the table is "Expired"

#### Scenario: Clearing the unpublishing date
- **WHEN** the user clears the unpublishing date field and saves
- **THEN** the node is without an unpublishing date, and the form shows an empty field after reopening

#### Scenario: Unpublishing date not later than the publication date
- **WHEN** the user sets an unpublishing date that equals or precedes the publication date, and saves
- **THEN** the changes are not saved, and an error is shown next to the unpublishing date field

#### Scenario: Author does not change
- **WHEN** an update request contains the `user_id` of another user
- **THEN** the author of the node remains the previous one

#### Scenario: Update time
- **WHEN** the user changes only the value of the field `title` and saves
- **THEN** the `updated_at` of the node is updated

### Requirement: Deleting a node
The "Delete" item in the row menu SHALL open a confirmation dialog that names the node (by `title`, and without it — by the slug), with the buttons "Cancel" and "Delete". The confirmation SHALL delete the node together with all its values in all languages, leave the user on the list of nodes of this type, and show a success message. Cancellation SHALL change nothing. A node of another type SHALL NOT be deleted through the address of this type.

#### Scenario: Confirmation
- **WHEN** the user selects "Delete" in the row of the node "About us" and confirms
- **THEN** the node disappears from the table, a success message is shown, and the values of the node are deleted

#### Scenario: Cancellation
- **WHEN** the user selects "Delete", and then "Cancel"
- **THEN** the dialog is closed, and the node remains

#### Scenario: Node of another type
- **WHEN** a deletion request is sent for the address of the type `news` with the `uuid` of a node of the type `product`
- **THEN** the server responds with 404, and the node remains

### Requirement: Addressing a node in the admin panel
All admin addresses concerning an existing node (edit form, update, deletion) SHALL identify the node by its `uuid`, not by the slug. The slug of a node SHALL be only data of the node for the public site: the admin panel shows it in the table and allows changing it in the form, but does not use it for addressing. An unknown `uuid` or the `uuid` of a node of another type SHALL give 404.

#### Scenario: Address by uuid
- **WHEN** a node has the `uuid` `0190a1b2-…` and the slug `pro-nas`
- **THEN** its edit form opens by `/admin/content-types/news/nodes/0190a1b2-…/edit`, and the address with the slug `pro-nas` instead of the `uuid` responds with 404

#### Scenario: Stability when the slug changes
- **WHEN** the slug of a node changes
- **THEN** the admin addresses of this node remain the same

### Requirement: Node sections for each kind of type
The routes of nodes (list, create, store, edit, update, deletion) SHALL exist in three sections: `/admin/content-types/{key}/nodes…`, `/admin/page-types/{key}/nodes…`, and `/admin/block-types/{key}/nodes…`; each SHALL serve only the types of its kind, and a node SHALL be addressed by the pair (type key, `uuid`). The behavior of the form, saving, editing, and deletion of a node SHALL be the same in all sections, and the redirection after an action SHALL lead to the list of nodes of the same section. The breadcrumbs of the node pages SHALL lead through the sidebar item of the section: "Content Types", "Page Types", or "Block Types".

#### Scenario: Section of pages
- **WHEN** the user opens `/admin/page-types/home/nodes/create` for a type of kind `page`
- **THEN** the create form of a node of this type opens

#### Scenario: Creating a block node
- **WHEN** the user saves the create form at `/admin/block-types/hero/nodes` without a slug
- **THEN** the node is created with a generated slug with the prefix `block-`, and the user is redirected to `/admin/block-types/hero/nodes`

#### Scenario: Block slug without the prefix outside the form
- **WHEN** a creation request of a block node contains an explicit slug without the prefix `block-`
- **THEN** the node is not created, and the error is next to the slug field

#### Scenario: Node of another section
- **WHEN** the user opens `/admin/content-types/hero/nodes/{uuid}/edit` for a node of the type `hero` of kind `block`
- **THEN** the server responds with 404

#### Scenario: Breadcrumbs
- **WHEN** the user opens the create form of a node at `/admin/page-types/home/nodes/create`
- **THEN** the breadcrumbs are: "Admin" → "Page Types" (link) → "Create node"

#### Scenario: Content breadcrumbs
- **WHEN** the user opens the create form of a node at `/admin/content-types/news/nodes/create`
- **THEN** the breadcrumbs are: "Admin" → "Content Types" (link) → "Create node"

### Requirement: "Image" and "file" fields in the form and in the node list
For a field of type `image` or `file` the node form SHALL show a file upload widget with the profile of the field's type (`image` — images, `file` — documents) instead of a separate choice from the media library: the user uploads a file, and the form saves the value `{uuid}.{extension}`. The widget SHALL take the allowed types and the maximum size from the settings of the field, reject a non-matching file before uploading, and show the reason; the server SHALL check them regardless of the widget. The `image` widget SHALL show a preview of the image, the `file` widget — an icon and the file name. The edit form SHALL show the saved file and allow replacing or clearing it. A multiple field SHALL be edited as a list of widgets by the general rules of multiple fields; the file block in a row of a multiple field SHALL NOT have a separate button to delete the file — the file is removed by deleting the row. The file block of a single field SHALL keep the button to delete the file. In the node table the value of an `image` or `file` field SHALL be output by the file name (the original one, and without it — the stored value). Saving the node SHALL bind the chosen files to the node, and clearing or replacement SHALL release them without deleting the files from the storage.

#### Scenario: Upload in the form
- **WHEN** the user in the node form chooses a file in the field `file` (or an image in the field `image`) and saves the node
- **THEN** the node is saved with the value `{uuid}.{extension}`, and the file in the media library has one usage

#### Scenario: File outside the settings of the field
- **WHEN** the field `image` allows only PNG, and the user chooses a JPEG
- **THEN** the widget shows an error, the file does not become the value of the form, and if the request bypasses the widget, the server rejects the file

#### Scenario: Display of a saved image
- **WHEN** the user opens the edit form of a node with a file in the field `image`
- **THEN** they see the preview of the image and the file name

#### Scenario: Display of a saved file
- **WHEN** the user opens the edit form of a node with a file in the field `file`
- **THEN** they see an icon and the file name without a preview of the image

#### Scenario: Replacing a file
- **WHEN** the user replaces the file in the field `file` with another and saves the node
- **THEN** the node references the new file, and the old file remains in the media library with the usage count 0

#### Scenario: Clearing
- **WHEN** the user clears the field `file` or `image` and saves the node
- **THEN** the value is absent, and the file remains in the media library

#### Scenario: Deleting a file from a multiple field
- **WHEN** the user deletes a row with a file in the multiple field `image`
- **THEN** the row disappears from the list, and inside the file block there is no separate button to delete the file

#### Scenario: Clearing a single field
- **WHEN** the user opens the form of a node with a file in the single field `file`
- **THEN** the file block has a button to delete the file, and it clears the field

#### Scenario: File in the node table
- **WHEN** the field `file` is visible in the list, and the node has a file
- **THEN** the cell shows the file name

#### Scenario: Deleting a node
- **WHEN** a node with a file is deleted
- **THEN** the file remains in the media library, and its usage count decreases

### Requirement: Container of each field of the node form
The create and edit forms of a node SHALL place each field of the type in its own container with a frame, whose caption (legend) is the name of the field with the requiredness mark, styled the same way as the file settings block in the field form. A boolean field SHALL show a checkbox with a label inside the frame. Hints and error messages SHALL remain inside the container of their field. The buttons "Save" and "Cancel" SHALL remain outside the containers, in the sticky action bar at the bottom of the window. The containers do not change the order of fields, the requiredness marks, and the behavior of multiple fields. The service column, unlike the fields of the type, SHALL be one block "Details" (not separate frames per attribute).

#### Scenario: Each field in a frame
- **WHEN** the user opens the create form of a node of a type with the fields `title` and `body`
- **THEN** `title` and `body` are each placed in its own container with a frame and a caption with the name, and the buttons "Cancel" and "Save" are in the sticky panel at the bottom of the window

#### Scenario: Errors inside the container
- **WHEN** the user saves the form with an empty required field
- **THEN** the error message is shown inside the container of this field

#### Scenario: Boolean field
- **WHEN** the type has a boolean field
- **THEN** its checkbox and label stand in one row inside the frame

### Requirement: Multiple upload in multiple "image" and "file" fields
A multiple field of type `image` or `file` SHALL allow choosing or dragging several files at once and SHALL NOT have an "Add item" button: new items appear only by uploading files. Each chosen file SHALL be uploaded by a separate request, and each successfully uploaded file SHALL be added to the end of the list as a new item with the value `{uuid}.{extension}`. The files SHALL be checked by the settings of the field (the allowed types, the maximum size) in the same way as a single file; a rejected file does not become an item of the list, and the reason is shown with its name. The items added in this way SHALL be edited by the general rules of multiple fields: deletion, changing the order. Other multiple fields (text, number, and so on) and single fields do not change their behavior; the "Add item" button in non-file multiple fields remains.

#### Scenario: Several files at once
- **WHEN** the user in a multiple field `image` chooses three admissible images and saves the node
- **THEN** three items with previews appear in the list in the order of choice, and the node is saved with three values, and each file in the media library has one usage

#### Scenario: Adding to existing ones
- **WHEN** a multiple field `file` already has two files, and the user chooses two more
- **THEN** the list has four items, the new ones at the end

#### Scenario: Some files rejected
- **WHEN** the field allows only PNG, and the user chooses two PNG and one JPEG
- **THEN** two items appear in the list, and the error is named by the name of the JPEG file

#### Scenario: No "Add item" button
- **WHEN** the user opens the node form with a multiple field `image` or `file`
- **THEN** there is no "Add item" button in this field, and the zone for choosing several files is present

#### Scenario: Another multiple field
- **WHEN** the user opens the node form with a multiple string field
- **THEN** the "Add item" button is present and adds an empty item

#### Scenario: Single field
- **WHEN** the field `file` is not multiple
- **THEN** the widget accepts one file, as before

### Requirement: Name and description of a file in the node form
The create and edit forms of a node SHALL for each saved file of an `image` or `file` field (in a single field and in each row of a multiple one) show in the file block the button "Edit file data", which opens a modal window with the editable fields "Name" (the original file name) and "Description". The editable fields themselves SHALL not be displayed in the file block permanently, but the block SHALL show the current name and description as text: the name, under it the description (if it is non-empty), and under the description the file size. The name and description in the block SHALL show the current state of the form: after "Done" in the window the block shows the applied name and description at once, without saving the node; "Cancel" does not change the block. In a row of a multiple field the button SHALL stand in the place where the former internal cross of deleting the file was (the file block in the row has no own deletion); in a single field the button SHALL stand next to the button to delete the file. The window SHALL have the buttons "Done" (apply the entered data to the form and close) and "Cancel" (close, discarding the entered data; also Escape and closing from outside). The applied data SHALL be saved together with the record of the values: either everything is saved or nothing; nothing is saved before the node's "Save" is pressed. The name SHALL be a non-empty string up to 255 characters; the description — an optional text, an empty description is stored as absent. A validation error SHALL be shown next to the file block (and in the modal window next to the corresponding field) without losing the entered data. The change SHALL concern only the files referenced by the values of this node; the extension of the saved file and the file itself on the disk do not change. The name and description belong to the file: after saving they are the same everywhere the file is used.

#### Scenario: Fields do not take space in the block
- **WHEN** the user opens the node form with a file in the field `image`
- **THEN** the file block has no input fields "Name" and "Description", only the button "Edit file data"

#### Scenario: Description above the size
- **WHEN** the user opens the edit form of a node with a file that has the description `Front cover`
- **THEN** the file block shows the name, under it the text `Front cover`, and under it the file size

#### Scenario: File without a description
- **WHEN** the user opens the node form with a file without a description
- **THEN** the file block shows the name and the size, and there is no empty description line

#### Scenario: Block is updated after "Done"
- **WHEN** the user in the window changes the name to `Cover`, enters the description `Front page`, and clicks "Done" without saving the node
- **THEN** the file block shows at once the name `Cover` and the description `Front page` above the size

#### Scenario: Cleared description disappears from the block
- **WHEN** the user erases the description in the window and clicks "Done"
- **THEN** the file block no longer shows the description line

#### Scenario: Cancellation does not change the block
- **WHEN** the user changes the name and the description in the window and clicks "Cancel"
- **THEN** the file block shows the previous name and description

#### Scenario: Description in a row of a multiple field
- **WHEN** the user changes the file description in a row of a multiple field and clicks "Done"
- **THEN** only the block of this row shows the new description above the size

#### Scenario: Just uploaded file
- **WHEN** the user uploads a file into the field `image`
- **THEN** the block shows the name and the size of the file without a description line

#### Scenario: Button in a row of a multiple field
- **WHEN** the user looks at a row of the multiple field `image`
- **THEN** there is no inner cross in the file block, and the button "Edit file data" stands in its place

#### Scenario: Opening the window
- **WHEN** the user clicks "Edit file data"
- **THEN** a modal window opens with the fields "Name" and "Description" filled with the current values

#### Scenario: Changing the name
- **WHEN** the user in the window changes the file name from `IMG_0042.jpg` to `Cover`, clicks "Done", and then saves the node
- **THEN** the node is saved, the reopened form and the table of nodes show the name `Cover`, and the file extension on the disk remains `jpg`

#### Scenario: Adding a description
- **WHEN** the user enters a file description in the window, clicks "Done", and saves the node
- **THEN** after reopening the window the description is shown in the field "Description", and in the file block above the size

#### Scenario: Clearing a description
- **WHEN** the user erases the description in the window, clicks "Done", and saves the node
- **THEN** the file has no description, and the field "Description" is empty

#### Scenario: Cancellation
- **WHEN** the user changes the name in the window and clicks "Cancel"
- **THEN** the window is closed, the name in the form has not changed, and with the saving of the node it does not change

#### Scenario: Empty name
- **WHEN** the user erases the name of the file in the window, clicks "Done", and saves the node
- **THEN** the saving is rejected, an error is shown next to the file block (and in the window next to the "Name" field), and nothing (neither the values of the node nor the metadata) is changed

#### Scenario: File in another node
- **WHEN** the file is used in another node as well, and the user changes its name
- **THEN** the other node and the media library show the new name

#### Scenario: File that is not in the values of the node
- **WHEN** a saving request contains the metadata of a file that is not referenced by the values of this node
- **THEN** the metadata of this file do not change

#### Scenario: Name and description on creation
- **WHEN** the user in the create form of a node uploads a file, changes its name in the window, enters a description, clicks "Done", and saves the node
- **THEN** the node is created, and the file has the new name and description

#### Scenario: Error on creation
- **WHEN** the user in the create form erases the name of the uploaded file in the window and saves the node
- **THEN** the node is not created, an error is shown next to the file block, and the entered data are kept

### Requirement: Description of a field in the node form
If a field of a content type has a description, the create and edit forms of a node SHALL show it right after the caption (legend) of the field, inside the container of the field, as helper text. This applies to single, multiple, and boolean fields. A field without a description SHALL have no empty line under its caption.

#### Scenario: Field with a description
- **WHEN** the field `title` has the description "Short title up to 60 characters", and the user opens the node form
- **THEN** this description is shown under the caption of the field

#### Scenario: Field without a description
- **WHEN** the field has no description
- **THEN** nothing additional is shown under the caption of the field

#### Scenario: Boolean field
- **WHEN** a boolean field has a description
- **THEN** the description is shown in the frame of this field next to the checkbox and the label

### Requirement: Item limit of a multiple field in the node form
The node form SHALL respect the maximum number of elements of a multiple field, if it is greater than 0. The counter "count / limit" SHALL be shown in one row with the caption of the field (next to it, not under it). When there are as many elements as allowed, the "Add item" button (in non-file fields) SHALL be unavailable, and the zone of multiple file upload (`image`, `file`) SHALL NOT be shown; instead, an explanation of the reached limit SHALL be shown; after deleting an element the zone SHALL appear again. If the user chooses more files than there are free places, only the first files in the order of choice within the free places SHALL be uploaded, and the rest SHALL be rejected with a message that names the limit. A field without a limit (`0`) SHALL behave as before. The server SHALL reject the excess regardless of the form, and the error SHALL be shown next to the field.

#### Scenario: Counter
- **WHEN** the user opens the node form with a multiple field with the limit 3 and two elements
- **THEN** "2 / 3" is visible next to the caption of the field

#### Scenario: Counter in one row with the caption
- **WHEN** a multiple field has a limit, including a required field with the mark "*"
- **THEN** the caption and the counter stand in one row, the counter does not wrap under the caption

#### Scenario: Limit reached in a string field
- **WHEN** a multiple string field with the limit 2 already has two elements
- **THEN** the "Add item" button is unavailable

#### Scenario: Limit reached in an image field
- **WHEN** a multiple `image` field with the limit 2 already has two images
- **THEN** the upload zone is absent, and instead an explanation that the limit is reached is shown

#### Scenario: More files chosen than places
- **WHEN** an `image` field with the limit 3 already has one image, and the user chooses four admissible files
- **THEN** only the first two are uploaded, the list has three items, and the rest is named in the limit message

#### Scenario: Deletion frees a place
- **WHEN** the limit is reached, and the user deletes one element
- **THEN** adding is available again, and in the image or file field the upload zone appears again

#### Scenario: Without a limit
- **WHEN** the field has the limit 0
- **THEN** there is no counter with a limit, and adding is allowed without restriction

#### Scenario: Server rejects the excess
- **WHEN** a saving request bypasses the form and sends more elements than the limit
- **THEN** the saving is rejected, and an error is shown next to the field

### Requirement: Rich text field in the node form and the node list
For a field of type `richtext` the node form SHALL show a formatted text editor instead of a plain text field: a toolbar with headings, bold, italic, underline, strikethrough, alignment (a dropdown: left, center, right, justify), text color, background color, bulleted and numbered lists, a link, an image button (inserting a file, and for a selected image — its options), inserting a YouTube video, inserting a frame, choosing emoji, a button to clear formatting, a button to view the raw HTML ("Source"), and a button to expand to full screen; the editing area SHALL show the formatting while typing. Inserting files other than images in the editor SHALL be unavailable. The edit form SHALL be filled with the saved value with its formatting. The editor SHALL have an accessible name with the caption of the field, an error mark from the server next to the field, and in a multiple field — a separate editor in each row. Saving the node SHALL send the HTML of the value; an empty editor SHALL send an empty value. In the node table the value of `richtext` SHALL be output as plain text without tags, shortened to a reasonable length, not as raw HTML.

#### Scenario: Formatting in the form
- **WHEN** the user in the editor of a `richtext` field types text, makes it bold, adds a bulleted list, and saves the node
- **THEN** the node is saved, and after reopening the edit form the bold text and the list are visible in the editor

#### Scenario: Link
- **WHEN** the user selects text, adds the link `https://example.com`, and saves the node
- **THEN** after reopening the edit form the text is a link to this address

#### Scenario: No other files
- **WHEN** the user opens the toolbar of the editor
- **THEN** it has only the action of inserting an image, and there are no actions for inserting other files (documents)

#### Scenario: Empty value
- **WHEN** the user leaves the editor of an optional `richtext` field empty and saves the node
- **THEN** the node is saved without a value in this field

#### Scenario: Error of a field
- **WHEN** the server rejects the value of `richtext`
- **THEN** the error is shown next to the editor, and the entered content is kept in the form

#### Scenario: Value in the node table
- **WHEN** a `richtext` field with the value `<p>Hi, <strong>world</strong></p>` is visible in the list
- **THEN** the cell shows "Hi, world" without tags

### Requirement: Extended actions of the formatted text editor
The `richtext` editor SHALL provide the following actions; each button SHALL have an accessible name, and the state buttons (underline, strikethrough, source, full screen) SHALL mark the active state via `aria-pressed`.
- Underline and strikethrough SHALL toggle the formatting of the selected text.
- Alignment SHALL apply to the block (paragraph, heading) with the cursor; the alignment button SHALL show the current alignment (left, if the block is not aligned), and the current item SHALL be marked in the menu.
- The text color and the background color SHALL open a palette with ready colors and a field for the code `#rrggbb`, apply the color to the selected text, and have the action "Reset color".
- Clearing the formatting SHALL remove from the selected text all marks except bold, italic, underline, and the link, remove alignment, color, and background, and the headings (`h2`–`h4`) SHALL remain headings.
- Inserting a YouTube video SHALL accept the address of the video (`youtube.com/watch?v=`, `youtu.be/`) and insert an embedded player; an invalid address SHALL give an error next to the field without insertion.
- Inserting a frame SHALL accept in one field both an `https` address and the whole `<iframe>` code (from it `src`, `width`, `height`, `title`, `allowfullscreen`, `allow` SHALL be carried over, and the other attributes discarded), and insert the frame only from the allowed host (and path prefix), in particular Google Drive and social networks; an address from a disallowed host SHALL give an error next to the field without insertion.
- The choice of an emoji SHALL insert the character at the cursor position and leave the focus in the editor.
- The button "Source" SHALL show the raw HTML of the value in a text field instead of the formatted text, apply its edits to the editor, and disable the other formatting buttons while the mode is on; returning SHALL show the edited content.
- Expanding to full screen SHALL stretch the editor with the toolbar to the whole window, return with the button or the `Escape` key, and SHALL not lose the content and the cursor.

#### Scenario: Underline and strikethrough
- **WHEN** the user selects text and clicks "Underline", then "Strikethrough", and saves the node
- **THEN** after reloading the text is underlined and struck through

#### Scenario: Alignment
- **WHEN** the user places the cursor in a paragraph and clicks "Center"
- **THEN** the paragraph is centered, "Center" is marked in the menu, and the saved HTML contains `text-align: center`

#### Scenario: Text and background color
- **WHEN** the user selects text and chooses a text color and a background color
- **THEN** the text is shown with the chosen colors, and after saving and reloading the colors are saved

#### Scenario: Resetting the color
- **WHEN** the user selects text with a color and chooses "Reset color"
- **THEN** the text is shown in the default color

#### Scenario: Clearing the formatting
- **WHEN** the user selects bold underlined text with a color, a background, and alignment in a heading and clicks "Clear formatting"
- **THEN** the bold, the underline, and the heading remain, and the color, the background, and the alignment disappear

#### Scenario: Inserting a YouTube video
- **WHEN** the user inserts the address `https://youtu.be/dQw4w9WgXcQ`
- **THEN** a player appears in the editor, and in the saved HTML there is an `iframe` with the address `youtube-nocookie.com/embed/dQw4w9WgXcQ`

#### Scenario: Invalid video address
- **WHEN** the user enters an address that is not a YouTube address
- **THEN** an error is shown next to the field, and the content of the editor does not change

#### Scenario: Inserting a frame by a link
- **WHEN** the user inserts the `https` address of an allowed host
- **THEN** a frame appears in the editor and is saved in the node

#### Scenario: Inserting a frame with iframe code
- **WHEN** the user inserts `<iframe src="https://www.youtube-nocookie.com/embed/abc" width="560" height="315" title="Video" allow="autoplay; camera" frameborder="0" allowfullscreen></iframe>`
- **THEN** a frame appears in the editor, and the saved `iframe` has `src`, `width="560"`, `height="315"`, `title`, `allowfullscreen`, `allow="autoplay"`, and has neither `frameborder` nor `camera`

#### Scenario: Google Drive frame
- **WHEN** the user inserts the address `https://drive.google.com/file/d/abc/preview`
- **THEN** a frame appears in the editor and is saved in the node

#### Scenario: Google Forms frame
- **WHEN** the user inserts the embed code of Google Forms ("Embed" code `<iframe src="https://docs.google.com/forms/d/e/abc/viewform?embedded=true">`)
- **THEN** the form appears in the editor, and the node stores a frame with this address

#### Scenario: Disallowed frame
- **WHEN** the user inserts an address of a host that is not in the allowed list
- **THEN** an error is shown next to the field, and the frame is not inserted

#### Scenario: Emoji
- **WHEN** the user chooses an emoji in the panel
- **THEN** the character is inserted at the cursor position, and the focus remains in the editor

#### Scenario: Raw HTML
- **WHEN** the user turns on "Source", changes the HTML in the field, and turns off "Source"
- **THEN** the changed content is visible in the editor, and while the mode was on, the formatting buttons were disabled

#### Scenario: Full screen mode
- **WHEN** the user clicks "Full screen"
- **THEN** the editor takes the whole window, the text and the cursor are preserved, and the `Escape` key returns the normal view

### Requirement: Inserting an image into the formatted text editor
The `richtext` editor SHALL have the button "Image" with an accessible name. When an image is selected in the text, the button SHALL open the image options dialog; otherwise it SHALL open the file choice. The selected image SHALL have a noticeable visual selection (an outline), so that it is visible that it is selected. The file choice SHALL upload the file at once, as in the widget of the `image` field (by the rules of the `image` profile), and insert the image at the cursor position. The address of the image in the text SHALL be relative, without protocol and domain (`/storage/{uuid}.{extension}`). The file SHALL appear in the media library immediately after the upload. While uploading, the button SHALL be disabled; an upload error (an unsupported type, an oversized file, a server error) SHALL be shown next to the editor, and the content SHALL remain unchanged. In "Source" mode the button SHALL be disabled together with the other formatting buttons. A saved image SHALL be shown in the editor after reopening the form. An image SHALL be selected only by a user action (a click, an insertion, a double click): on opening the form and after the update of the value no image is selected, even if the text begins with an image.

#### Scenario: Inserting an image
- **WHEN** the user places the cursor in the text, clicks "Image", and chooses a PNG
- **THEN** the file is uploaded, the image appears at the cursor position, and in "Source" its `src` has the form `/storage/{uuid}.png` without `http` and domain

#### Scenario: Saving and reopening
- **WHEN** the user inserts an image and saves the node, and then opens the edit form
- **THEN** the image is visible in the editor

#### Scenario: File in the media library
- **WHEN** the user inserts an image and saves the node
- **THEN** this file is in the media library with the usage count 1, and the details of the file indicate the node, the field, and the language

#### Scenario: Unsupported file
- **WHEN** the user chooses a file whose type is not allowed by the `image` profile
- **THEN** an error is shown next to the editor, the image is not inserted, and the content is not changed

#### Scenario: "Source" mode
- **WHEN** the user turns on "Source"
- **THEN** the button "Image" is disabled

#### Scenario: Selecting an image
- **WHEN** the user clicks an image in the editor
- **THEN** the image has a visible selection outline, and after a click elsewhere the outline is absent

#### Scenario: Image is not selected after opening
- **WHEN** the user opens the node form whose text begins with an image
- **THEN** the image has no selection outline, the options dialog is not open, and the button "Image" opens the file choice

#### Scenario: Button with a selected image
- **WHEN** an image is selected and the user clicks "Image"
- **THEN** the dialog "Image options" opens, the file choice does not open

#### Scenario: Button without a selected image
- **WHEN** no image is selected and the user clicks "Image"
- **THEN** the file choice opens

### Requirement: Image options in the formatted text editor
A double click on an image in the `richtext` editor SHALL open the dialog "Image options" (the same as the button "Image" with a selected image); there is no separate action in the toolbar. The dialog SHALL be modal, with the accessible name "Image options", with the fields "Width (px)", "Alternative text", and "Caption" and the buttons "Done" and "Original size"; the current values of the selected image SHALL be in the fields. "Done" SHALL apply the values to the image. The width SHALL be an integer number of pixels from 1 to 4000; another value SHALL give an error next to the field without applying. An empty width or "Original size" SHALL remove the width (the image is shown in its natural size, the height is proportional). The alternative text by default SHALL equal the name of the uploaded file. A non-empty caption SHALL be shown under the image in the editor and saved as a visible caption; an empty caption SHALL remove it. The saved width, alternative text, and caption SHALL be restored after reopening the form.

#### Scenario: Changing the width
- **WHEN** the user double-clicks an inserted image, enters the width 200 in the dialog "Image options", clicks "Done", and saves the node
- **THEN** the image in the editor has the width 200 px, and after reopening the form the width remains 200 (in "Source" `width="200"`)

#### Scenario: Invalid width
- **WHEN** the user enters the width `0`, `5000`, or `abc`
- **THEN** an error is shown next to the field, and the width of the image does not change

#### Scenario: Original size
- **WHEN** the user double-clicks an image with the width 200 and clicks "Original size"
- **THEN** the width is removed, and in "Source" there is no `width`

#### Scenario: Caption
- **WHEN** the user enters the caption "Our office", clicks "Done", and saves the node
- **THEN** "Our office" is visible under the image, in "Source" the image is in a `figure` with a `figcaption`, and after reopening the form the caption is in place

#### Scenario: Alternative text
- **WHEN** the user inserts an image from the file `logo.png`, and then changes the alternative text to "Logo"
- **THEN** first `alt` equals `logo.png`, after "Done" — "Logo"

#### Scenario: Clearing the caption
- **WHEN** the user clears the caption of an image and clicks "Done"
- **THEN** the image remains without `figure` and `figcaption`

#### Scenario: Double click
- **WHEN** the user double-clicks an image with the width 200 and the caption "Our office"
- **THEN** the dialog "Image options" opens, the fields contain 200, the current `alt`, and "Our office"

#### Scenario: No separate action in the toolbar
- **WHEN** the user looks at the toolbar
- **THEN** there is no separate action "Image options"

### Requirement: Alignment of an image in the formatted text editor
When an image is selected in the `richtext` editor, the existing alignment menu SHALL apply the choice to the image: "Left", "Center", and "Right"; "Justify" SHALL be disabled. The caption SHALL always be centered right under the image (and not centered in the editing area), regardless of the alignment of the image: the `figure` SHALL have the width of the image (`width: {width}px; max-width: 100%`, and without a set width — `width: fit-content`), so that a long caption wraps within the width of the image and does not squeeze it; it SHALL be aligned by margins (`margin-left`/`margin-right: auto`) and the caption SHALL be centered inside (`text-align: center`). The alignment SHALL be saved in standard HTML: an inline style on `figure` (center — `margin-left: auto; margin-right: auto`, right — `margin-left: auto`, left — without margins), and "Left" without a caption SHALL return the image to a bare `img`; a previously saved `figure` with only `text-align: center|right` SHALL be read as center/right. Without a selected image the menu SHALL work as before. The saved alignment SHALL be restored after reopening the form.

#### Scenario: Center
- **WHEN** the user selects an image, chooses "Center", and saves the node
- **THEN** the image in the editor is centered, in "Source" it is in a `figure` with `margin-left: auto; margin-right: auto`, and after reopening the form it remains centered

#### Scenario: Image with a caption
- **WHEN** an image with the caption "Our office" is selected and "Center" is chosen
- **THEN** both the image and the caption are centered

#### Scenario: The caption does not follow the image
- **WHEN** an image with a caption is selected and "Right" or "Left" is chosen
- **THEN** the image stands on the right (left), and the caption is centered right under the image (the center of the caption matches the center of the image)

#### Scenario: Set width with a long caption
- **WHEN** an image has the width 500 and a caption with a long word or a long sentence
- **THEN** the width of the image remains 500 (it is not squeezed to the longest word), and the caption wraps over lines within this width

#### Scenario: Right
- **WHEN** an image is selected and "Right" is chosen
- **THEN** the image stands on the right, and in "Source" the `figure` has `margin-left: auto`

#### Scenario: Return to the left
- **WHEN** a centered image without a caption is selected and "Left" is chosen
- **THEN** in "Source" the image is again a bare `img` without `figure` and `style`

#### Scenario: "Justify" is unavailable
- **WHEN** an image is selected and the alignment menu is open
- **THEN** the item "Justify" is disabled

#### Scenario: The image itself is aligned
- **WHEN** an image without a caption is selected and "Center" (then "Right") is chosen
- **THEN** the image itself (and not only the caption) is visually centered (on the right) in the editing area

### Requirement: Width of the node form
The create and edit forms of a node SHALL have the maximum width `max-w-5xl` (1024px), so that the `richtext` editor and the fields are not cramped.

#### Scenario: Wide screen
- **WHEN** the user opens the node form on a screen wider than 1100px
- **THEN** the form takes a width more than 672px, but not more than 1024px

#### Scenario: Long caption
- **WHEN** an image has a caption longer than the width of the image
- **THEN** the caption wraps over lines within the width of the image, the `figure` does not become wider than the image, and the center of the caption matches the center of the image

#### Scenario: Default width
- **WHEN** the user inserts an image
- **THEN** it is inserted with the width 500 px (`width="500"` in "Source"), and in the options dialog the field "Width" shows 500

#### Scenario: Original size after insertion
- **WHEN** the user clicks "Original size" for a freshly inserted image
- **THEN** `width` is removed, and the image has its natural size

### Requirement: Input element of the "YouTube video" field
The create and edit forms of a node SHALL show, for a field of type `youtube`, the list of added videos and the button "Add video" (for a single-value field the button disappears when there is already a video). Each video SHALL be a card with a screenshot (a preview), a title and a description, and the buttons "Edit" and "Delete". The title SHALL be a direct link to the video (the `url` of the value), opening in a new tab (`target="_blank"`, `rel="noopener noreferrer"`); if there is no title, the text of the link SHALL be the address itself. Adding and editing SHALL take place in a modal window with the link field, the button "Fetch data", the editable fields of title and description, and the block of the screenshot. The changes from the modal window SHALL get into the node form only after confirmation ("Done"); "Cancel" or closing the window SHALL discard the edits. The button "Fetch data" SHALL be inactive while the link is empty. After successful data fetching, the window SHALL substitute the title, the description, and the screenshot, overwriting the current ones, and show the preview of the screenshot. The user SHALL be able to change the title and the description, replace the screenshot with another image of the `image` profile, or delete it. A change of the link itself SHALL NOT overwrite the already entered title, description, and screenshot, until the user presses "Fetch data" again. For a multiple field the list SHALL contain several cards by the general rules of the form, each with its own link and data. The server errors SHALL be shown in the modal window next to the field, and the entered data SHALL be kept. The saving of the node itself SHALL work as before.

#### Scenario: Card in the list
- **WHEN** the form has a video with a screenshot, a title, and a description
- **THEN** the list shows a card with the screenshot, the title-link, and the description

#### Scenario: The title opens the video
- **WHEN** the user clicks the title of a card
- **THEN** the link to the video opens in a new tab, and the node form remains open

#### Scenario: Modal window for adding
- **WHEN** the user clicks "Add video"
- **THEN** a modal window opens with an empty video form, and after "Done" a new card appears in the list

#### Scenario: Modal window for editing
- **WHEN** the user clicks "Edit" on a card
- **THEN** the window opens with the data of this video, and after "Done" the card shows the changed data

#### Scenario: Cancellation in the modal window
- **WHEN** the user changes the data in the window and clicks "Cancel" or closes the window
- **THEN** the card and the value of the form remain as they were

#### Scenario: Fetching data
- **WHEN** the user enters the link to an available video in the window and clicks "Fetch data"
- **THEN** the title, the description, and the screenshot are substituted into the window, the preview of the screenshot is visible, and the node is not yet saved

#### Scenario: Editing the fetched data
- **WHEN** the user changes the title and the description, clicks "Done", and saves the node
- **THEN** the changed title and description are saved, and after reloading the page they are shown on the card

#### Scenario: Replacing the screenshot
- **WHEN** the user in the window uploads an own image instead of the screenshot, clicks "Done", and saves the node
- **THEN** after reloading the card shows the new image

#### Scenario: Deleting the screenshot
- **WHEN** the user in the window deletes the screenshot, clicks "Done", and saves the node
- **THEN** the value is saved without a screenshot, and the title and the description remain

#### Scenario: Deleting a video from the list
- **WHEN** the user clicks "Delete" on a card and saves the node
- **THEN** the video is absent from the saved value

#### Scenario: Button without a link
- **WHEN** the link field in the window is empty
- **THEN** the button "Fetch data" is inactive

#### Scenario: Changing the link does not erase edits
- **WHEN** after manual edits the user changes the link without clicking "Fetch data"
- **THEN** the title, the description, and the screenshot remain as they were

### Requirement: Fetching video data by a link
The admin panel SHALL provide an authenticated user with a verified email with a request that, by a link to a YouTube video, reads from the YouTube Data API the title, the description, and the largest available screenshot, saves the screenshot to the media library as a file of the `image` profile, and responds with the links to the video (the entered one, and for Shorts — the regular one), the title, the description, and the screenshot. A link to Shorts SHALL be converted to a regular link to the video, and the data SHALL be read by it. The request SHALL NOT change any node or the values of fields. The API key SHALL be taken from the configuration of the application and SHALL NOT get into the response or to the client. If the video is unavailable, the request SHALL NOT create a file and SHALL NOT change the state of the form, and the response SHALL contain a message that distinguishes the reasons: the link does not look like a YouTube video; the video is not found or is private; the API key is not configured; the YouTube service did not respond or the quota is exhausted. A failure to download the screenshot SHALL be treated as an unavailable video, without a partial result.

#### Scenario: Successful fetching
- **WHEN** the user sends a link to a public video
- **THEN** the response contains the entered link, the title, the description, and the screenshot with a link to the saved file, and this file appears in the media library

#### Scenario: Link to Shorts
- **WHEN** the user sends `https://www.youtube.com/shorts/{id}` of a public video
- **THEN** the response contains the regular link `https://www.youtube.com/watch?v={id}` and the data of this video

#### Scenario: Link not to a video
- **WHEN** a text that does not look like a YouTube link is sent
- **THEN** the request is rejected with a message about an invalid link, YouTube is not contacted, and no files are created

#### Scenario: Video not found
- **WHEN** the link is valid, but YouTube does not return a video (deleted or private)
- **THEN** the response contains a message that the video is unavailable, no files are created, and the data in the form do not change

#### Scenario: Key not configured
- **WHEN** the API key is absent from the configuration
- **THEN** the response contains a message that the YouTube integration is not configured, and YouTube is not contacted

#### Scenario: YouTube service unavailable
- **WHEN** YouTube does not respond or responds with a quota or access error
- **THEN** the response contains a message about the temporary unavailability of the service, and no files are created

#### Scenario: Access without logging in
- **WHEN** an unauthenticated user sends the request
- **THEN** the request is not executed, as with other admin requests

#### Scenario: Key is not on the client
- **WHEN** any request of data fetching is executed
- **THEN** the API key is absent from the response and from the page data

### Requirement: Display of the "YouTube video" field in the node table
The node table SHALL, for a field of type `youtube`, show the title of the video, and if there is none — the link to the video. For a multiple field the values SHALL be shown separated by commas, as for other types. The column SHALL NOT be sortable.

#### Scenario: Display of the title
- **WHEN** the column of a `youtube` field with the title of the video is visible in the node table
- **THEN** the cell contains the text of the title, not a link and not JSON

#### Scenario: Display without a title
- **WHEN** the video has no title
- **THEN** the cell contains the link to the video

### Requirement: Two-column layout of the node form
On screens from `lg` (1024 px) wide the create and edit forms SHALL have two columns of roughly 70/30: on the left — the fields of the type, on the right — the block "Details", which stays visible when the long form is scrolled. The columns SHALL share one form: sending saves both the fields of the type and the service attributes in one request. Wide elements (the `richtext` editor, the file widgets, the `youtube` card) SHALL NOT stretch the left column and SHALL NOT overlap the right one.

#### Scenario: Desktop
- **WHEN** the user opens the node form in a window 1280 px wide
- **THEN** the fields of the type are placed on the left, the block "Details" on the right, and both are visible at the same time

#### Scenario: Scrolling
- **WHEN** the user scrolls a long form down on a desktop
- **THEN** the block "Details" remains visible in the window

#### Scenario: Wide youtube field
- **WHEN** a node contains a `youtube` field (single or multiple) with a long title and description, and the window is 1280 px wide
- **THEN** the field container does not go beyond the left column, and the block "Details" is not overlapped by anything

#### Scenario: One request
- **WHEN** the user changes `title` and the status, and then clicks "Save"
- **THEN** both changes are saved by one submission of the form

### Requirement: Details of a node in the form
The block "Details" SHALL contain, in this order: the toggle "Show on the site" (`is_active`; enabled in the create form), the badge of the current state of the node, the field "Publication date" (`published_at`, a date and time selection; in the create form filled with the current moment), the field "Show until" (`unpublished_at`, a date and time selection; may be empty, in the create form empty), the slug field (with the same hints and rules as before), "Updated" (`updated_at`, read-only, only in the edit form), and "Author" (the name of the author, read-only; in the create form — the current user). The creation date (`created_at`) SHALL NOT be shown. The badge SHALL display the state by the current state of the form (before saving): a disabled toggle gives "Disabled", an enabled one with a future publication date — "Scheduled", an enabled one with an unpublishing date not later than the current moment (and a publication date not in the future) — "Expired", otherwise — "Published". The editable fields SHALL show validation errors inside the block.

#### Scenario: Service block of creation
- **WHEN** the user opens the create form
- **THEN** in the block the toggle "Show on the site" is enabled, the publication date is filled with the current moment, the field "Show until" is empty, the slug is empty, there is no "Updated", and the current user is shown as the author

#### Scenario: Service block of editing
- **WHEN** the user opens the edit form of a node created by another user
- **THEN** "Author" shows that user, and "Updated" shows the time of the last change of the node; both are not editable

#### Scenario: Badge reacts to the form
- **WHEN** the user turns off the toggle "Show on the site"
- **THEN** the badge shows "Disabled" at once, even though the node is not yet saved

#### Scenario: Badge "Scheduled"
- **WHEN** the user enters a publication date in the future with the toggle enabled
- **THEN** the badge shows "Scheduled"

#### Scenario: Badge "Expired"
- **WHEN** the user enters an unpublishing date in the past with the toggle enabled
- **THEN** the badge shows "Expired"

#### Scenario: Field "Show until"
- **WHEN** the user opens the edit form of a node with an unpublishing date
- **THEN** the field "Show until" is filled with this date, and after clearing the field and saving the node remains without an unpublishing date

#### Scenario: Error of the unpublishing date
- **WHEN** the server rejects the unpublishing date
- **THEN** the error is shown inside the block next to the field "Show until"

#### Scenario: Creation date
- **WHEN** the user opens the edit form
- **THEN** there is no creation date in the form

### Requirement: Details panel on narrow screens
On screens narrower than `lg` the block "Details" SHALL open as a slide-out panel, not be a column. The sticky action bar at the bottom of the window SHALL, besides "Cancel" and "Save", contain the details button (on `lg` and wider there is no such button). The button SHALL be an icon without visible text and without a state badge, SHALL have the accessible name "Details", and SHALL show an error indicator if any service field has a validation error. Closing the panel SHALL NOT lose the values entered in it, and sending the form SHALL send them in the same way as on desktop. While the panel is closed the service fields SHALL NOT be duplicated on the page. After a failed save with an error of a service field, the panel SHALL remain closed, but the button SHALL show the error indicator; opening the panel shows the message next to the corresponding field. The buttons "Cancel" and "Save" remain available in the panel.

#### Scenario: Mobile view
- **WHEN** the user opens the form in a window 390 px wide
- **THEN** they see the fields of the type in one column, and at the bottom of the screen — the panel with the icon button for details and the buttons "Cancel" and "Save", without a column on the right

#### Scenario: Opening the panel
- **WHEN** the user clicks the icon button for details
- **THEN** the panel opens with the toggle, the publication date, the slug, "Updated", and "Author"

#### Scenario: Values survive closing
- **WHEN** the user changes the slug in the panel, closes it, and clicks "Save"
- **THEN** the node is saved with the new slug

#### Scenario: Error of a service field
- **WHEN** on a narrow screen the user enters a taken slug and clicks "Save"
- **THEN** the node is not saved, and the details button shows the error indicator

#### Scenario: Message in the panel
- **WHEN** after a failed save with a slug error the user opens the panel
- **THEN** the error message is shown next to the slug field

#### Scenario: Button without text
- **WHEN** the user opens the form on a narrow screen
- **THEN** the details button contains no visible text and no state badge, but has the accessible name "Details"

#### Scenario: Screen rotation
- **WHEN** on a narrow screen the user changed a value in the panel and enlarged the window to a width from `lg`
- **THEN** the changed values are shown in the right column without loss

### Requirement: Link to a node in the media library for all kinds
The media library page SHALL show, for a file related to a node of any kind (`content`, `page`, `block`), the label of the node and the link to its edit form in the section of its kind.

#### Scenario: File in a page node
- **WHEN** a file is used in a node of the type `home` of kind `page`
- **THEN** the media library shows a link to `/admin/page-types/home/nodes/{uuid}/edit`

#### Scenario: File in a content node
- **WHEN** a file is used in a node of the type `news` of kind `content`
- **THEN** the link leads to `/admin/content-types/news/nodes/{uuid}/edit`

### Requirement: Slug prefix in the form of a block node
The create and edit forms of a node of a type of kind `block` SHALL show the prefix `block-` as an unchangeable visually separated part of the slug field before the input element; the input element SHALL accept only the part of the slug after the prefix. The form SHALL always send to the server the full slug with the prefix: `block-` is added to the entered text if it does not explicitly begin with it (an explicitly entered or pasted prefix is not doubled, and the field shows the remainder after it). An empty field SHALL be sent empty, and the server generates a slug with the prefix `block-`. In the edit form of a node the field SHALL show the existing slug without the prefix. The hint under the field SHALL explain that an empty field gives a generated slug with the prefix `block-`. For nodes of kinds `content` and `page` the slug field SHALL remain without a prefix.

#### Scenario: Prefix is visible
- **WHEN** the user opens the create form of a block node
- **THEN** the prefix `block-` is visible before the slug field, and the field itself is empty

#### Scenario: Only the remainder is entered
- **WHEN** the user enters `hero` into the field and saves the form
- **THEN** the node is created with the slug `block-hero`

#### Scenario: Prefix already entered explicitly
- **WHEN** the user pastes `block-hero` into the field and saves the form
- **THEN** the field shows `hero`, and the node is created with the slug `block-hero` (the prefix is not doubled)

#### Scenario: Empty field
- **WHEN** the user leaves the field empty and saves the form
- **THEN** the node is created with a generated slug of the form `block-xxxxxxxx`

#### Scenario: Editing
- **WHEN** the user opens the edit form of a node with the slug `block-hero`
- **THEN** the prefix `block-` is shown separately, and the field contains `hero`

#### Scenario: Other kinds
- **WHEN** the user opens the form of a node of a type of kind `content` or `page`
- **THEN** the slug field has no prefix

### Requirement: "SEO" panel in the node form
The form of a node of a type that has the fields `meta_title`, `meta_description`, and `meta_image` (kinds `content` and `page`) SHALL show these three fields not among the main fields, but in the right service column, in the "SEO" section of the accordion under the "Details" section. The accordion SHALL have two sections: "Details" (open by default) and "SEO" (closed by default); the whole accordion SHALL remain in the shared frame of the side column, and each section SHALL be a separate element in its own nested frame. At most one section SHALL be open at the same time: opening one automatically closes the other; the open section can be closed, then none is open. The "SEO" section SHALL work with the base language, as the rest of the form, be saved together with the node by the same action, and use the same input elements and validation as the other fields. The values SHALL be optional. If after a failed save the "SEO" field has an error, the section SHALL be expanded, the other section closed, and the error visible. A type without these fields (`block`) has no "SEO" section, and the accordion shows only "Details". The other fields of the type are shown in the main column in weight order. On a screen narrower than `lg` (mobile) the service column SHALL open by a button in the bottom sheet with the same accordion; the error indicator on the button SHALL be shown also for errors in the "SEO" fields.

#### Scenario: Form of a content type node
- **WHEN** the user opens the form of a node of a type that has `meta_*` fields on a wide screen
- **THEN** in the right column there is the accordion with the sections "Details" (open) and "SEO" (closed), each in its own frame inside the shared frame of the column; the main column contains no `meta_*` fields

#### Scenario: Expanding the "SEO" section
- **WHEN** the user expands the "SEO" section
- **THEN** they see the fields `meta_title`, `meta_description`, `meta_image` with the current values, and the "Details" section is closed

#### Scenario: One open section
- **WHEN** the "Details" section is open, and the user opens "SEO"
- **THEN** "SEO" is open, and "Details" are closed

#### Scenario: Separate frames
- **WHEN** the form shows both sections
- **THEN** each section has its own frame, and both together remain in the shared frame of the side column

#### Scenario: Saving SEO values
- **WHEN** the user fills in the fields of the "SEO" section and saves the node
- **THEN** the values are saved together with the node and shown in the "SEO" section after opening the edit form

#### Scenario: Error in a collapsed section
- **WHEN** the "SEO" section is collapsed, and the saving returns an error for `meta_title`
- **THEN** the "SEO" section is open, "Details" are closed, and the error is visible next to the field

#### Scenario: Sticky action bar
- **WHEN** the user opens the node form with an open section of the accordion in a window 400 px high (at 1280 and 390 px wide) and scrolls the form
- **THEN** the panel with "Cancel" and "Save" (and the details button on a narrow screen) remains fully visible at the bottom of the window, and is neither overlapped nor covered by the accordion

#### Scenario: Much content in the right column
- **WHEN** on a wide screen a section taller than the window is open
- **THEN** the right column scrolls by itself, does not go beyond the window, and does not overlap the action bar

#### Scenario: Mobile
- **WHEN** the user clicks the button "Details" on a screen narrower than `lg`
- **THEN** the bottom sheet with the accordion opens: "Details" are open, "SEO" is closed

#### Scenario: Node without SEO values
- **WHEN** the user saves a node without filling in the section "SEO"
- **THEN** the node is saved without values of `meta_*`

#### Scenario: Block types
- **WHEN** the user opens the form of a node of a type of kind `block`
- **THEN** there is no "SEO" section, and the accordion contains only "Details"

#### Scenario: Columns of the node table
- **WHEN** the list settings of the type refer to `meta_title`
- **THEN** the field is available as a column in the same way as any other field of a node

### Requirement: "Term" field in the form and the node list
The node form SHALL show for a field of type "term" the choice of terms of its vocabulary. For a single-value field there SHALL be a choice of one term with the possibility to clear it; for a multiple field — a choice of several terms (taking the limit of elements into account). The terms SHALL be shown as a tree with indents by depth, and their names — in the base language. The saved terms SHALL be marked, and an invalid choice (a term of another vocabulary, a non-existent one) SHALL be rejected by the server with an error next to the field. A required field SHALL require a choice. The node list SHALL show in the column of the field the names of the chosen terms separated by commas (in the base language) and SHALL not offer sorting by this column.

#### Scenario: Choice of one term
- **WHEN** the user chooses the term "Jackets" in the single-value field `category` and saves the node
- **THEN** the value is saved, and on reopening the form "Jackets" is marked

#### Scenario: Choice of several terms
- **WHEN** the user chooses the terms "News" and "Sport" in the multiple field `tags`
- **THEN** both values are saved in the chosen order

#### Scenario: Tree with indents
- **WHEN** the user opens the choice of terms of a vocabulary where "Jackets" is a child of "Clothing"
- **THEN** "Jackets" is shown below "Clothing" with an indent

#### Scenario: Clearing
- **WHEN** the user clears the choice in an optional single-value field and saves
- **THEN** the value of the field is deleted

#### Scenario: Names in the list
- **WHEN** the column `tags` is visible in the list of nodes, and the node has the terms "News" and "Sport"
- **THEN** the cell shows "News, Sport"

#### Scenario: Column is not sortable
- **WHEN** the user opens the menu of the column `category` in the list of nodes
- **THEN** sorting by this column is unavailable

### Requirement: Input element of the "link" field
The create and edit forms of a node SHALL show for a field of type `url` two single-line text fields in one row: "Link" (the link) and "Title" (the title, optional). Both fields SHALL have accessible names and be filled with the saved value during editing. Server errors SHALL be shown next to the field, and the entered data SHALL be kept. An empty "Link" field SHALL clear the value, even if "Title" is filled in. For a multiple field each element of the list SHALL have its own pair of fields by the general rules of the form. The field SHALL have no modal windows and no requests to external services.

#### Scenario: Creating a node with a link
- **WHEN** the user enters `/about` into "Link" and `About us` into "Title" and saves the node
- **THEN** the node is saved, and on reopening both fields contain the entered values

#### Scenario: Link without a title
- **WHEN** the user fills in only "Link"
- **THEN** the node is saved without a title

#### Scenario: Invalid link
- **WHEN** the user enters `javascript:alert(1)` into "Link" and saves
- **THEN** an error is shown next to the field, and the entered values remain in the fields

#### Scenario: Multiple field
- **WHEN** the user adds several elements to the multiple field `url`
- **THEN** each element has its own pair "Link" and "Title", and it is deleted and reordered by the general rules

### Requirement: Value of the "link" field in the node table
If a field of type `url` is chosen as a column of the list of nodes, its cell SHALL show an interactive link: the text is the title of the value, and without a title — the address itself; the link SHALL open in a new tab (`target="_blank"`, `rel="noopener noreferrer"`), and its `href` is the saved link. For a multiple field the general display of several values in a cell SHALL apply. This concerns only the list of nodes, not the form. The column of the `url` field SHALL NOT be sortable, SHALL NOT be offered as the default sorting in the settings of the type, and a sorting parameter by it SHALL be ignored in the same way as for `youtube` and `term`.

#### Scenario: Cell with a title
- **WHEN** a node has a `url` value with the title `About us`
- **THEN** the cell shows a link with the text `About us`, which leads to the saved address and opens in a new tab

#### Scenario: Cell without a title
- **WHEN** a node has a `url` value `/about` without a title
- **THEN** the cell shows a link with the text `/about`, which opens in a new tab

#### Scenario: Sorting by the field
- **WHEN** the address of the list contains sorting by the `url` field
- **THEN** the parameter is ignored, and the column is not marked as sorted

### Requirement: Input elements of the reference fields
The node form SHALL show for `type_ref` a choice of a type, and for `node_ref` — a choice of a node; both as a dropdown autocomplete with server-side search, which requests the options while typing (with a delay) and shows a limited number of results. For `type_ref` the search is by the `name` of the type of the kind chosen in the settings of the field; the option shows the `name` (and the `key` in a muted way). For `node_ref` the search is by the `title` (the `default` language) of the nodes of the type chosen in the settings. The chosen value SHALL show its label (`name` / `title`) at once when the form is opened, without typing; the form value is the `key` / `uuid`. The choice SHALL be clearable, and then the value is cleared. For a multiple field each element SHALL have its own autocomplete by the general rules of the form. Server errors SHALL be shown next to the field, and the choice SHALL be kept.

#### Scenario: Searching a type
- **WHEN** the user types part of the `name` of a type in the field `type_ref`
- **THEN** the list shows the types of the required kind that match the query, and the choice writes the `key`

#### Scenario: Searching a node
- **WHEN** the user types part of the `title` in the field `node_ref`
- **THEN** the list shows the nodes of the required type that match the query, and the choice writes the `uuid`

#### Scenario: Saved value
- **WHEN** the user opens a node with a saved value
- **THEN** the field shows the label of the chosen target

#### Scenario: Clearing the choice
- **WHEN** the user clears the choice and saves
- **THEN** the value of the field is removed

### Requirement: Search endpoints for the reference fields
The admin panel SHALL provide authorized administrators with a JSON search of types by `name` within a kind and of nodes by `title` (the `default` language) within a type. The search SHALL return no more than 20 results in a stable order, and an empty query — the first 20. An unknown kind or type SHALL give a validation error, and an unauthorized request — a refusal.

#### Scenario: Searching types
- **WHEN** an administrator requests the types of kind `block` by `q = hero`
- **THEN** the response contains up to 20 types of this kind with `key` and `name` that contain `hero`

#### Scenario: Searching nodes
- **WHEN** an administrator requests the nodes of the type `article` by `q = news`
- **THEN** the response contains up to 20 nodes with `uuid` and `title` that contain `news`

#### Scenario: Unknown type
- **WHEN** the request specifies a type that does not exist
- **THEN** the response is a validation error

#### Scenario: Without authorization
- **WHEN** an unauthenticated user calls the search
- **THEN** the request is rejected

### Requirement: Values of the reference fields in the node table
If a field `type_ref` / `node_ref` is chosen as a column of the list of nodes, the cell SHALL show the `name` of the type / the `title` of the node (the `default` language) as a link to the edit page of this entity; the link SHALL open in the same window (without `target="_blank"`). For a multiple field — several links in the saved order; if the label is unavailable — the value itself without a link. The column SHALL be sorted by the label (`name` / `title` of the `default` language), for a multiple field — by the first value; nodes without a value SHALL go last in both directions. The column SHALL be available as the default sorting in the list settings.

#### Scenario: Cell
- **WHEN** a node has a `node_ref` to a node with `title = News`
- **THEN** the cell shows the link `News`, which leads to the editing of this node and opens in the same window

#### Scenario: Link to a type
- **WHEN** a node has a `type_ref` to the type `Hero` of kind `block`
- **THEN** the cell shows the link `Hero` to the editing of this type

#### Scenario: Sorting by the label
- **WHEN** the list is sorted by such a field in ascending order
- **THEN** the nodes go in the alphabetical order of the target labels, and the nodes without a value go last

### Requirement: Error of a slug collision of a page in the node form
The create and edit forms of a node of kind `page` SHALL reject a slug that matches the slug of a type of kind `content` or a reserved site path, and show an error next to the slug field; the rest of the entered data SHALL remain in the form. For nodes of kinds `content` and `block` this check does NOT apply. The automatically formed slug of a page SHALL avoid such matches by adding a numeric suffix, so the error arises only for an explicitly entered slug.

#### Scenario: Slug of a page matches the slug of a type
- **WHEN** the user enters the slug `news` in the form of a page, and a type of kind `content` has the slug `news`
- **THEN** the page is not saved, and an error is shown next to the slug field

#### Scenario: Slug of a page matches a reserved path
- **WHEN** the user enters the slug `login` in the edit form of a page
- **THEN** the change is not saved, and an error is shown next to the slug field

#### Scenario: Automatic slug without a match
- **WHEN** the user leaves the slug empty and enters the `title` "Admin"
- **THEN** the page is created with the slug `admin-2`

#### Scenario: Node content with the slug of a type
- **WHEN** the user enters the slug `news` in the form of a node of the type `content`, and the type has the slug `news`
- **THEN** the node is saved

### Requirement: "Preview" button in the edit form of a node
The edit form of a node of kind `page` or of a type `content` SHALL contain in the bottom action bar the button "Preview" — a link to the public address of this node, which opens in a new browser tab: for a page `/{slug}`, for a `content` node — `/{type slug}/{node slug}`. The address SHALL match the saved slug (not the unsaved changes in the form). For a node of a type `block` there SHALL be no button, because a block has no address of its own. In the create form there SHALL be no button. The button is a simple link: for a node that the site does not show (disabled, scheduled, expired), the site responds with 404 by the rules of the public site.

#### Scenario: Preview of a page
- **WHEN** the user opens the edit form of a page with the slug `about` and clicks "Preview"
- **THEN** `/about` opens in a new tab

#### Scenario: Preview of a content node
- **WHEN** the user opens the edit form of the node `hello` of a type with the slug `news`
- **THEN** the button "Preview" leads to `/news/hello` and opens in a new tab

#### Scenario: Block without preview
- **WHEN** the user opens the edit form of a block node
- **THEN** there is no "Preview" button

#### Scenario: Create form
- **WHEN** the user opens the create form of a node
- **THEN** there is no "Preview" button

#### Scenario: Invisible node
- **WHEN** the user clicks "Preview" for a disabled node
- **THEN** the site's 404 opens in a new tab, and the form in the admin panel remains unchanged

### Requirement: Thumbnail `thumb` in the cell of the node list
The cell of an `image` field in the table of nodes SHALL show the `thumb` style of the image (the `preview` field of the file's data; if it is absent — the original). The thumbnail SHALL be a link to the original with `target="_blank"` and `rel="noopener noreferrer"` and SHALL have `cursor-pointer`. For a multiple field this applies to each thumbnail.

#### Scenario: Thumbnail in the cell
- **WHEN** a node has an `image` field with a file
- **THEN** the `src` of the image in the cell is the address of the `thumb` style, and the `href` of the link is the address of the original

#### Scenario: Cursor
- **WHEN** the user hovers over a thumbnail in the cell
- **THEN** the cursor is a pointer (`cursor-pointer`)

### Requirement: Error of a node slug that hides a type list in the node form
The create and edit forms of a node of kind `content` SHALL reject a slug that makes the address of the node (the slug of its type, `/`, and the slug of the node) equal to the slug of a type of kind `content`, and show an error next to the slug field; the rest of the entered data SHALL remain in the form. The check applies on creation and on a change of the slug, and does not apply to a type without a slug or to nodes of kinds `page` and `block`.

#### Scenario: Slug that hides a type list
- **WHEN** the user enters the slug `institutions` in the form of a node of the type with the slug `common`, and another type has the slug `common/institutions`
- **THEN** the node is not saved, and an error is shown next to the slug field

#### Scenario: Slug of a node of a type without a slug
- **WHEN** the user enters any admissible slug in the form of a node of a type without a slug
- **THEN** the node is saved

#### Scenario: Saving without changing the slug
- **WHEN** an existing node whose address equals a type slug (created earlier) is saved with the unchanged slug and a new title
- **THEN** the change is saved

### Requirement: Tables in the formatted text editor
The `richtext` editor SHALL display, edit, and save tables. The toolbar SHALL have a button "Table" with an accessible name. Outside a table it SHALL insert a table of three rows and three columns whose first row consists of header cells, with the cursor in the first cell; inside a table it SHALL open a menu of table actions: add a row above, add a row below, delete the row, add a column before, add a column after, delete the column, switch the header row on or off, and delete the table. The menu actions SHALL work on the row and the column with the cursor. A cell SHALL hold text with the same inline formatting as a paragraph (bold, italic, underline, links, color), and the content of a cell SHALL be saved as paragraphs. The button "Table" SHALL be disabled, like the other formatting buttons, while the mode "Source" is on.

A table that is already in the value (written by the editor, imported from another site, or saved with `colspan` and `rowspan`) SHALL open in the editor without losing the rows, the columns, the header cells, the cell spans, or the text, and SHALL be saved with the same structure after the node is saved without edits to the table. Table rows inside `thead`, `tbody`, and `tfoot` SHALL be read as ordinary rows of one table. The editor SHALL show a table with visible cell borders, and a table wider than the editor SHALL scroll horizontally instead of widening the form.

#### Scenario: Inserting a table
- **WHEN** the user clicks "Table" with the cursor outside a table, types `A` in the first cell, and saves the node
- **THEN** after reloading the editor shows a table of three rows and three columns, the first row consists of header cells, and the first cell contains `A`

#### Scenario: Adding and deleting a row and a column
- **WHEN** the user places the cursor in a cell of a table, chooses "Add row below" and "Add column after" in the menu "Table", and saves the node
- **THEN** after reloading the table has one more row and one more column, and choosing "Delete row" and "Delete column" removes them again

#### Scenario: Switching the header row
- **WHEN** the user places the cursor in the first row and chooses to switch the header row off
- **THEN** the cells of the first row are ordinary cells, and the same choice again makes them header cells

#### Scenario: Deleting the table
- **WHEN** the user places the cursor in a cell and chooses "Delete table"
- **THEN** the table is removed from the text, and the surrounding paragraphs remain

#### Scenario: Menu outside a table
- **WHEN** the cursor is in a paragraph outside a table
- **THEN** clicking "Table" inserts a table and does not open the menu of table actions

#### Scenario: An existing table survives opening and saving
- **WHEN** a node whose `richtext` value holds a table with a header row, a cell with `colspan="2"`, and text in every cell is opened and saved without edits to the table
- **THEN** the saved value holds a table with the same rows, header cells, `colspan="2"`, and cell text

#### Scenario: A table from the legacy markup
- **WHEN** a node is opened whose value has rows in `thead` and `tbody` and paragraphs inside the cells
- **THEN** the editor shows one table with all the rows in their order, and saving keeps the text of every cell

#### Scenario: Source mode disables the button
- **WHEN** the user switches on the mode "Source"
- **THEN** the button "Table" is disabled, and it is enabled again after returning from "Source"

#### Scenario: A wide table does not widen the form
- **WHEN** the value holds a table with more columns than fit in the editor
- **THEN** the table scrolls horizontally inside the editor, and the node form keeps its width

### Requirement: Saving a node with an unchanged legacy slug
The edit form of a node SHALL accept the slug that the node already has, even if it does not match the strict slug format (a slug kept from the legacy import), and the other changes SHALL be saved. A slug that is changed in the form SHALL still follow the strict format and the other slug rules. The create form SHALL NOT accept the legacy format.

#### Scenario: Editing a node with a legacy slug
- **WHEN** a node with the slug `volnovaha---misto-geroy-ukrayini` is edited and saved with a new title and the same slug
- **THEN** the changes are saved

#### Scenario: Changing a legacy slug to another legacy slug
- **WHEN** the slug of such a node is changed in the form to `a---b`
- **THEN** the change is not saved, and an error is shown next to the slug field

#### Scenario: Creating a node with a legacy slug
- **WHEN** the create form is submitted with the slug `a---b`
- **THEN** the node is not created, and an error is shown next to the slug field

### Requirement: Limiting the width of the slug column
The service column "Slug" of the node table SHALL have a limited width: a long slug SHALL be cut into one line with an ellipsis, without stretching the table or pushing the other columns. The full slug SHALL be available in a hint. A click on the slug SHALL copy the full slug to the clipboard and show a short confirmation; the slug SHALL be operable by keyboard (focusable, `Enter` and `Space` copy it) and SHALL NOT open the node or trigger any other row action.

#### Scenario: Long slug
- **WHEN** a node has a slug longer than the width of the column
- **THEN** the slug takes one line and ends with an ellipsis, and the other columns remain in place

#### Scenario: Full slug in the hint
- **WHEN** the user hovers over a cut slug
- **THEN** they see the full slug

#### Scenario: Copying the slug
- **WHEN** the user clicks the slug of a node in the table
- **THEN** the full slug (not the shortened text) is placed in the clipboard, a confirmation is shown, and the user stays on the list

### Requirement: Block style dropdown of the formatted text editor
The toolbar of the `richtext` editor SHALL have one dropdown "Block style" instead of the single heading button, with the items "Normal" (a paragraph), "Heading 2", "Heading 3" and "Heading 4" (`h2` / `h3` / `h4`). The button of the dropdown SHALL have an accessible name, SHALL show the style of the block with the cursor (the label of the current item), and the current item SHALL be marked in the menu. Choosing an item SHALL turn the block with the cursor into that style (choosing the current heading level again SHALL NOT change the block; "Normal" turns a heading back into a paragraph) and SHALL leave the focus in the editor. Only a paragraph SHALL be shown as "Normal"; where the cursor is not in a paragraph or a heading `h2`–`h4` (for example, an image is selected), no item SHALL be marked as current and the button SHALL show a neutral icon. The dropdown SHALL be unavailable in the "Source" mode like the other formatting buttons.

#### Scenario: Heading 3
- **WHEN** the user puts the cursor in a paragraph, opens "Block style", chooses "Heading 3", and saves the node
- **THEN** the saved value holds the text in an `h3`, and after reopening the form the dropdown shows "Heading 3" at that text

#### Scenario: Heading 4
- **WHEN** the user puts the cursor in a paragraph, chooses "Heading 4", and saves the node
- **THEN** the saved value holds the text in an `h4`, and after reopening the form the dropdown shows "Heading 4" at that text

#### Scenario: Switching the level
- **WHEN** the cursor is in an `h2` and the user chooses "Heading 3"
- **THEN** the block becomes an `h3`, and the dropdown shows "Heading 3"

#### Scenario: Back to a paragraph
- **WHEN** the cursor is in an `h3` and the user chooses "Normal"
- **THEN** the block becomes a paragraph, and the dropdown shows "Normal"

#### Scenario: Neither a paragraph nor a heading
- **WHEN** an image is selected in the editor and the user opens the dropdown
- **THEN** no item is marked as current, and the button does not show "Normal"

#### Scenario: Current style in the menu
- **WHEN** the cursor is in an `h2` and the user opens the dropdown
- **THEN** the item "Heading 2" is marked as current

### Requirement: Node id in the edit form
The edit form of a node SHALL show the numeric `id` of the node in the "Details" block, as a read-only value with the label "ID". A click on the value SHALL copy the `id` to the clipboard and show a short confirmation; the value SHALL be operable by keyboard (focusable, `Enter` and `Space` copy it). The create form SHALL NOT show an `id`, because the node does not exist yet. The `id` SHALL NOT be sent back on saving and SHALL NOT be editable.

#### Scenario: Id on the edit form
- **WHEN** the user opens the edit form of the node with `id` 42
- **THEN** the "Details" block shows "ID" with the value 42

#### Scenario: Copying the id
- **WHEN** the user clicks the id value
- **THEN** `42` is placed in the clipboard and a confirmation is shown

#### Scenario: Create form
- **WHEN** the user opens the create form
- **THEN** the "Details" block has no "ID"

### Requirement: Pasting lists from Word into the formatted text editor
When the user pastes formatted text copied from Microsoft Word (a `.docx` document) into the `richtext` editor, the list paragraphs of the clipboard SHALL become real lists: bulleted items a bulleted list, numbered items a numbered list, with the nesting of the document kept (several levels). The bullet or number that Word draws as text (`·`, `•`, `o`, `§`, `1.`, `a)`) and the spaces after it SHALL NOT appear in the pasted text. Consecutive items of the same kind and level SHALL form one list; an ordinary paragraph between items SHALL end the list. The inline formatting of an item (bold, italic, underline, links) SHALL be kept as the editor allows. The same SHALL apply to paragraphs whose text itself starts with a bullet character (`•`, `·`, `▪`, `◦`, `●`) followed by spaces (this is what a Word list looks like when it arrives without list markup, for example as plain text); consecutive such paragraphs SHALL become one bulleted list without the bullet and the spaces. Likewise, a run of at least two consecutive paragraphs (or plain-text lines) that start with a number and `.` or `)` followed by whitespace, where each number is one more than the previous (`1.`, `2.`, `3.`), SHALL become one numbered list that starts at the first number, without the typed numbers and spaces. A single numbered paragraph, or numbers that do not follow each other, SHALL stay as text. Text that is not a Word list, and lists that already arrive as `ul` / `ol` (from a browser, Google Docs), SHALL be pasted as before.

#### Scenario: Bulleted list
- **WHEN** the user pastes three bulleted paragraphs copied from Word
- **THEN** the editor shows one bulleted list with three items, without bullet characters or leading spaces in the text

#### Scenario: Numbered list
- **WHEN** the user pastes a numbered list "1. One", "2. Two" copied from Word
- **THEN** the editor shows one numbered list with the items "One" and "Two", without the typed numbers in the text

#### Scenario: Bullets that arrive as text
- **WHEN** the user pastes the paragraphs `•    керівник — Юлія Павелко;`, `•    викладач — Юлія Шустова;`, `•    концертмейстер — Наталія Омельницька.` (as plain text or as ordinary paragraphs)
- **THEN** the editor shows one bulleted list with the items `керівник — Юлія Павелко;`, `викладач — Юлія Шустова;` and `концертмейстер — Наталія Омельницька.`, without the bullet and the spaces, and the paragraphs before and after the list stay ordinary paragraphs

#### Scenario: Typed numbers that arrive as text
- **WHEN** the user pastes the paragraphs `1.    керівник — Юлія Павелко;`, `2.    викладач — Юлія Шустова;`, `3.    концертмейстер — Наталія Омельницька.` between ordinary paragraphs
- **THEN** the editor shows one numbered list with the three items without the numbers and spaces, and the surrounding paragraphs stay ordinary

#### Scenario: A lone number stays text
- **WHEN** the user pastes one paragraph `2026. Рік театру` or the paragraphs `1. Intro` and `5. Outro`
- **THEN** they stay ordinary paragraphs with their text unchanged

#### Scenario: Nested list
- **WHEN** the user pastes a list from Word whose second item has two sub-items at the next level
- **THEN** the sub-items are a nested list inside the second item

#### Scenario: Paragraph between lists
- **WHEN** the pasted text is a list, an ordinary paragraph, and another list
- **THEN** the editor shows two separate lists with the paragraph between them

#### Scenario: List from a browser
- **WHEN** the user pastes a `ul` with two `li` copied from a web page
- **THEN** the editor shows the same list as before the change

### Requirement: Author avatar in the node list and form
The "Author" column of the node table and the read-only "Author" row of the node form SHALL show the author's avatar next to their name when the author has one, and their initials otherwise.

#### Scenario: Author with an avatar in the list
- **WHEN** a node's author has an avatar and the table shows the `author` column
- **THEN** the row shows that picture next to the author's name

#### Scenario: Author with an avatar in the form
- **WHEN** a user opens the edit form of a node whose author has an avatar
- **THEN** the "Author" row of the "Details" block shows the picture next to the name

#### Scenario: Author without an avatar
- **WHEN** the author of a node has no avatar
- **THEN** the list cell and the form row show the initials next to the name

### Requirement: One-click state toggle in the node list
In the node list, the state badge (the `status` column) of a row the user may edit SHALL be a clickable control that toggles only `is_active` of the node, without a confirmation. A click on a node in the state "Published", "Scheduled" or "Expired" SHALL set `is_active = false`; a click on a "Disabled" node SHALL set `is_active = true`. `published_at`, `unpublished_at` and all values SHALL stay unchanged, so after enabling, the badge shows the state that follows from the dates (a node with a future `published_at` becomes "Scheduled", one with a reached `unpublished_at` becomes "Expired"). The change SHALL go through `PATCH {section}/{contentType}/nodes/{node}/active` with a boolean `is_active`, SHALL be authorized by the same policy as editing the node, SHALL answer with a redirect back and a success message, and SHALL move `updated_at`. For a row the user may not edit, the badge SHALL be shown as before, not clickable. The control SHALL have an accessible name that says what the click does. The edit form SHALL be unchanged.

#### Scenario: Unpublishing a published node
- **WHEN** the user clicks the "Published" badge of a node in the list
- **THEN** `is_active` becomes false, the badge shows "Disabled", and the dates are unchanged

#### Scenario: Enabling a disabled node with a future date
- **WHEN** the user clicks the "Disabled" badge of a node whose `published_at` is in the future
- **THEN** `is_active` becomes true and the badge shows "Scheduled"

#### Scenario: Clicking a scheduled or expired node
- **WHEN** the user clicks the "Scheduled" or "Expired" badge
- **THEN** `is_active` becomes false and the badge shows "Disabled"

#### Scenario: A user who may not edit the node
- **WHEN** a user without the right to edit a node views it in the list, or sends `PATCH .../active` for it
- **THEN** the badge is not clickable, and the request is refused with 403 without changing the node

#### Scenario: Invalid value
- **WHEN** the request has no boolean `is_active`
- **THEN** the response is a validation error and the node is unchanged

### Requirement: Translation mode of the node edit page
The edit page of a node of any kind SHALL follow the translation mode contract. In the translation mode of a language the form SHALL keep the layout of the base form (the main column, the side sections, the action bar) with only the translatable fields editable: the title, the `meta_*` fields and the regular fields marked translatable. A field SHALL be shown prefilled with its stored translation, or with its base value when no translation exists; a multiple field SHALL be prefilled and saved as a whole list. The slug, status, dates, author, activity and every non-translatable field SHALL stay in place, shown disabled with their base values, and SHALL NOT be changed; nothing SHALL be marked required, and "Save" SHALL stay disabled until a translatable value changes. The save SHALL write only the fields of the current language, SHALL NOT enforce required fields, SHALL NOT store a value equal to the base value and SHALL remove the translation of a field that is emptied. Saving a translation SHALL count as a change of the node (its modification time is updated). The right to open and save a translation SHALL be the right to edit that node in the base language, including the access level of its type.

#### Scenario: Translation mode keeps the layout
- **WHEN** a node has a translatable `title`, a translatable `body` and a non-translatable `price`, and the user opens it with `?lang=en`
- **THEN** the form has the layout of the base form, the title and the body are editable and prefilled, and the price, the slug, the status and the dates stay in place, disabled

#### Scenario: Saving a translated title
- **WHEN** the user changes the prefilled title to "Hello" and saves
- **THEN** the English title "Hello" is stored, the base title is unchanged, and the user stays on the English form

#### Scenario: Required field left empty
- **WHEN** a required translatable field is emptied in the translation mode and the user saves
- **THEN** the save succeeds and the translation of that field is removed

#### Scenario: Value equal to the base
- **WHEN** the user saves a form in which a prefilled field was not changed
- **THEN** no translation is stored for that field

#### Scenario: Multiple field
- **WHEN** a translatable multiple field has the base list `a, b` and the user saves the English list `a, c`
- **THEN** the whole English list is stored as `a, c` and the base list is unchanged

#### Scenario: Node of another owner
- **WHEN** a user whose access level allows editing only their own nodes opens the translation mode of another user's node
- **THEN** the response is the same as for the base edit page

#### Scenario: Clearing
- **WHEN** the user confirms "Clear translation" in the English mode
- **THEN** every English value of the node is removed and the form shows the base values

### Requirement: Translations column and missing-translation filter
When multilingual mode is on and at least one additional language is active, the node list SHALL offer a "Translations" column that shows the languages in which the node has a translation, and a filter "Without translation in" with the active languages. The column SHALL be hideable like other columns and SHALL NOT be sortable. The filter SHALL keep only the nodes that have no translation in the chosen language, combined with the other parameters of the list. When multilingual mode is off or no language is active, neither SHALL appear and the list SHALL behave as before. A node is translated into a language when it has at least one stored translated value in it.

#### Scenario: Column
- **WHEN** a node has translations in `en` only and the languages `en` and `pl` are active
- **THEN** the Translations column shows `en` for that node and nothing for a node without translations

#### Scenario: Filter
- **WHEN** the user chooses "Without translation in `pl`"
- **THEN** only the nodes that have no Polish value remain in the list

#### Scenario: Single-language site
- **WHEN** multilingual mode is off
- **THEN** the list has no Translations column and no filter

#### Scenario: Number of queries
- **WHEN** the list shows a page of nodes
- **THEN** the translated languages of the whole page are read with a constant number of queries, not one per node

### Requirement: File names and descriptions in the translation mode of a node
In the translation mode of a node, every `image` or `file` field SHALL let the user edit the display name and the description of each file in its value in the current language, whether or not the field itself is translatable. A name and a description SHALL be prefilled with the translated value or, when none exists, with the base one (the original name, the base description). For a field that is not translatable the file itself SHALL be shown but not changeable; for a translatable field the file may also be changed as for any translatable field. Saving SHALL store only names and descriptions that differ from the base, SHALL remove one that is emptied, and SHALL NOT change the original name or the base description. The same file in several nodes SHALL show the same translated name and description everywhere.

#### Scenario: Description of a non-translatable image
- **WHEN** a node has a non-translatable `image` field and the user opens the English mode
- **THEN** the image is shown with an editable name and description prefilled from the base ones, and the image cannot be replaced

#### Scenario: Saving a translated description
- **WHEN** the user changes the prefilled description to "Front cover" and the name to "Cover" and saves
- **THEN** the English name and description of that file are stored and the original name and the base description are unchanged

#### Scenario: Shared file
- **WHEN** the same file is used in another node
- **THEN** that node's English mode shows the same English name and description

#### Scenario: Original name is kept
- **WHEN** the user saves a translated name
- **THEN** the original name of the file is unchanged and the base mode still shows it

### Requirement: A file new in a node must be one the user may attach
The system SHALL accept a file as a new value of a node (an `image` or `file` field, a YouTube screenshot, an image in rich text, in any language, including the translation mode) only when the user uploaded that file or the user's access to the media library allows them to change it. A file counts as new when it is not already among the files referenced by the node's saved values; when a node is created, every file is new. A file that is already among the node's saved values SHALL stay valid for every user who may edit the node, whoever uploaded it. A refused file SHALL give a validation error next to the field, and nothing of that save SHALL be stored. A user of the role `admin` SHALL be unrestricted.

#### Scenario: Crafted request with another user's file on create
- **WHEN** a user with the level `own` on a type and no access to the media library submits a node whose file field holds the uuid of a file uploaded by another user
- **THEN** the save is refused with an error next to that field and no node is created

#### Scenario: Crafted request with another user's file on update
- **WHEN** the same user saves their own node with the uuid of another user's file added to a file field, with a new name and description for it
- **THEN** the save is refused with an error next to that field, and the node and the foreign file are unchanged

#### Scenario: Own upload
- **WHEN** a user without access to the media library uploads an image in the node form and saves the node
- **THEN** the node is saved with that image

#### Scenario: Colleague's file already in the node
- **WHEN** a user with the level `any` on a type, without access to the media library, re-saves a node that already holds a file uploaded by a colleague
- **THEN** the node is saved and the file stays in its values

#### Scenario: Media access
- **WHEN** a user with the media level `any`, or `own` for a file they uploaded, attaches a file to a node
- **THEN** the file is accepted; a user with the media level `own` is refused a file uploaded by another user

#### Scenario: Translation mode
- **WHEN** a user submits a translation of a translatable file field with another user's file
- **THEN** the translation is refused with an error next to the field and no translated value is stored

#### Scenario: Administrator
- **WHEN** an administrator attaches any existing file to a node
- **THEN** the file is accepted

### Requirement: Editing a node includes the data of its files
A user who may edit a node SHALL be able to change the name and the description of every file referenced by that node's values, with no further check of the media library access. Data sent for a file that is not among the node's values SHALL be ignored without an error, and the rest of the save SHALL succeed.

#### Scenario: Colleague's file already in the node
- **WHEN** a user with the level `any` on a type, without access to the media library, saves a node with a new name and description for a file uploaded by a colleague that is already in the node
- **THEN** the file gets the new name and description

#### Scenario: File outside the node
- **WHEN** a user saves a node with new data for a file that is not among the node's values
- **THEN** the node is saved and that file keeps its name and description
