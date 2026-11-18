# admin-content-nodes Specification (delta)

## ADDED Requirements

### Requirement: Мініатюра `thumb` у комірці списку записів
The cell of a field of the type `image` in the table of records SHALL show the style `thumb` of the image (the field `preview` of the data of the file; if there is none — the original). The thumbnail SHALL be a link to the original with `target="_blank"` and `rel="noopener noreferrer"` and SHALL have `cursor-pointer`. For a multiple field this applies to each thumbnail.

#### Scenario: Thumbnail in the cell
- **WHEN** a record has a field `image` with a file
- **THEN** the `src` of the image in the cell is the address of the style `thumb`, and the `href` of the link is the address of the original

#### Scenario: Cursor
- **WHEN** the user hovers the cursor over a thumbnail in the cell
- **THEN** the cursor is a pointer (`cursor-pointer`)
