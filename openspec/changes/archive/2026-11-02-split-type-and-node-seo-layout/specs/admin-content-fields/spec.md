## MODIFIED Requirements

### Requirement: Сторінка полів типу
The admin SHALL have the page `/admin/content-types/{key}/fields`, available only to authenticated users with a confirmed email, which shows the fields of a content type of the kind `content` with this key, except the fields of the panels (`meta_*`, `section_*`; see the requirement about the fields of the panels), in a data table in the client mode: all fields are passed to the page, and the search, sorting, pagination and page size are performed by the browser without requests to the server. The table SHALL NOT have filters. Without an explicit sorting the fields SHALL go by the weight (`weight`), and with equal weight — by the order of creation. The heading of the page SHALL name the type, and above the table there SHALL be a link back to the list of types. For a key that is not among the types of the kind `content` (in particular the key of a type `page` or `block`), the page SHALL respond with 404.

#### Scenario: Opening the page
- **WHEN** an authenticated user opens `/admin/content-types/news/fields`
- **THEN** they see the table of fields of the type `news` and a heading with the name of the type, and there is no button of filters in the panel of the table

#### Scenario: Default order
- **WHEN** the type has the fields `title` (weight 0), `body` (weight 20) and `summary` (weight 10)
- **THEN** without sorting the table shows them in the order `title`, `summary`, `body`

#### Scenario: Search in the browser
- **WHEN** the user types text into the search of the table
- **THEN** the table narrows by the name or the key of the field without reloading the page and without a request to the server

#### Scenario: Type of another kind
- **WHEN** the user opens the page of fields for the key of a type of the kind `page`
- **THEN** the server responds with 404

#### Scenario: Guest
- **WHEN** an unauthenticated visitor opens `/admin/content-types/news/fields`
- **THEN** they are redirected to the login page

#### Scenario: Fields of the panels are not shown
- **WHEN** the user opens the page of fields of a type that has the fields `title`, `meta_title`, `section_title` and `body`
- **THEN** the table has `title` and `body`, and there is no `meta_title` and `section_title`

### Requirement: Зміна порядку полів перетягуванням
The table of fields SHALL allow changing the order of fields by dragging the rows by the handle in the first cell (also with the keyboard). The dragging SHALL be available only when the whole list of fields is visible in the original order: without search, without sorting by a column, and all fields fit on one page; otherwise the handles are inactive with a hint on how to make the dragging available. After release the row SHALL immediately take the new place, and the new order SHALL be saved on the server: the weights of all shown fields of the type are rewritten sequentially from 0 in the new order, and after reloading the page the order remains the same. If the saving fails, the order in the table SHALL return to the previous one, and the user SHALL see an error. The server SHALL accept only the complete set of keys: exactly all shown fields of this type (without the fields of the panels), without repetitions and foreign keys. The system field `title` can also be moved.

#### Scenario: Dragging
- **WHEN** the user drags the field `body` above the field `summary`
- **THEN** `body` goes before `summary`, and after reloading the order is the same

#### Scenario: Weights after the change
- **WHEN** the order is saved for a type with three fields
- **THEN** their weights are 0, 1, 2 in the new order

#### Scenario: Search or sorting is active
- **WHEN** the user has entered a search or sorted the table by a column
- **THEN** the drag handles are inactive, changing the order is impossible

#### Scenario: Failure of saving
- **WHEN** the server rejects the new order
- **THEN** the rows return to their previous places, the user sees an error

#### Scenario: Incomplete or foreign set of keys
- **WHEN** the server receives an order without one of the fields of the type, with a repetition, or with a key that the type does not have
- **THEN** the order is rejected, the weights are not changed

#### Scenario: Type of another kind
- **WHEN** a request to change the order arrives for the key of a type of the kind `page`
- **THEN** the server responds with 404

#### Scenario: Order does not touch the fields of the panels
- **WHEN** the user rearranges the shown fields of the type
- **THEN** the values of the fields of the panels and their presence do not change, and a key of a panel field in the request is rejected as foreign

## ADDED Requirements

### Requirement: Поля панелей не налаштовуються через вкладку «Поля»
The system fields of the panels — `meta_title`, `meta_description`, `meta_image` and `section_title`, `section_description`, `section_cover` — are configured by the system, the user SHALL NOT configure them: they SHALL be absent from the table of fields of a type of any kind, and the edit page, saving changes and deleting such a field SHALL respond with 404. The system field `title` remains in the table and is configured as before. The values of the fields of the panels are edited only in the tabs «Розділ» and «SEO» of the type and in the section «SEO» of a record; hiding changes neither the fields themselves, nor their values, nor the possibility to use `meta_*` as a column of the table of records.

#### Scenario: Type of pages
- **WHEN** the user opens the page of fields of a type of the kind `page`
- **THEN** the fields `meta_*` are not shown in the table

#### Scenario: Direct opening of the edit form
- **WHEN** the user opens `/admin/content-types/news/fields/meta_title/edit`
- **THEN** the server responds with 404

#### Scenario: Saving and deleting
- **WHEN** a change or a deletion of the field `section_title` arrives at the server
- **THEN** the server responds with 404, the field and its value remain unchanged

#### Scenario: Column of the table of records
- **WHEN** the settings of the list of the type refer to `meta_title`
- **THEN** the field is available as a column of the table of records, as before
