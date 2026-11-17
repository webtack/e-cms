# Spec Delta

## ADDED Requirements

### Requirement: Look of a table in rich text
A table in a `richtext` value SHALL be shown on the public site with visible cell borders, padding inside the cells, and header cells that differ from ordinary cells. A table wider than the content SHALL scroll horizontally inside its own area instead of widening the page. The text of every cell SHALL be left-aligned unless a paragraph inside the cell sets its own alignment.

#### Scenario: A table with a header row
- **WHEN** a `richtext` field holds a table with a header row and two rows of data
- **THEN** the cells have borders and padding, and the header cells differ from the data cells

#### Scenario: A wide table on a phone
- **WHEN** a `richtext` field holds a table with many columns and the page is opened at the width of a phone
- **THEN** the table scrolls horizontally inside its own area and the page itself does not scroll sideways

#### Scenario: A paragraph alignment inside a cell
- **WHEN** a cell holds a paragraph saved with `text-align: center`
- **THEN** the text of that cell is centered and the other cells stay left-aligned
