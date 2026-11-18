# Spec Delta

## MODIFIED Requirements

### Requirement: Пошук і сортування записів
The table search SHALL search by the search fields from the settings of the type, and without them — by all text fields of the type, without regard to the case. It SHALL be possible to sort by the columns of the fields of the type, including the multiple ones: the sort key of a record SHALL be the first value of the field. The columns of file fields (`file`, `image`) and `youtube`, as well as the slug and the state, are not sortable. Without an explicit sorting the order SHALL be taken from the settings of the list of the type, and without them — by the order of creation. Records without a value in the sort column SHALL go last in both directions. With equal sort keys (by a field and by `published_at`) the records SHALL go by `title` in the language of the list in ascending order, and then by the identifier; without an explicit sorting this order is not applied. A page number out of range SHALL return the last page. Incorrect parameters of the address SHALL be ignored without an error of the page.

#### Scenario: Search
- **WHEN** the user enters «lamp» in the search of the table of records of the type `product`
- **THEN** the table keeps the records whose text fields contain «lamp», and the address contains `q=lamp`

#### Scenario: Sorting by a field
- **WHEN** the user sorts by the column `price` in descending order
- **THEN** the records go from the highest price, records without a price — last

#### Scenario: Equal values in the column
- **WHEN** the user sorts by the column `price`, and two records have the same price and the `title` «Beta» and «Alpha»
- **THEN** «Alpha» goes before «Beta» in both directions of sorting

#### Scenario: Sorting by a multiple field
- **WHEN** the address contains `sort=tags`, and `tags` is a multiple text field
- **THEN** the records are ordered by the first value of `tags`, records without values — last

#### Scenario: Sorting by a service column
- **WHEN** the address contains `sort=slug`
- **THEN** the sorting is ignored, the page opens

#### Scenario: Columns of file fields and youtube
- **WHEN** the type has the fields `image`, `file` and `youtube`
- **THEN** their columns have `sortable: false`, and `sort` for them in the address is ignored, the page opens

#### Scenario: Settings of sorting by a multiple field
- **WHEN** `settings.list.sort` of the type refers to a multiple field
- **THEN** records without an explicit `sort` in the address are ordered by the first value of this field

#### Scenario: Settings of sorting by a file field
- **WHEN** `settings.list.sort` of the type refers to the field `image`
- **THEN** this setting is dropped, the default order applies

#### Scenario: Page out of range
- **WHEN** the address contains `page=999`, and there are only three pages
- **THEN** the third page is shown

#### Scenario: Default order
- **WHEN** the type has in its settings the sorting by `title` in ascending order, and there is no `sort` in the address
- **THEN** the records go by `title` in ascending order
