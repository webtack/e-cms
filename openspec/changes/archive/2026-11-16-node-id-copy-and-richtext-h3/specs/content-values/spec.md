## ADDED Requirements

### Requirement: No paragraph inside a list item of rich text
When cleaning a `richtext` value the system SHALL store the text of a list item directly in `li`: a `p` that is the only paragraph of an `li` and has no attributes SHALL be unwrapped, so `<li><p>Item</p></li>` is saved as `<li>Item</li>`. A `p` SHALL stay inside `li` when the item holds more than one paragraph, or when the paragraph has attributes (for example an alignment `style`). A nested list that follows the unwrapped paragraph SHALL stay inside the same `li`. Only the wrapping `p` tag is removed; its content (inline formatting, links, `span`, `br`, and nested lists) SHALL stay unchanged. The rule SHALL apply to bulleted and numbered lists, regardless of the widget, to every value cleaned after the change, and SHALL NOT alter the visible text of the value. Values saved earlier SHALL NOT be migrated; they are cleaned this way only when they are saved again.

#### Scenario: Simple item
- **WHEN** `<ul><li><p>One</p></li><li><p>Two</p></li></ul>` is written to a `richtext` field
- **THEN** `<ul><li>One</li><li>Two</li></ul>` is saved

#### Scenario: Item with a nested list
- **WHEN** `<ul><li><p>One</p><ul><li><p>Inner</p></li></ul></li></ul>` is written to a `richtext` field
- **THEN** `<ul><li>One<ul><li>Inner</li></ul></li></ul>` is saved

#### Scenario: Inline formatting in an item
- **WHEN** `<ul><li><p>A <strong>bold</strong>, <em>italic</em>, <a href="https://example.com">link</a>, <span style="color: #dc2626">red</span><br>text</p></li></ul>` is written to a `richtext` field
- **THEN** all inline tags and their attributes stay, only the wrapping `p` is gone: `<ul><li>A <strong>bold</strong>, <em>italic</em>, <a …>link</a>, <span style="color: #dc2626">red</span><br>text</li></ul>`

#### Scenario: Several paragraphs in an item
- **WHEN** `<ul><li><p>One</p><p>Two</p></li></ul>` is written to a `richtext` field
- **THEN** both paragraphs stay inside the `li`

#### Scenario: Aligned paragraph in an item
- **WHEN** `<ol><li><p style="text-align: center">One</p></li></ol>` is written to a `richtext` field
- **THEN** the `p` with its alignment stays inside the `li`

#### Scenario: Visible text
- **WHEN** a list is cleaned with and without the paragraphs inside its items
- **THEN** the visible text used for search and for the length limits is the same

#### Scenario: Reopening a list
- **WHEN** the user saves a bulleted list in the editor and reopens the node form
- **THEN** the list shows the same items, and each item can be edited as before

#### Scenario: Earlier values
- **WHEN** a value saved earlier with `<li><p>One</p></li>` is not saved again
- **THEN** it is returned unchanged
