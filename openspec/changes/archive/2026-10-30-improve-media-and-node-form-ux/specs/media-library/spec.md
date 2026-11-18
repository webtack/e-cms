# Spec Delta

## ADDED Requirements

### Requirement: File type in the media library
The media library table and the file details panel SHALL show the file type by a short extension label (for example, `docx`, `pdf`, `jpg`), not by the full MIME type. Sorting by the type column SHALL remain working.

#### Scenario: Type column
- **WHEN** the storage has a Word file
- **THEN** the "Type" column of this file shows `docx`

#### Scenario: File details
- **WHEN** the user opens the details of a JPEG image
- **THEN** the subtitle of the panel shows `jpg`

### Requirement: Media library filter by type
The media library toolbar SHALL contain a drop-down type list next to the search field, with the options "All types" and the short labels of the types accepted by the upload profiles. Choosing a type SHALL show only the files of this type, add a chip of the active filter under the panel (removed with its ×), and be stored in the address of the page. The button "Reset" SHALL reset the type too. The filter SHALL be applied on the server before pagination and SHALL work together with the search and sorting. A label that is not in the list SHALL be ignored without an error.

#### Scenario: Choosing a type
- **WHEN** the storage has files `docx`, `pdf` and `jpg`, and the user chooses the type `pdf` in the list
- **THEN** the table shows only the `pdf` files, a chip of the filter appears under the panel, and the total count and the pages match this choice

#### Scenario: All types
- **WHEN** a type is chosen, and the user chooses "All types"
- **THEN** the filter is removed, the chip disappears, and the table shows all files

#### Scenario: Removing the chip
- **WHEN** the user removes the chip of the filter by type
- **THEN** the table shows all files, and the type list returns to "All types"

#### Scenario: Reset
- **WHEN** a type is chosen and a search is entered, and the user presses "Reset"
- **THEN** both the type and the search are reset

#### Scenario: Filter together with the search
- **WHEN** the user chooses the type `pdf` and searches for "звіт"
- **THEN** the table shows only the `pdf` files whose name contains "звіт"

#### Scenario: Filter in the address
- **WHEN** the user opens the address of the page with a filter by the type `docx`
- **THEN** the list shows `docx`, the chip is visible, and the table contains only `docx` files

#### Scenario: Unknown value
- **WHEN** the address contains a filter by a type that is not in the list
- **THEN** the filter is ignored, and the table shows all files
