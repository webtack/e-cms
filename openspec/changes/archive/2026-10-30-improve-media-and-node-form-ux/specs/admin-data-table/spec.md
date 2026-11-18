# Spec Delta

## ADDED Requirements

### Requirement: Quick filter in the toolbar
The table SHALL allow a column with a list of values to be marked as a quick filter: then a drop-down list of the values of this column with a reset option ("All") appears in the toolbar next to the search field, and the button and the constructor of filters are not needed for it. Choosing a value SHALL apply the "equals" filter on this column in the same way as a filter from the constructor: the state in the address (in server mode), a chip of the active filter under the panel, the reset by the button "Reset", the change of the page to the first. The list SHALL display the current state: it shows the selected value when the filter is set by the address, a chip or the constructor, and "All" when there is no filter. A table without marked columns SHALL look and work as before.

#### Scenario: Choosing a value
- **WHEN** the table has the column "Type" as a quick filter, and the user chooses a value in the list
- **THEN** the table shows the rows with this value, there is a chip under the panel, and the address contains the filter

#### Scenario: Resetting the choice
- **WHEN** the user chooses "All" in the list
- **THEN** the filter is removed, the chip disappears

#### Scenario: Synchronization with the chip
- **WHEN** the user removes the chip of the filter
- **THEN** the list returns to "All"

#### Scenario: Resetting the table
- **WHEN** the user presses "Reset" with a value chosen
- **THEN** the filter is removed and the list shows "All"

#### Scenario: No quick filters
- **WHEN** no column is marked as a quick filter
- **THEN** there are no additional lists in the panel
