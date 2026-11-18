## ADDED Requirements

### Requirement: Row actions
The table SHALL support an optional actions column: if the page provides actions for rows, each row SHALL have at the end a "⋮" menu button with an accessible name, which opens the dropdown menu of actions of this row. The actions column SHALL be outside the choice of visible columns (it cannot be hidden), SHALL NOT take part in search, filters and sorting, and SHALL work the same way in server and client modes. Without provided actions the table SHALL look and behave as before.

#### Scenario: Opening the row menu
- **WHEN** the user clicks "⋮" in a row of a table with actions
- **THEN** the menu with the actions of exactly this row opens, and it can be opened with the keyboard, an item chosen and the menu closed

#### Scenario: Table without actions
- **WHEN** the page provides no row actions
- **THEN** there is no additional column, and the table markup does not change

#### Scenario: Actions do not affect the column choice
- **WHEN** the user opens the menu for choosing the visible columns
- **THEN** the actions column is not there, and it remains visible

### Requirement: Actions in the toolbar
The table SHALL let the page place its own controls (for example, a create button) in the toolbar next to the search, filters and column choice. On narrow screens these controls SHALL wrap without breaking the layout.

#### Scenario: Button in the toolbar
- **WHEN** the page passed a button to the toolbar actions slot
- **THEN** it is displayed in the toolbar and is not reset when the search, filters or page change
