# Spec Delta

## MODIFIED Requirements

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
