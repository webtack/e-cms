## RENAMED Requirements

- FROM: `### Requirement: Сторінка списку вузлів`
- TO: `### Requirement: Сторінка списку записів`

## MODIFIED Requirements

### Requirement: Сторінка списку записів
The admin SHALL have the page `/admin/content-types/{key}/nodes`, available only to authenticated users with a confirmed email, which shows the records of the content type of the kind `content` with this key in the data table in server mode: the search, sorting, pagination and page size are performed by the server, and the state of the table is stored in the address. The table SHALL NOT have filters. The page title SHALL name the type, and above the table SHALL be the horizontal menu of the type section with the current item «Записи». For a key that is not among the types of the kind `content` (in particular, a key of a type `page` or `block`), the page SHALL respond with 404. The page SHALL open also for a type without records.

#### Scenario: Opening the page
- **WHEN** an authenticated user opens `/admin/content-types/news/nodes`
- **THEN** they see the table of records of the type `news`, the title with the name of the type and the section menu where «Записи» is current; there is no filter button in the toolbar of the table

#### Scenario: Only records of this type
- **WHEN** records of the types `news` and `product` exist, and the user opens the list of the type `news`
- **THEN** the table contains only the records of the type `news`

#### Scenario: Empty state
- **WHEN** the type has no records
- **THEN** the page opens with the standard message of the table about the absence of data, and the button «Створити» is visible

#### Scenario: Type of another kind
- **WHEN** the user opens `/admin/content-types/home/nodes` for the key of a type of the kind `page`
- **THEN** the server responds with 404

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/content-types/news/nodes`
- **THEN** they are redirected to the login page
