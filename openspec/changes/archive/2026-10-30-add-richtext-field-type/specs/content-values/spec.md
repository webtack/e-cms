## ADDED Requirements

### Requirement: Field type "rich text"
The system SHALL support the field type `richtext`, whose value is an HTML fragment (paragraphs, headings, emphasis, lists, links). The value SHALL be stored as a string together with the values of the long text, without a new values table, and SHALL be read as it was stored (after sanitization). Multiple values, translations and clearing (`null`, an empty string) SHALL work by the general rules. A value that contains no visible text (an empty paragraph `<p></p>`) SHALL be considered empty: the field is cleared, and a required field is rejected. The type SHALL support the filter `eq` and `contains` and participation in the text search, as the long text does.

#### Scenario: Saving and reading
- **WHEN** `<p>Привіт, <strong>світе</strong></p>` is written into the field `richtext`
- **THEN** on reading the same HTML is returned

#### Scenario: Empty document
- **WHEN** `<p></p>` is written into the field `richtext`
- **THEN** the value is considered empty: an optional field is cleared, and a required one is rejected with an error for this field

#### Scenario: Search
- **WHEN** records are searched by a text that is contained in the value of the field `richtext`
- **THEN** the record is found

### Requirement: Sanitization of HTML of formatted text
Before saving, the system SHALL clean the value of `richtext` by an allow-list: only the tags of headings, paragraphs, line breaks, emphasis (bold, italic, underline, strikethrough), quotes, bulleted and numbered lists and links are allowed. All other tags, attributes and event handlers SHALL be removed; for links only the schemes `http`, `https`, `mailto`, `tel` (and relative addresses) SHALL be allowed, and links SHALL have safe `rel` values. The cleaning SHALL take place on the server regardless of the widget, and on reading the already cleaned value SHALL be returned. The text content that remains after removing the forbidden tags SHALL be kept.

#### Scenario: Script
- **WHEN** `<p>Hi</p><script>alert(1)</script>` is written into the field `richtext`
- **THEN** `<p>Hi</p>` is saved without the tag `script` and its content

#### Scenario: Event handler
- **WHEN** `<p onclick="x()">Hi</p>` is written into the field `richtext`
- **THEN** `<p>Hi</p>` is saved without the attribute `onclick`

#### Scenario: Unsafe link scheme
- **WHEN** `<a href="javascript:alert(1)">x</a>` is written into the field `richtext`
- **THEN** the saved HTML does not contain a link with the scheme `javascript:`

#### Scenario: Safe link
- **WHEN** `<a href="https://example.com">x</a>` is written into the field `richtext`
- **THEN** the link is saved with the same address

#### Scenario: Image
- **WHEN** HTML with an `img` tag is written into the field `richtext`
- **THEN** the tag is removed from the saved value

### Requirement: Length of formatted text by the visible text
The boundary values of length (`min`, `max` from the settings of the field), if they are set, SHALL apply to the number of characters of the visible text of the value of `richtext`, not to the length of the HTML markup.

#### Scenario: Markup is not counted
- **WHEN** the field `richtext` has `max` = 5, and `<p><strong>Hello</strong></p>` is written
- **THEN** the value is accepted, because the visible text has 5 characters

#### Scenario: Exceeding
- **WHEN** the field `richtext` has `max` = 5, and `<p>Hello!</p>` is written
- **THEN** the record is rejected with an error for this field

### Requirement: The type "rich text" does not change the general mechanism
Adding the type `richtext` SHALL NOT change the general mechanism of writing, reading and selecting values, except that the type can itself define which value is empty. For the other types an empty value remains `null` or an empty string.

#### Scenario: Other types
- **WHEN** an empty string is written into a field of the type `text`
- **THEN** the value is cleared, as before

#### Scenario: List of formatted text
- **WHEN** the list `["<p>a</p>", "<p></p>"]` is written into the multiple field `richtext`
- **THEN** only the first item is saved
