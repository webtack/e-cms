# admin-data-table Specification

## Purpose

Gives the admin panel a single data table with search, filters, sorting, pagination, and selection of visible columns, whose state is kept in the page address.

## Requirements

### Requirement: Table state in the address
In server mode the table SHALL display the data selected by the server according to the address parameters (search, filters, sorting, page, page size), and SHALL update the address on each change of these parameters without a full page reload. A page reload or opening the address in a new tab SHALL restore the same state.

#### Scenario: Restoring the state
- **WHEN** the user applied a search, sorting, navigated to the second page, and then reloaded the page
- **THEN** the table shows the same data, and the search field, the sorting indicator, and the page number match the address

#### Scenario: Without a full reload
- **WHEN** the user changes the sorting
- **THEN** only the table data and the address are updated, and the admin shell remains mounted

### Requirement: Client mode
The table SHALL support a client mode for small sets: all rows are passed to the page at once, and search, filters, sorting, and pagination are performed by the browser without requests to the server. The visible capabilities, controls, the set of operators by column type, and the comparison rules SHALL match the server mode: search and the "contains" operator are case-insensitive, numbers are compared numerically, dates chronologically, empty values are always at the end regardless of the sort direction. The state of the client table SHALL live in the page's memory and SHALL NOT change the address.

#### Scenario: Without requests to the server
- **WHEN** the user changes the search, a filter, the sorting, or the page of a client table
- **THEN** the data is updated without any request to the server, and the address does not change

#### Scenario: Same behavior as the server mode
- **WHEN** the same data, search, filter, and sorting are applied in a client and a server table
- **THEN** both show the same set of rows in the same order

#### Scenario: Empty values
- **WHEN** a client table is sorted by a column in which some rows have no value
- **THEN** such rows are at the end both for ascending and for descending order

#### Scenario: Column visibility
- **WHEN** the user hides a column of a client table
- **THEN** the choice is saved in the same way as in a server table

### Requirement: Search
The table SHALL have a search field that is applied with a small delay after the input stops, and resets the pagination to the first page.

#### Scenario: Applying a search
- **WHEN** the user types a string in the search field and pauses the input
- **THEN** a search parameter appears in the address, only the matching rows are shown, and the first page is active

#### Scenario: Clearing the search
- **WHEN** the user clears the search field
- **THEN** the search parameter is removed from the address, and all rows are shown

### Requirement: Filters
The table SHALL allow adding several filters, each consisting of a column, an operator, and a value. The available operators and the type of the input element SHALL depend on the column type (text, number, date, boolean, choice from a list). The filters SHALL be combined with the AND condition, applying a filter SHALL reset the page to the first one, and the active filters SHALL be visible and removable one by one.

#### Scenario: Adding a numeric filter
- **WHEN** the user adds a filter on a numeric column with the operator "not less than" and the value 100
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
- **THEN** the search, the filters, and the sorting are cleared, and the first page is open

### Requirement: Sorting
The header of a column marked as sortable SHALL toggle the sorting in a cycle: ascending → descending → no sorting. Sorting by one column SHALL apply at a time; the current column and direction SHALL be visible in the header. Unsortable columns SHALL NOT react to clicks.

#### Scenario: Sorting cycle
- **WHEN** the user clicks the header of a sorted column three times
- **THEN** ascending sorting, descending sorting, and no sorting are applied successively, and the indicator and the address match each step

#### Scenario: Changing the column
- **WHEN** the sorting is active on column A, and the user clicks the header of column B
- **THEN** the sorting applies only to B in ascending order

#### Scenario: Unsortable column
- **WHEN** the user clicks the header of an unsortable column
- **THEN** the sorting and the address do not change

### Requirement: Pagination
The table SHALL show page navigation, a summary of the range of rows and the total count, and a choice of page size from a limited set. Changing the page size SHALL return to the first page. Unavailable actions (back on the first page, forward on the last page) SHALL be disabled.

#### Scenario: Going to the next page
- **WHEN** the user goes to the next page
- **THEN** the next rows are shown, the summary is updated (for example "16–30 of 42"), and the address contains the page number

#### Scenario: Boundaries
- **WHEN** the user is on the first page
- **THEN** the "to start" and "back" actions are disabled

#### Scenario: Page size
- **WHEN** the user changes the page size
- **THEN** the first page opens with the new number of rows

#### Scenario: Page out of range
- **WHEN** the address contains a page number greater than the number of pages
- **THEN** the last available page is shown without an error

### Requirement: Column visibility
The table SHALL have a menu for choosing visible columns. A column marked as non-hideable SHALL always be visible. The choice SHALL be saved in the user's browser separately for each table and restored on the next opening; without a saved choice the default visibility SHALL apply. Hiding a column SHALL NOT change the search, the filters, the sorting, or the page.

#### Scenario: Hiding a column
- **WHEN** the user unchecks a column in the menu
- **THEN** the column disappears from the table, and the rest of the table state does not change

#### Scenario: Saving the choice
- **WHEN** the user has hidden a column and reloaded the page
- **THEN** the column remains hidden

#### Scenario: Independence of tables
- **WHEN** the user has hidden a column in one table
- **THEN** the column visibility of another table has not changed

#### Scenario: Non-hideable column
- **WHEN** a column is marked as non-hideable
- **THEN** in the menu it is shown disabled for changes and remains in the table

#### Scenario: Storage unavailable
- **WHEN** the browser does not allow storing data
- **THEN** the menu works within the current page, and no errors are shown to the user

#### Scenario: Hidden column in a filter
- **WHEN** an active filter or sorting refers to a column that is hidden
- **THEN** the filter and the sorting remain valid and visible in the list of active filters

### Requirement: Empty state and loading
When there are no rows for the current parameters, the table SHALL show an empty state (with a separate text if search or filters are active, and with the reset action). While a request is in progress, the table SHALL show a loading indicator and keep the previous data, without blocking the rest of the page.

#### Scenario: Nothing found
- **WHEN** the search finds no rows
- **THEN** a message that nothing was found is shown, together with the reset action of the parameters

#### Scenario: No data at all
- **WHEN** the source has no rows and no parameters are set
- **THEN** the empty state is shown without the reset action

### Requirement: Invalid address parameters
Invalid or unknown address parameters (a column, an operator, a direction, a page size, a page number) SHALL NOT lead to a server error: they are ignored, the table is shown with the valid values, and the user sees a message about the ignored parameters.

#### Scenario: Unknown sorting column
- **WHEN** the address specifies sorting by a non-existent column
- **THEN** the page opens without sorting, and a message about the invalid parameter is shown

#### Scenario: Oversized page size
- **WHEN** the address specifies a page size above the maximum
- **THEN** the maximum allowed size applies

### Requirement: Accessibility and adaptivity
The table SHALL be operable from the keyboard (the controls are focusable, the header of a sorted column is a button with the state `aria-sort`), have text labels for icons, and, at any window width (mobile and desktop, with the sidebar expanded or collapsed), whenever its columns do not fit the available width, SHALL scroll horizontally inside its container, without widening the page or the content area of the shell.

#### Scenario: aria-sort
- **WHEN** the table is sorted by a column in ascending order
- **THEN** the header of this column has `aria-sort="ascending"`, and the other sortable columns have `aria-sort="none"`

#### Scenario: Narrow screen
- **WHEN** the window width is 375 px
- **THEN** the page has no horizontal scrolling, and the table scrolls inside the container

#### Scenario: Desktop screen with a wide table
- **WHEN** the window is desktop width (for example 1280 px) and the columns of the table do not fit the content area
- **THEN** the page has no horizontal scrolling, the content area does not grow beyond the window, and the table scrolls horizontally inside its container

#### Scenario: Sidebar state does not matter
- **WHEN** the table does not fit on a desktop screen and the user collapses or expands the sidebar
- **THEN** the table still scrolls inside its container and the page has no horizontal scrolling

#### Scenario: Table that fits
- **WHEN** the columns of the table fit the available width
- **THEN** the table container shows no horizontal scrollbar

### Requirement: Localization
All texts of the component (labels, operators, messages, the pagination summary) SHALL be displayed in the interface language of the user (uk/en).

#### Scenario: Ukrainian
- **WHEN** the interface language is `uk`
- **THEN** the button labels, the filter operators, and the pagination summary are in Ukrainian

### Requirement: Demo page only outside production
For checking the component, there SHALL be a page in the admin panel, available only to authenticated users and only in environments other than production; in production the page SHALL respond "not found".

#### Scenario: Local environment
- **WHEN** an authenticated user opens the demo page in a non-production environment
- **THEN** the server and client tables with test data of all supported column types are shown, and they do not affect each other

#### Scenario: Production
- **WHEN** the application runs in production
- **THEN** the address of the demo page returns "not found"

### Requirement: Row actions
The table SHALL support an optional actions column: if the page has provided actions for the rows, each row SHALL have at the end a "⋮" menu button with an accessible name, which opens the dropdown menu of actions of this row. The actions column SHALL be outside the selection of visible columns (it cannot be hidden), SHALL NOT take part in search, filters, and sorting, and SHALL work the same way in server and client modes. Without the provided actions the table SHALL look and behave as before.

#### Scenario: Opening a row menu
- **WHEN** the user clicks "⋮" in a row of the table with actions
- **THEN** the menu with the actions of exactly this row opens, and it can be opened by the keyboard, an item chosen, and closed

#### Scenario: Table without actions
- **WHEN** the page provides no row actions
- **THEN** there is no additional column, and the table markup does not change

#### Scenario: Actions do not affect the choice of columns
- **WHEN** the user opens the menu for choosing visible columns
- **THEN** the actions column is not there, and it remains visible

### Requirement: Actions in the toolbar
The table SHALL allow the page to place its own controls (for example, a create button) in the toolbar next to the search, filters, and column selection. On narrow screens these controls SHALL wrap without breaking the layout.

#### Scenario: Button in the toolbar
- **WHEN** the page has passed a button into the toolbar actions slot
- **THEN** it is displayed in the toolbar and is not reset when the search, filters, or page change

### Requirement: Column width limit
A column description SHALL have an optional width (a CSS length) that acts as a maximum. For a column with it, the header (`th`) SHALL receive this width as `max-width`, the cell content SHALL NOT exceed it, and a longer text SHALL be cut into one line with an ellipsis. Columns without it SHALL behave as before. The maximum width affects only the display: search, filters, sorting, column visibility, and the values in the rows do not change. The full text of a cut cell SHALL remain available (a hint or the custom content of the cell).

#### Scenario: A long text is cut
- **WHEN** a column has a maximum width, and the value in the cell is longer than it
- **THEN** the cell is not wider than the set width, the text ends with an ellipsis and occupies one line

#### Scenario: A short text
- **WHEN** the value in the cell is shorter than the maximum width
- **THEN** the cell is displayed without changes and does not stretch to the maximum width

#### Scenario: Column header
- **WHEN** a column has a maximum width
- **THEN** its header has `max-width` equal to the set width, and the header text is not cut

#### Scenario: Column without a limit
- **WHEN** a column has no maximum width
- **THEN** its cells and header are displayed as before

#### Scenario: The page does not widen
- **WHEN** the table has a column with a maximum width and a very long value
- **THEN** the table does not go beyond its width, and the page gets no horizontal scrolling on a 375px screen

### Requirement: Drag and drop of rows
The client table SHALL have an optional ability to change the order of rows by drag and drop. When it is enabled, each row SHALL have a handle in the first cell, also available from the keyboard (focus on the handle, a lifted row is moved with the arrows), with a translated accessible name. After the drag ends, the table SHALL notify the page about the new order of the identifiers of all rows and show it at once. The handles SHALL be inactive while the order is not the original and complete: when a search is active, a column sorting is applied, or not all rows fit on one page. Without this ability the table SHALL work as before, without handles. In server mode the ability is unavailable.

#### Scenario: Handles only when enabled
- **WHEN** the table is opened without the ability to drag
- **THEN** there are no handles in the rows, and the markup does not differ from the previous one

#### Scenario: Drag and drop with the mouse
- **WHEN** the user drags a row to a new place
- **THEN** the row takes the new place, and the page receives the full new order of identifiers

#### Scenario: Drag and drop with the keyboard
- **WHEN** the user focuses the handle, lifts the row with space, moves it with the arrows, and confirms
- **THEN** the result is the same as after dragging with the mouse

#### Scenario: Handles inactive
- **WHEN** a search is entered in the table, a sorting is selected, or there are more rows than fit on the page
- **THEN** the handles are inactive with a hint, and the order cannot be changed

### Requirement: Quick filter in the toolbar
The table SHALL allow marking a column with a list of values as a quick filter: then a dropdown list of the values of this column with a reset option ("All") appears in the toolbar next to the search field, and the button and the filter builder are not needed for it. Choosing a value SHALL apply an "equals" filter on this column in the same way as a filter from the builder: the state in the address (in server mode), the chip of the active filter under the panel, the reset by the "Reset" button, the change to the first page. The list SHALL display the current state: show the chosen value when the filter is set by the address, a chip, or the builder, and "All" when there is no filter. A table without marked columns SHALL look and work as before.

#### Scenario: Choosing a value
- **WHEN** the table has the column "Type" as a quick filter, and the user chooses a value in the list
- **THEN** the table shows the rows with this value, there is a chip under the panel, and the address contains the filter

#### Scenario: Resetting the choice
- **WHEN** the user chooses "All" in the list
- **THEN** the filter is removed, and the chip disappears

#### Scenario: Synchronization with the chip
- **WHEN** the user removes the chip of the filter
- **THEN** the list returns to "All"

#### Scenario: Resetting the table
- **WHEN** the user clicks "Reset" with a value chosen
- **THEN** the filter is removed, and the list shows "All"

#### Scenario: Without quick filters
- **WHEN** no column is marked as a quick filter
- **THEN** there are no additional lists in the panel

### Requirement: Boolean cell and header styling
By default the table SHALL show a boolean value as a text-only badge ("Yes" as a success badge, "No" as quiet muted text), translated into the interface language; a column MAY request a warning tone for "No". The table header row SHALL be shown on a faint tint of the accent colour, and a data row SHALL get a subtle highlight on hover. These changes SHALL not alter sorting, filtering or the filter values "yes/no".

#### Scenario: Default boolean cell
- **WHEN** a table has a boolean column without a custom cell
- **THEN** a true value is shown as a "Yes" badge and a false value as "No" text, and filtering by that column still offers the values "Yes" and "No"

#### Scenario: Warning tone for No
- **WHEN** a boolean column requests the warning tone and a row has the value false
- **THEN** that cell shows "No" as an amber badge

#### Scenario: Row hover
- **WHEN** the user moves the pointer over a data row
- **THEN** the row is subtly highlighted and its content does not shift
