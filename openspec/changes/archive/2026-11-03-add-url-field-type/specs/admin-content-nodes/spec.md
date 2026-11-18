## ADDED Requirements

### Requirement: Елемент введення поля «посилання»
The form of creating and editing a record SHALL show for a field of the type `url` two single-line text fields in one row: «Link» (the link) and «Title» (the title, optional). Both fields SHALL have accessible names and be filled with the saved value during editing. Server errors SHALL be shown next to the field, and the entered data SHALL be kept. An empty «Link» field SHALL clear the value, even if «Title» is filled. For a multiple field each element of the list SHALL have its own pair of fields according to the general rules of the form. The field SHALL NOT have modal windows and requests to external services.

#### Scenario: Creating a record with a link
- **WHEN** the user enters `/about` in «Link», `Про нас` in «Title», and saves the record
- **THEN** the record is saved, and when it is opened again both fields contain the entered values

#### Scenario: Link without a title
- **WHEN** the user fills in only «Link»
- **THEN** the record is saved without a title

#### Scenario: Invalid link
- **WHEN** the user enters `javascript:alert(1)` in «Link» and saves
- **THEN** an error is shown next to the field, the entered values remain in the fields

#### Scenario: Multiple field
- **WHEN** the user adds several elements to a multiple field `url`
- **THEN** each element has its own pair «Link» and «Title», is removed and reordered according to the general rules

### Requirement: Значення поля «посилання» в таблиці записів
If a field of the type `url` is selected as a column of the list of records, its cell SHALL show an interactive link: the text is the title of the value, and without a title — the address itself; the link SHALL open in a new tab (`target="_blank"`, `rel="noopener noreferrer"`), and its `href` is the saved link. For a multiple field the general display of several values in a cell SHALL apply. This concerns only the list of records, not the form. The column of the field `url` SHALL NOT be sortable, SHALL NOT be offered as the default sorting in the settings of the type, and the sorting parameter by it SHALL be ignored in the same way as for `youtube` and `term`.

#### Scenario: Cell with a title
- **WHEN** a record has a value `url` with the title `Про нас`
- **THEN** the cell shows a link with the text `Про нас`, which leads to the saved address and opens in a new tab

#### Scenario: Cell without a title
- **WHEN** a record has the value `url` `/about` without a title
- **THEN** the cell shows a link with the text `/about`, which opens in a new tab

#### Scenario: Sorting by the field
- **WHEN** the address of the list contains sorting by the field `url`
- **THEN** the parameter is ignored, the column is not marked as sorted
