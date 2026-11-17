## MODIFIED Requirements

### Requirement: Limiting the width of the slug column
The service column "Slug" of the node table SHALL have a limited width: a long slug SHALL be cut into one line with an ellipsis, without stretching the table or pushing the other columns. The full slug SHALL be available in a hint. A click on the slug SHALL copy the full slug to the clipboard and show a short confirmation; the slug SHALL be operable by keyboard (focusable, `Enter` and `Space` copy it) and SHALL NOT open the node or trigger any other row action.

#### Scenario: Long slug
- **WHEN** a node has a slug longer than the width of the column
- **THEN** the slug takes one line and ends with an ellipsis, and the other columns remain in place

#### Scenario: Full slug in the hint
- **WHEN** the user hovers over a cut slug
- **THEN** they see the full slug

#### Scenario: Copying the slug
- **WHEN** the user clicks the slug of a node in the table
- **THEN** the full slug (not the shortened text) is placed in the clipboard, a confirmation is shown, and the user stays on the list

## ADDED Requirements

### Requirement: Block style dropdown of the formatted text editor
The toolbar of the `richtext` editor SHALL have one dropdown "Block style" instead of the single heading button, with the items "Normal" (a paragraph), "Heading 2", "Heading 3" and "Heading 4" (`h2` / `h3` / `h4`). The button of the dropdown SHALL have an accessible name, SHALL show the style of the block with the cursor (the label of the current item), and the current item SHALL be marked in the menu. Choosing an item SHALL turn the block with the cursor into that style (choosing the current heading level again SHALL NOT change the block; "Normal" turns a heading back into a paragraph) and SHALL leave the focus in the editor. Only a paragraph SHALL be shown as "Normal"; where the cursor is not in a paragraph or a heading `h2`–`h4` (for example, an image is selected), no item SHALL be marked as current and the button SHALL show a neutral icon. The dropdown SHALL be unavailable in the "Source" mode like the other formatting buttons.

#### Scenario: Heading 3
- **WHEN** the user puts the cursor in a paragraph, opens "Block style", chooses "Heading 3", and saves the node
- **THEN** the saved value holds the text in an `h3`, and after reopening the form the dropdown shows "Heading 3" at that text

#### Scenario: Heading 4
- **WHEN** the user puts the cursor in a paragraph, chooses "Heading 4", and saves the node
- **THEN** the saved value holds the text in an `h4`, and after reopening the form the dropdown shows "Heading 4" at that text

#### Scenario: Switching the level
- **WHEN** the cursor is in an `h2` and the user chooses "Heading 3"
- **THEN** the block becomes an `h3`, and the dropdown shows "Heading 3"

#### Scenario: Back to a paragraph
- **WHEN** the cursor is in an `h3` and the user chooses "Normal"
- **THEN** the block becomes a paragraph, and the dropdown shows "Normal"

#### Scenario: Neither a paragraph nor a heading
- **WHEN** an image is selected in the editor and the user opens the dropdown
- **THEN** no item is marked as current, and the button does not show "Normal"

#### Scenario: Current style in the menu
- **WHEN** the cursor is in an `h2` and the user opens the dropdown
- **THEN** the item "Heading 2" is marked as current

### Requirement: Node id in the edit form
The edit form of a node SHALL show the numeric `id` of the node in the "Details" block, as a read-only value with the label "ID". A click on the value SHALL copy the `id` to the clipboard and show a short confirmation; the value SHALL be operable by keyboard (focusable, `Enter` and `Space` copy it). The create form SHALL NOT show an `id`, because the node does not exist yet. The `id` SHALL NOT be sent back on saving and SHALL NOT be editable.

#### Scenario: Id on the edit form
- **WHEN** the user opens the edit form of the node with `id` 42
- **THEN** the "Details" block shows "ID" with the value 42

#### Scenario: Copying the id
- **WHEN** the user clicks the id value
- **THEN** `42` is placed in the clipboard and a confirmation is shown

#### Scenario: Create form
- **WHEN** the user opens the create form
- **THEN** the "Details" block has no "ID"

### Requirement: Pasting lists from Word into the formatted text editor
When the user pastes formatted text copied from Microsoft Word (a `.docx` document) into the `richtext` editor, the list paragraphs of the clipboard SHALL become real lists: bulleted items a bulleted list, numbered items a numbered list, with the nesting of the document kept (several levels). The bullet or number that Word draws as text (`·`, `•`, `o`, `§`, `1.`, `a)`) and the spaces after it SHALL NOT appear in the pasted text. Consecutive items of the same kind and level SHALL form one list; an ordinary paragraph between items SHALL end the list. The inline formatting of an item (bold, italic, underline, links) SHALL be kept as the editor allows. The same SHALL apply to paragraphs whose text itself starts with a bullet character (`•`, `·`, `▪`, `◦`, `●`) followed by spaces (this is what a Word list looks like when it arrives without list markup, for example as plain text); consecutive such paragraphs SHALL become one bulleted list without the bullet and the spaces. Likewise, a run of at least two consecutive paragraphs (or plain-text lines) that start with a number and `.` or `)` followed by whitespace, where each number is one more than the previous (`1.`, `2.`, `3.`), SHALL become one numbered list that starts at the first number, without the typed numbers and spaces. A single numbered paragraph, or numbers that do not follow each other, SHALL stay as text. Text that is not a Word list, and lists that already arrive as `ul` / `ol` (from a browser, Google Docs), SHALL be pasted as before.

#### Scenario: Bulleted list
- **WHEN** the user pastes three bulleted paragraphs copied from Word
- **THEN** the editor shows one bulleted list with three items, without bullet characters or leading spaces in the text

#### Scenario: Numbered list
- **WHEN** the user pastes a numbered list "1. One", "2. Two" copied from Word
- **THEN** the editor shows one numbered list with the items "One" and "Two", without the typed numbers in the text

#### Scenario: Bullets that arrive as text
- **WHEN** the user pastes the paragraphs `•    керівник — Юлія Павелко;`, `•    викладач — Юлія Шустова;`, `•    концертмейстер — Наталія Омельницька.` (as plain text or as ordinary paragraphs)
- **THEN** the editor shows one bulleted list with the items `керівник — Юлія Павелко;`, `викладач — Юлія Шустова;` and `концертмейстер — Наталія Омельницька.`, without the bullet and the spaces, and the paragraphs before and after the list stay ordinary paragraphs

#### Scenario: Typed numbers that arrive as text
- **WHEN** the user pastes the paragraphs `1.    керівник — Юлія Павелко;`, `2.    викладач — Юлія Шустова;`, `3.    концертмейстер — Наталія Омельницька.` between ordinary paragraphs
- **THEN** the editor shows one numbered list with the three items without the numbers and spaces, and the surrounding paragraphs stay ordinary

#### Scenario: A lone number stays text
- **WHEN** the user pastes one paragraph `2026. Рік театру` or the paragraphs `1. Intro` and `5. Outro`
- **THEN** they stay ordinary paragraphs with their text unchanged

#### Scenario: Nested list
- **WHEN** the user pastes a list from Word whose second item has two sub-items at the next level
- **THEN** the sub-items are a nested list inside the second item

#### Scenario: Paragraph between lists
- **WHEN** the pasted text is a list, an ordinary paragraph, and another list
- **THEN** the editor shows two separate lists with the paragraph between them

#### Scenario: List from a browser
- **WHEN** the user pastes a `ul` with two `li` copied from a web page
- **THEN** the editor shows the same list as before the change
