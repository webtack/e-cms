## MODIFIED Requirements

### Requirement: Field "rich text" in the form and the list of records
For a field of the type `richtext` the record form SHALL show a formatted text editor instead of a plain text field: a toolbar with headings, bold, italic, underline, strikethrough, alignment (a drop-down list: left, centre, right, justify), text colour, background colour, bulleted and numbered lists, a link, inserting a YouTube video, inserting a frame, choosing emoji, a button for clearing the formatting, a button for viewing the raw HTML ("Source"), a button for expanding to full screen; the editing area SHALL show the formatting while typing. Images and files in the editor SHALL be unavailable. The edit form SHALL be filled with the stored value with its formatting. The editor SHALL have an accessible name with the label of the field, an error mark from the server next to the field, and in a multiple field — a separate editor in each row. Saving the record SHALL send the HTML of the value; an empty editor SHALL send an empty value. In the table of records the value of `richtext` SHALL be output as plain text without tags, shortened to a reasonable length, and not as raw HTML.

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

## ADDED Requirements

### Requirement: Extended actions of the rich text editor
The editor `richtext` SHALL provide the following actions; each button SHALL have an accessible name, and the state buttons (underline, strikethrough, source, full-screen mode) SHALL mark the active state through `aria-pressed`.
- Underline and strikethrough SHALL toggle the formatting of the selected text.
- Alignment SHALL be applied to the block (paragraph, heading) with the cursor; the alignment button SHALL show the current alignment (left, if the block is not aligned), and in the menu the current item SHALL be marked.
- The text colour and the background colour SHALL open a palette with ready colours and a field for the code `#rrggbb`, apply the colour to the selected text and have the action "Reset colour".
- Clearing the formatting SHALL remove from the selected text all marks except bold, italic, underline and link, remove alignment, colour and background, and the headings (`h2`–`h4`) SHALL remain headings.
- Inserting a YouTube video SHALL accept the address of a video (`youtube.com/watch?v=`, `youtu.be/`) and insert an embedded player; an invalid address SHALL give an error next to the field without insertion.
- Inserting a frame SHALL accept in one field both an address `https` and a whole `<iframe>` code (from it `src`, `width`, `height`, `title`, `allowfullscreen`, `allow` SHALL be carried over, and the other attributes dropped), and insert a frame only from an allowed host (and path prefix), in particular Google Drive and social networks; an address from a disallowed host SHALL give an error next to the field without insertion.
- Choosing an emoji SHALL insert the character at the cursor position and leave the focus in the editor.
- The button "Source" SHALL show the raw HTML of the value in a text field instead of the formatted text, apply its edits to the editor, and disable the other formatting buttons while the mode is on; returning SHALL show the edited content.
- Expanding to full screen SHALL stretch the editor with the toolbar to the whole window, return with the button or the `Escape` key, and SHALL not lose the content or the cursor.

#### Scenario: Underline and strikethrough
- **WHEN** the user selects text and presses "Underlined", then "Strikethrough", and saves the record
- **THEN** after a reload the text is underlined and struck through

#### Scenario: Alignment
- **WHEN** the user puts the cursor in a paragraph and presses "Centre"
- **THEN** the paragraph is centred, in the menu "Centre" is marked, and the saved HTML contains `text-align: center`

#### Scenario: Text and background colour
- **WHEN** the user selects text and chooses a text colour and a background colour
- **THEN** the text is shown in the chosen colours, and after saving and a reload the colours are kept

#### Scenario: Resetting the colour
- **WHEN** the user selects coloured text and chooses "Reset colour"
- **THEN** the text is shown in the default colour

#### Scenario: Clearing the formatting
- **WHEN** the user selects bold underlined text with a colour, a background and alignment in a heading and presses "Clear formatting"
- **THEN** bold, underline and the heading remain, and the colour, the background and the alignment disappear

#### Scenario: Inserting a YouTube video
- **WHEN** the user inserts the address `https://youtu.be/dQw4w9WgXcQ`
- **THEN** a player appears in the editor, and the saved HTML contains an `iframe` with the address `youtube-nocookie.com/embed/dQw4w9WgXcQ`

#### Scenario: Invalid video address
- **WHEN** the user enters an address that is not a YouTube address
- **THEN** an error is shown next to the field, and the content of the editor is not changed

#### Scenario: Inserting a frame by link
- **WHEN** the user inserts an `https` address on an allowed host
- **THEN** a frame appears in the editor, and it is stored in the record

#### Scenario: Inserting a frame by iframe code
- **WHEN** the user inserts `<iframe src="https://www.youtube-nocookie.com/embed/abc" width="560" height="315" title="Відео" allow="autoplay; camera" frameborder="0" allowfullscreen></iframe>`
- **THEN** a frame appears in the editor, and the saved `iframe` has `src`, `width="560"`, `height="315"`, `title`, `allowfullscreen`, `allow="autoplay"` and has no `frameborder` and `camera`

#### Scenario: Google Drive frame
- **WHEN** the user inserts the address `https://drive.google.com/file/d/abc/preview`
- **THEN** a frame appears in the editor, and it is stored in the record

#### Scenario: Google Forms frame
- **WHEN** the user inserts the "Embed" code from Google Forms (`<iframe src="https://docs.google.com/forms/d/e/abc/viewform?embedded=true">`)
- **THEN** the form appears in the editor, and the record stores a frame with this address

#### Scenario: Disallowed frame
- **WHEN** the user inserts an address on a host that is not in the allowed list
- **THEN** an error is shown next to the field, and no frame is inserted

#### Scenario: Emoji
- **WHEN** the user chooses an emoji in the panel
- **THEN** the symbol is inserted at the cursor position, and the focus stays in the editor

#### Scenario: Raw HTML
- **WHEN** the user turns on "Source", changes the HTML in the field and turns "Source" off
- **THEN** the changed content is visible in the editor, and while the mode was on, the formatting buttons were disabled

#### Scenario: Full-screen mode
- **WHEN** the user presses "Full screen"
- **THEN** the editor takes the whole window, the text and the cursor are kept, and the `Escape` key returns the normal view
