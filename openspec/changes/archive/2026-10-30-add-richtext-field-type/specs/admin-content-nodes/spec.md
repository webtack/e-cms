## ADDED Requirements

### Requirement: Field "rich text" in the form and the list of records
For a field of the type `richtext` the record form SHALL show a formatted text editor instead of a plain text field: a toolbar with headings, bold, italic, bulleted and numbered lists and a link, and the editing area SHALL show the formatting while typing. Images and files in the editor SHALL be unavailable. The edit form SHALL be filled with the stored value with its formatting. The editor SHALL have an accessible name with the label of the field, an error mark from the server next to the field, and in a multiple field — a separate editor in each row. Saving the record SHALL send the HTML of the value; an empty editor SHALL send an empty value. In the table of records the value of `richtext` SHALL be output as plain text without tags, shortened to a reasonable length, and not as raw HTML.

#### Scenario: Formatting in the form
- **WHEN** the user in the editor of the field `richtext` types text, makes it bold, adds a bulleted list and saves the record
- **THEN** the record is saved, and after reopening the edit form the bold text and the list are visible in the editor

#### Scenario: Link
- **WHEN** the user selects text, adds the link `https://example.com` and saves the record
- **THEN** after reopening the form the text is a link to this address

#### Scenario: No images
- **WHEN** the user opens the toolbar of the editor
- **THEN** it has no actions for inserting an image or a file

#### Scenario: Empty value
- **WHEN** the user leaves the editor of an optional field `richtext` empty and saves the record
- **THEN** the record is saved without a value in this field

#### Scenario: Error of the field
- **WHEN** the server rejects the value of `richtext`
- **THEN** the error is shown next to the editor, and the entered content is kept in the form

#### Scenario: Value in the table of records
- **WHEN** the field `richtext` with the value `<p>Привіт, <strong>світе</strong></p>` is visible in the list
- **THEN** the cell shows "Привіт, світе" without tags
