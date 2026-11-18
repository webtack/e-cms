# Spec Delta

## Purpose

Provides the admin with a single data table with search, filters, sorting, pagination and a choice of visible columns, whose state is stored in the page address.

## ADDED Requirements

### Requirement: Table state in the address
In server mode the table SHALL display the data selected by the server according to the address parameters (search, filters, sorting, page, page size), and SHALL update the address on every change of these parameters without a full page reload. Reloading the page or opening the address in a new tab SHALL restore the same state.

#### Scenario: State restoration
- **WHEN** the user applied a search, sorting and moved to the second page, and then reloaded the page
- **THEN** the table shows the same data, and the search field, the sorting indicator and the page number match the address

#### Scenario: Without a full reload
- **WHEN** the user changes the sorting
- **THEN** only the table data and the address are updated, and the admin shell remains mounted

### Requirement: Client mode
The table SHALL support a client mode for small sets: all rows are passed to the page at once, and search, filters, sorting and pagination are performed by the browser without requests to the server. The available features, controls, the set of operators by column type and the comparison rules SHALL match the server mode: search and the "contains" operator are case-insensitive, numbers are compared numerically, dates chronologically, and empty values are always at the end regardless of the sort direction. The client table state SHALL live in the page memory and SHALL NOT change the address.

#### Scenario: No requests to the server
- **WHEN** the user changes the search, a filter, the sorting or the page of a client table
- **THEN** the data is updated without any request to the server, and the address does not change

#### Scenario: Behaviour identical to the server mode
- **WHEN** the same data, search, filter and sorting are applied in a client and a server table
- **THEN** both show the same set of rows in the same order

#### Scenario: Empty values
- **WHEN** a client table is sorted by a column where some rows have no value
- **THEN** such rows are at the end both for ascending and for descending order

#### Scenario: Column visibility
- **WHEN** the user hides a column of a client table
- **THEN** the choice is stored in the same way as in a server table

### Requirement: Search
The table SHALL have a search field that is applied with a short delay after the input stops, and resets pagination to the first page.

#### Scenario: Applying a search
- **WHEN** the user types a string into the search field and pauses the input
- **THEN** a search parameter appears in the address, only the matching rows are shown, and the first page is active

#### Scenario: Clearing the search
- **WHEN** the user clears the search field
- **THEN** the search parameter is removed from the address and all rows are shown

### Requirement: Filters
The table SHALL let the user add several filters, each consisting of a column, an operator and a value. The available operators and the input element type SHALL depend on the column type (text, number, date, boolean, select from a list). The filters SHALL be combined with an "AND" condition, applying a filter SHALL reset the page to the first one, and active filters SHALL be visible and removable one by one.

#### Scenario: Adding a numeric filter
- **WHEN** the user adds a filter on a numeric column with the operator "greater than or equal" and the value 100
- **THEN** the address contains this filter, and the table shows only the matching rows

#### Scenario: Operators by type
- **WHEN** the user selects a boolean column for a filter
- **THEN** only the equality operator with the values "yes/no" is available

#### Scenario: Range
- **WHEN** the user selects the range operator for a numeric or date column
- **THEN** the form asks for two bounds, and a filter without both bounds is not applied

#### Scenario: Removing a filter
- **WHEN** the user removes one of two active filters
- **THEN** the second one remains, and the address and the data are updated

#### Scenario: Reset
- **WHEN** the user clicks "Reset"
- **THEN** the search, the filters and the sorting are cleared, and the first page is open

### Requirement: Sorting
The header of a column marked as sortable SHALL toggle the sorting in a cycle: ascending → descending → no sorting. Only one column's sorting SHALL apply at a time; the current column and direction SHALL be visible in the header. Unsorted columns SHALL NOT react to clicks.

#### Scenario: Sorting cycle
- **WHEN** the user clicks the header of a sorted column three times
- **THEN** the ascending, descending and no-sorting states are applied in turn, and the indicator and the address match each step

#### Scenario: Changing the column
- **WHEN** the sorting is active on column A, and the user clicks the header of column B
- **THEN** the sorting applies only to B in ascending order

#### Scenario: Unsorted column
- **WHEN** the user clicks the header of an unsorted column
- **THEN** the sorting and the address do not change

### Requirement: Pagination
The table SHALL show page navigation, a summary of the row range and the total count, and a choice of page size from a limited set. Changing the page size SHALL return to the first page. Unavailable actions (back on the first page, forward on the last page) SHALL be disabled.

#### Scenario: Moving to the next page
- **WHEN** the user moves to the next page
- **THEN** the next rows are shown, the summary is updated (for example "16–30 of 42"), and the address contains the page number

#### Scenario: Boundaries
- **WHEN** the user is on the first page
- **THEN** the "to the start" and "back" actions are disabled

#### Scenario: Page size
- **WHEN** the user changes the page size
- **THEN** the first page opens with the new number of rows

#### Scenario: Page beyond the range
- **WHEN** the address contains a page number greater than the number of pages
- **THEN** the last available page is shown without an error

### Requirement: Column visibility
The table SHALL have a menu for choosing the visible columns. A column marked as not hideable SHALL always be visible. The choice SHALL be stored in the user's browser separately for each table and restored on the next opening; without a saved choice the default visibility SHALL apply. Hiding a column SHALL NOT change the search, filters, sorting or page.

#### Scenario: Hiding a column
- **WHEN** the user unchecks a column in the menu
- **THEN** the column disappears from the table, and the rest of the table state does not change

#### Scenario: Saving the choice
- **WHEN** the user has hidden a column and reloaded the page
- **THEN** the column remains hidden

#### Scenario: Independence of tables
- **WHEN** the user has hidden a column in one table
- **THEN** the column visibility of another table has not changed

#### Scenario: A column that cannot be hidden
- **WHEN** a column is marked as not hideable
- **THEN** in the menu it is displayed as disabled for changes and remains in the table

#### Scenario: Unavailable storage
- **WHEN** the browser does not allow storing data
- **THEN** the menu works within the current page, and no errors are shown to the user

#### Scenario: Hidden column in a filter
- **WHEN** an active filter or sorting refers to a column that is hidden
- **THEN** the filter and the sorting remain in effect and visible in the list of active filters

### Requirement: Empty state and loading
When there are no rows for the current parameters, the table SHALL show an empty state (with a separate text if a search or filters are active, together with a reset action). While a request is in progress, the table SHALL show a loading indicator and keep the previous data, without blocking the rest of the page.

#### Scenario: Nothing found
- **WHEN** the search finds no rows
- **THEN** a message that nothing was found is shown, together with the parameter reset action

#### Scenario: No data at all
- **WHEN** the source has no rows and no parameters are set
- **THEN** the empty state is shown without a reset action

### Requirement: Invalid address parameters
Invalid or unknown address parameters (column, operator, direction, page size, page number) SHALL NOT cause a server error: they are ignored, the table is shown with the valid values, and the user sees a message about the ignored parameters.

#### Scenario: Unknown sorting column
- **WHEN** the address specifies sorting by a non-existent column
- **THEN** the page opens without sorting, and a message about the invalid parameter is shown

#### Scenario: Page size too large
- **WHEN** the address specifies a page size above the maximum
- **THEN** the maximum allowed size is applied

### Requirement: Accessibility and responsiveness
The table SHALL be keyboard-operable (controls are focusable, the header of a sorted column is a button with the `aria-sort` state), have text labels for icons, and on narrow screens SHALL scroll horizontally within its container without widening the page.

#### Scenario: aria-sort
- **WHEN** the table is sorted by a column in ascending order
- **THEN** the header of this column has `aria-sort="ascending"`, and the other sortable columns have `aria-sort="none"`

#### Scenario: Narrow screen
- **WHEN** the window width is 375 px
- **THEN** the page has no horizontal scrolling, and the table scrolls within its container

### Requirement: Localization
All texts of the component (labels, operators, messages, the pagination summary) SHALL be displayed in the interface language of the user (uk/en).

#### Scenario: Ukrainian
- **WHEN** the interface language is `uk`
- **THEN** the button labels, the filter operators and the pagination summary are in Ukrainian

### Requirement: Demo page only outside production
To verify the component there SHALL be a page in the admin, available only to authenticated users and only in environments other than production; in production the page SHALL respond "not found".

#### Scenario: Local environment
- **WHEN** an authenticated user opens the demo page in a non-production environment
- **THEN** the server and client tables are shown with test data of all supported column types, and they do not affect each other

#### Scenario: Production
- **WHEN** the application runs in production
- **THEN** the address of the demo page returns "not found"
