## ADDED Requirements

### Requirement: Limiting the width of a column
The column description SHALL have an optional width (a CSS length) that acts as a maximum. For a column with it, the header (`th`) SHALL get this width as `max-width`, the content of the cells SHALL NOT exceed it, and longer text SHALL be truncated to one line with an ellipsis. Columns without it SHALL behave as before. The maximum width affects only the display: search, filters, sorting, column visibility and the values in the rows do not change. The full text of a truncated cell SHALL remain available (a hint or the custom content of the cell).

#### Scenario: Long text is truncated
- **WHEN** a column has a maximum width, and the value in a cell is longer than it
- **THEN** the cell is not wider than the given width, the text ends with an ellipsis and takes one line

#### Scenario: Short text
- **WHEN** the value in a cell is shorter than the maximum width
- **THEN** the cell is displayed without changes and does not stretch to the maximum width

#### Scenario: Column header
- **WHEN** a column has a maximum width
- **THEN** its header has `max-width` equal to the given width, and the header text is not shortened

#### Scenario: Column without a limit
- **WHEN** a column has no maximum width
- **THEN** its cells and header are displayed as before

#### Scenario: The page does not expand
- **WHEN** the table has a column with a maximum width and a very long value
- **THEN** the table does not go beyond its width, and the page gets no horizontal scrolling on a 375px screen
