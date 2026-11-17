## MODIFIED Requirements

### Requirement: Fields page of a type
The admin panel SHALL have the page `/admin/content-types/{key}/fields`, available only to authenticated users with a verified email, which shows the fields of a content type of kind `content` with this key, except the panel fields (`meta_*`, `section_*`; see the requirement about panel fields) in a data table in client mode: all fields are passed to the page, and search, sorting, pagination, and page size are performed by the browser without requests to the server. The table SHALL NOT have filters. Without an explicit sort the fields SHALL go by weight (`weight`), and for equal weight — by the order of creation. The page SHALL have no title of its own: the heading is the name of the type shown above the tabs of the section menu (see `admin-content-types`), and above the table there SHALL be a link back to the list of types. For a key that is not among the types of kind `content` (in particular a key of a type `page` or `block`), the page SHALL respond with 404.

#### Scenario: Opening the page
- **WHEN** an authenticated user opens `/admin/content-types/news/fields`
- **THEN** they see the table of fields of the type `news` and a heading with the name of the type above the tabs, with no title "Fields of “…”", and the table toolbar has no filter button

#### Scenario: Default order
- **WHEN** the type has the fields `title` (weight 0), `body` (weight 20), and `summary` (weight 10)
- **THEN** without sorting the table shows them in the order `title`, `summary`, `body`

#### Scenario: Search in the browser
- **WHEN** the user types text into the table search
- **THEN** the table narrows by the name or key of the field without a page reload and without a request to the server

#### Scenario: Type of another kind
- **WHEN** the user opens the fields page for the key of a type of kind `page`
- **THEN** the server responds with 404

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/content-types/news/fields`
- **THEN** they are redirected to the login page

#### Scenario: Panel fields are not shown
- **WHEN** the user opens the fields page of a type that has the fields `title`, `meta_title`, `section_title`, and `body`
- **THEN** the table has `title` and `body`, and there is no `meta_title` and `section_title`
