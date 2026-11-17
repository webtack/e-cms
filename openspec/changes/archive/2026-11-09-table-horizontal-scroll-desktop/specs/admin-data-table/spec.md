# Spec Delta

## MODIFIED Requirements

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
