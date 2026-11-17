# Spec Delta

## ADDED Requirements

### Requirement: Responsive widget grid
The dashboard SHALL lay its widgets out in a grid that fits the width of the content area at any screen width: no widget SHALL be wider than the content area, and the page SHALL NOT scroll horizontally because of the widgets. A widget whose content is wider than the available space (for example a long node title) SHALL shorten that content inside its own card instead of widening the card. The number of columns SHALL depend on the width of the content area, not on the width of the browser window, so that it follows the state of the sidebar: one column when the content area is narrow, and two columns when it is wide enough; the grid SHALL NOT have more than two columns. Widgets SHALL keep their order (read by rows, left to right), and widgets in one row SHALL have the same height (the height of the tallest one). The "Recent changes" widget and the latest uploads of the "Media Library" widget SHALL list up to 7 rows; the other list widgets keep up to 5.

#### Scenario: Narrow screen
- **WHEN** a user opens the dashboard in a window about 400 px wide, and a recent node has a very long title
- **THEN** the widgets are one under another, each as wide as the content area, the long title is shortened, and the page has no horizontal scroll

#### Scenario: Wide screen
- **WHEN** a user opens the dashboard on a wide screen with the sidebar expanded
- **THEN** the widgets are in two columns in the usual order, and never in three

#### Scenario: Columns follow the sidebar
- **WHEN** the window width stays the same, and the user collapses the sidebar so that the content area becomes wide enough for two columns (or expands it so that it becomes too narrow)
- **THEN** the grid changes the number of columns to match the width of the content area

#### Scenario: Widgets of different height
- **WHEN** two widgets with a different number of rows are in the same row of the grid
- **THEN** both widgets have the height of the taller one

#### Scenario: Longer recent and media lists
- **WHEN** more than 7 nodes were changed and more than 7 files were uploaded
- **THEN** "Recent changes" shows the 7 newest nodes and the media widget shows the 7 latest uploads
