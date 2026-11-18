# Spec Delta

## ADDED Requirements

### Requirement: Dragging rows
The client table SHALL have an optional capability of changing the order of rows by dragging. When it is enabled, each row SHALL have a handle in the first cell, also available from the keyboard (focus on the handle, the lifted row is moved with the arrow keys), with a translated accessible name. After dragging ends, the table SHALL notify the page of the new order of the identifiers of all rows and show it immediately. The handles SHALL be inactive while the order is not the original and complete one: when a search is active, a column sorting is applied, or not all rows fit on one page. Without this capability the table SHALL work as before, without handles. In server mode the capability is not available.

#### Scenario: Handles appear only when enabled
- **WHEN** the table is opened without the dragging capability
- **THEN** there are no handles in the rows, and the markup does not differ from the previous one

#### Scenario: Dragging with the mouse
- **WHEN** the user drags a row to a new place
- **THEN** the row takes the new place, and the page receives the full new order of identifiers

#### Scenario: Dragging with the keyboard
- **WHEN** the user focuses a handle, lifts the row with the space bar, moves it with the arrow keys and confirms
- **THEN** the result is the same as after dragging with the mouse

#### Scenario: Handles are inactive
- **WHEN** a search is entered in the table, a sorting is selected, or there are more rows than one page holds
- **THEN** the handles are inactive with a hint, and the order cannot be changed
