# Spec Delta

## ADDED Requirements

### Requirement: Tables in the formatted text editor
The `richtext` editor SHALL display, edit, and save tables. The toolbar SHALL have a button "Table" with an accessible name. Outside a table it SHALL insert a table of three rows and three columns whose first row consists of header cells, with the cursor in the first cell; inside a table it SHALL open a menu of table actions: add a row above, add a row below, delete the row, add a column before, add a column after, delete the column, switch the header row on or off, and delete the table. The menu actions SHALL work on the row and the column with the cursor. A cell SHALL hold text with the same inline formatting as a paragraph (bold, italic, underline, links, color), and the content of a cell SHALL be saved as paragraphs. The button "Table" SHALL be disabled, like the other formatting buttons, while the mode "Source" is on.

A table that is already in the value (written by the editor, imported from another site, or saved with `colspan` and `rowspan`) SHALL open in the editor without losing the rows, the columns, the header cells, the cell spans, or the text, and SHALL be saved with the same structure after the node is saved without edits to the table. Table rows inside `thead`, `tbody`, and `tfoot` SHALL be read as ordinary rows of one table. The editor SHALL show a table with visible cell borders, and a table wider than the editor SHALL scroll horizontally instead of widening the form.

#### Scenario: Inserting a table
- **WHEN** the user clicks "Table" with the cursor outside a table, types `A` in the first cell, and saves the node
- **THEN** after reloading the editor shows a table of three rows and three columns, the first row consists of header cells, and the first cell contains `A`

#### Scenario: Adding and deleting a row and a column
- **WHEN** the user places the cursor in a cell of a table, chooses "Add row below" and "Add column after" in the menu "Table", and saves the node
- **THEN** after reloading the table has one more row and one more column, and choosing "Delete row" and "Delete column" removes them again

#### Scenario: Switching the header row
- **WHEN** the user places the cursor in the first row and chooses to switch the header row off
- **THEN** the cells of the first row are ordinary cells, and the same choice again makes them header cells

#### Scenario: Deleting the table
- **WHEN** the user places the cursor in a cell and chooses "Delete table"
- **THEN** the table is removed from the text, and the surrounding paragraphs remain

#### Scenario: Menu outside a table
- **WHEN** the cursor is in a paragraph outside a table
- **THEN** clicking "Table" inserts a table and does not open the menu of table actions

#### Scenario: An existing table survives opening and saving
- **WHEN** a node whose `richtext` value holds a table with a header row, a cell with `colspan="2"`, and text in every cell is opened and saved without edits to the table
- **THEN** the saved value holds a table with the same rows, header cells, `colspan="2"`, and cell text

#### Scenario: A table from the legacy markup
- **WHEN** a node is opened whose value has rows in `thead` and `tbody` and paragraphs inside the cells
- **THEN** the editor shows one table with all the rows in their order, and saving keeps the text of every cell

#### Scenario: Source mode disables the button
- **WHEN** the user switches on the mode "Source"
- **THEN** the button "Table" is disabled, and it is enabled again after returning from "Source"

#### Scenario: A wide table does not widen the form
- **WHEN** the value holds a table with more columns than fit in the editor
- **THEN** the table scrolls horizontally inside the editor, and the node form keeps its width
