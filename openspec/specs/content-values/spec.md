# content-values Specification

## Purpose
Defines the typed storage of the field values of content nodes, translations through the value language, the registry of value types, and the single mechanism for reading, writing, and validating values according to the configuration of the field.

## Requirements

### Requirement: Initial value types
The system SHALL support the field types: short text, long text, integer, decimal number, boolean, date, date and time; the values of each type SHALL be stored in a column of the corresponding DB type and read back in the corresponding type.

#### Scenario: Storing and reading types
- **WHEN** the integer `42`, the decimal `12.50`, the boolean `true`, the date `2026-10-29`, and the date-time `2026-10-29 14:30:00` are written to a node
- **THEN** on reading the values of the same types and with the same values are returned (integer, decimal without loss of precision, boolean, date, date-time)

### Requirement: Shared contract of value tables
Each value table SHALL contain the owner of the value (the node: `content_node_id` and `content_node_uuid`; or the type without a node), the owner flag (`entity`: `node` or `type`), the relation to the field by the pair of the type's `key` (`content_type`) and the field's `key` (`field_key`), the language (`lang`), the weight of the value (`weight`), the typed value, and an optional JSON for additional settings; the combination (owner, type, field key, language, weight) SHALL be unique for both owners. For a node owner the type in the value SHALL match the type of its node, and `content_node_uuid` — the `uuid` of this node; for a type owner the node and its `uuid` SHALL be empty.

#### Scenario: Uniqueness of the weight
- **WHEN** for one node, field, and language a value with the same weight is written twice
- **THEN** the second save is rejected at the DB level

#### Scenario: Value of a field of another type
- **WHEN** a value of a field of type `product` is written to a node of type `article` (directly in the DB)
- **THEN** saving is rejected at the DB level, because the type in the value does not match the type of the node

#### Scenario: Value of a non-existent field
- **WHEN** a value is written with a pair (`content_type`, `field_key`) that does not exist among the fields
- **THEN** saving is rejected at the DB level

#### Scenario: Uuid of the node in a value
- **WHEN** a node value is written
- **THEN** `content_node_uuid` matches the `uuid` of the node and is not changed separately from it

#### Scenario: Owner flag
- **WHEN** a node value or a type value is written
- **THEN** `entity` equals `node` or `type` respectively, and a contradictory combination with the presence of a node is rejected

### Requirement: Translations as separate values
The system SHALL store the translation of a value as a separate value row with the corresponding `lang`; the default language SHALL be `default`; translations SHALL NOT be stored in JSON columns. Values in different languages SHALL be independent: writing one language does not change the others.

#### Scenario: Default value
- **WHEN** a value is written without specifying a language
- **THEN** it is stored with `lang = default`

#### Scenario: Translation
- **WHEN** the value "Hello" with `lang = en` is written to the translatable field `title`, and the value "Привіт" already exists with `lang = default`
- **THEN** two independent values exist; reading from `default` returns "Привіт", reading from `en` returns "Hello"

#### Scenario: Missing translation
- **WHEN** an admissible language is read for which no value has been written
- **THEN** `null` is returned (or an empty list for a multiple field), without substituting the value of `default`

### Requirement: Translatability of a field
The system SHALL allow writing a value with a language different from `default` only for fields with the translatability flag, when multilingual mode is enabled and the language is an active content language (see `content-languages`), and SHALL reject such a write in all other cases.

#### Scenario: Non-translatable field
- **WHEN** a value with `lang = en` is written to a non-translatable field
- **THEN** the write is rejected with an error for this field

#### Scenario: Unknown or inactive language
- **WHEN** a write is performed with a language that is not in the list of content languages or is inactive
- **THEN** the write is rejected with a validation error

#### Scenario: Multilingual disabled
- **WHEN** multilingual mode is disabled and a write is performed with `lang = en`
- **THEN** the write is rejected with a validation error

### Requirement: Single write mechanism
The system SHALL write the values of a node by a single mechanism by the field keys of the content type and the language, in a transaction, replacing the existing values of the specified fields in the specified language and not changing the values of other fields and other languages.

#### Scenario: Partial update
- **WHEN** only the field `title` of a node that also has the field `visits` is updated
- **THEN** the value of `title` is replaced, and the value of `visits` remains unchanged

#### Scenario: Error during writing
- **WHEN** the writing of the values of a node fails on the validation of any field
- **THEN** none of the values of this node is saved

#### Scenario: Unknown field
- **WHEN** the request contains a key that does not exist among the fields of the content type of the node
- **THEN** the write is rejected with a validation error

### Requirement: Validation according to the field configuration
The system SHALL validate the values by the field type and its settings (in particular requiredness and boundary values, such as length or minimum/maximum) and SHALL return errors under the field keys. Requiredness SHALL be checked for the language `default` when a node is created (in particular `title`); for other languages requiredness is not required.

#### Scenario: Required field
- **WHEN** a node is created without a value of a required field (in particular `title`) in the language `default`
- **THEN** the node is rejected with an error for this field

#### Scenario: Incorrect type
- **WHEN** the string `abc` is passed to an "integer" field
- **THEN** the node is rejected with an error for this field

#### Scenario: Exceeding a boundary value
- **WHEN** a value goes beyond the bounds set in the settings of the field
- **THEN** the node is rejected with an error for this field

#### Scenario: Excessive decimal places
- **WHEN** a value with more than 6 decimal places (`1.1234567`) is passed to a "decimal number" field
- **THEN** the node is rejected with an error for this field, and the value is not rounded

### Requirement: Multiple values
For a field with the multiplicity flag the system SHALL store an ordered list of values with the weight `weight` (within the language) and return it in the same order; for a single-value field — at most one value per language.

#### Scenario: Order of multiple values
- **WHEN** `["b", "a", "c"]` is written to a multiple field
- **THEN** on reading `["b", "a", "c"]` is returned

#### Scenario: Array in a single-value field
- **WHEN** an array of several values is passed to a single-value field
- **THEN** the node is rejected with an error for this field

#### Scenario: Clearing a value
- **WHEN** `null` or an empty list is passed to the field
- **THEN** all its values in the specified language are deleted, and on reading `null` (or an empty list for a multiple field) is returned

### Requirement: Reading node values
The system SHALL return the values of a node by the field keys for the specified language (by default `default`; other languages are admissible when multilingual mode is enabled and the language is in the list of content languages, including inactive ones) and SHALL load the values of a set of nodes without a query per node (the number of queries does not depend on the number of nodes). In the same way the system SHALL return the values of the type itself by the keys of its fields for the specified language; the values of the type are not mixed with the values of nodes.

#### Scenario: Reading a set
- **WHEN** the values of 50 nodes are read
- **THEN** the number of DB queries does not exceed a constant bound that depends only on the number of value types used

#### Scenario: Reading type values
- **WHEN** the values of the type `news` are read by the keys `section_title` and `meta_image`
- **THEN** the values of these fields for the specified language are returned, and the absent value is `null`

#### Scenario: Unknown key of a type value
- **WHEN** a type value is read by a key that the type does not have, or that does not belong to the type owner
- **THEN** the read is rejected with a validation error

### Requirement: Extensibility by field types
The system SHALL allow adding a new field type by registering its description (value table, validation, type casting, supported filter and sort operations) without changes to the general mechanism of writing, reading, and querying.

#### Scenario: New type
- **WHEN** a new field type with its own value table is added to the registry
- **THEN** fields of this type can be created, written, read, filtered, and sorted without changes to the code of the general mechanism

### Requirement: Field types "image" and "file"
The system SHALL support the field types `image` and `file`, whose value references a file from the storage. The value SHALL be stored as a row `{uuid}.{extension}` together with the values of the string type, without a new value table. The type SHALL define the upload profile, which is the upper bound of the rules: `image` — the profile `image`, `file` — the profile `document`. The field SHALL have optional settings: `mimes` — the allowed file types (a subset of the types of the profile) and `max_size` — the maximum size in kilobytes (not more than the limit of the profile); without settings the rules of the whole profile apply. The settings SHALL only narrow the profile rules and never widen them. For a multiple field the system SHALL store an ordered list of such rows by the general rules of multiple values, and the translations — by the general rules of translations.

#### Scenario: Storing and reading an image
- **WHEN** a file of the profile `image` is written to an `image` field
- **THEN** on reading the same row `{uuid}.{extension}` is returned

#### Scenario: Storing and reading a file
- **WHEN** a file of the profile `document` is written to a `file` field
- **THEN** on reading the same row `{uuid}.{extension}` is returned

#### Scenario: Link without additional queries
- **WHEN** a set of nodes with `image` and `file` fields is read
- **THEN** the links to the files are built from the read values, without additional queries to the file storage

#### Scenario: Clearing
- **WHEN** `null` is passed to an `image` or `file` field
- **THEN** the value is deleted, and the file remains in the storage

### Requirement: Validation of the value of "image" and "file" fields
The system SHALL accept the value of an `image` or `file` field only in the format `{uuid}.{extension}`, where `uuid` is the identifier of an existing file, its profile matches the profile of the field's type, the file type is among the allowed types of the field, the size does not exceed the maximum size of the field, and the extension matches the extension of the file in the storage. Other strings (in particular with path separators) SHALL be rejected with an error for this field.

#### Scenario: File of the required profile
- **WHEN** the value is a string `{uuid}.{extension}` of an existing file of the profile of the field's type
- **THEN** the node is accepted

#### Scenario: Document in an image field
- **WHEN** a file of the profile `document` is written to an `image` field
- **THEN** the node is rejected with an error for this field

#### Scenario: Image in a file field
- **WHEN** a file of the profile `image` is written to a `file` field
- **THEN** the node is rejected with an error for this field

#### Scenario: Non-existent file
- **WHEN** the value has the correct format, but there is no such file
- **THEN** the node is rejected with an error for this field

#### Scenario: Wrong extension
- **WHEN** the `uuid` belongs to an existing file, and the extension in the value is different
- **THEN** the node is rejected with an error for this field

#### Scenario: Arbitrary string
- **WHEN** `../secret.png` or a string of arbitrary form is passed to an `image` or `file` field
- **THEN** the node is rejected with an error for this field

#### Scenario: Type outside the field settings
- **WHEN** an `image` field allows only `image/png`, and a file `image/jpeg` of the profile `image` is written to it
- **THEN** the node is rejected with an error for this field

#### Scenario: Field size exceeded
- **WHEN** a field has `max_size` 1024 KB, and a file of 2 MB is written
- **THEN** the node is rejected with an error for this field

#### Scenario: One file in different fields
- **WHEN** the same file `image/jpeg` is written to a field that allows JPEG, and to a field that allows only PNG
- **THEN** the write to the first field is accepted, and to the second is rejected

#### Scenario: A change of settings does not affect what is saved
- **WHEN** after narrowing the allowed types a field already has saved file values that no longer match the settings
- **THEN** the saved values are read without changes, and the new rules apply on the next write

### Requirement: Settings do not widen the profile
The system SHALL reject the settings of an `image` or `file` field that contain a type that is absent from the profile of the type, or a size above the limit of the profile. The settings SHALL not allow executable files and SVG, even if the profile was mistakenly widened.

#### Scenario: Type outside the profile
- **WHEN** `application/pdf` is specified in the settings of an `image` field
- **THEN** the settings are rejected with an error

#### Scenario: Size above the profile
- **WHEN** a `max_size` greater than the limit of the profile `image` is specified for an `image` field
- **THEN** the settings are rejected with an error

#### Scenario: Empty settings
- **WHEN** a field is created without settings
- **THEN** the types and the size of the whole profile of the type apply

### Requirement: "Image" and "file" types do not change the general mechanism
The addition of the types `image` and `file` SHALL NOT change the general mechanism of writing, reading, and querying values: fields of these types are created, written, read, and filtered by the same means as other types, and the filter by value works as for a short text. The value type of a field that has saved values SHALL NOT change between `string`, `image`, and `file`.

#### Scenario: Filter by a file
- **WHEN** nodes are filtered by the value of an `image` or `file` field equal to a certain `{uuid}.{extension}`
- **THEN** the nodes that contain this file in this field are returned

#### Scenario: Change of the type of a field with values
- **WHEN** the type of a `string` or `image` field with saved values is changed to `file`
- **THEN** the change is rejected, and the values do not change

### Requirement: Limit of the elements of a multiple field
Writing the values of a multiple field that has a maximum number of elements greater than 0 SHALL reject a list in which (after discarding empty elements) there are more elements than allowed; the error SHALL be addressed to the field itself. The limit `0` SHALL mean no limit. The limit SHALL be checked regardless of the form (by the common writing mechanism); a field without a limit and single-value fields do not change behavior.

#### Scenario: Within the limit
- **WHEN** a field with the limit 3 receives three values
- **THEN** the values are saved

#### Scenario: Above the limit
- **WHEN** a field with the limit 3 receives four values
- **THEN** the write is rejected with a field error, and nothing is changed

#### Scenario: No limit
- **WHEN** a field with the limit 0 receives twenty values
- **THEN** all values are saved

#### Scenario: Empty elements are not counted
- **WHEN** a field with the limit 2 receives two values and one empty element
- **THEN** the values are saved

### Requirement: Field type "rich text"
The system SHALL support the field type `richtext`, whose value is an HTML fragment (paragraphs, headings, emphasis, lists, links). The value SHALL be stored as a row together with the values of long text, without a new value table, and SHALL be read as it was saved (after sanitization). Multiple values, translations, and clearing (`null`, an empty string) SHALL work by the general rules. A value that contains no visible text (an empty paragraph `<p></p>`) SHALL be considered empty: an optional field is cleared, and a required field is rejected. The type SHALL support the filters `eq` and `contains` and participation in text search, as long text does.

#### Scenario: Storing and reading
- **WHEN** `<p>Hi, <strong>world</strong></p>` is written to a `richtext` field
- **THEN** on reading the same HTML is returned

#### Scenario: Empty document
- **WHEN** `<p></p>` is written to a `richtext` field
- **THEN** the value is considered empty: an optional field is cleared, and a required field is rejected with an error for this field

#### Scenario: Search
- **WHEN** nodes are searched by a text contained in the value of a `richtext` field
- **THEN** the node is found

### Requirement: HTML sanitization of rich text
Before saving, the system SHALL clean the value of `richtext` against an allow-list: only the tags of headings, paragraphs, line breaks, emphasis (bold and italic as `strong`/`b` and `em`/`i`, underline, strikethrough), quotes, bulleted and numbered lists, tables (`table`, `thead`, `tbody`, `tfoot`, `tr`, `th`, `td`), links, `span`, images from the storage (with a caption), and frames from the allowed hosts are allowed. All other tags, attributes, and event handlers SHALL be removed; in links only the schemes `http`, `https`, `mailto`, `tel` (and relative addresses) SHALL be allowed, and links SHALL have safe `rel` values. The attribute `style` SHALL be allowed only on paragraphs, headings, quotes, list items, `span`, and `figure` (on `figure` only `text-align`, `width`, `max-width`, and `margin-left`/`margin-right`, see below), and SHALL keep only the properties `text-align` (`left`, `center`, `right`, `justify`), `color`, and `background-color` with a value in the format `#rgb`, `#rrggbb`, or `rgb(r, g, b)`; any other property or value (in particular `url(...)`, `expression(...)`, `position`) SHALL be removed. In tables only the attributes `colspan` and `rowspan` SHALL remain, only on `th` and `td`, and only as a whole number from 1 to 100 (any other value removes the attribute and the cell remains); every other attribute of a table tag (`style`, `width`, `align`, `class` and so on) SHALL be removed. The tags `caption`, `colgroup`, and `col` SHALL be removed, and the text of a `caption` SHALL remain. The tag `img` SHALL remain only with the attributes `src`, `alt`, and `width`, and only when `src` is a relative address of a storage file of the form `/storage/{uuid}.{extension}` (without protocol and domain); an image with any other address (external, protocol-relative `//host/...`, `data:`, with another path) SHALL be removed entirely. The attribute `width` SHALL remain only as an integer from 1 to 4000 (otherwise the attribute is removed, and the image remains); `height` SHALL be removed. The caption SHALL be stored as a `figure` containing `img` and `figcaption` with the text; `figcaption` SHALL have no attributes, `figure` SHALL have only a `style` with the width, the alignment, and `max-width: 100%`: `width` — only `{N}px` (an integer 1–4000), `fit-content`, or `min-content` (saved earlier), `margin-left`/`margin-right` — only `auto` or `0`, `max-width` — only `100%`, `text-align` — `left|center|right`. An `iframe` SHALL remain only with an `https` address to a host (if needed with a path prefix) from the list of allowed hosts in the configuration, which by default includes video services, Google Drive and Google Docs, and social networks, and only with the attributes `src`, `width`, `height`, `title`, `allowfullscreen`, `allow`; the system SHALL forcibly set `sandbox` and `loading="lazy"` on it, otherwise the whole tag SHALL be removed. The cleaning SHALL happen on the server regardless of the widget, and on reading the already cleaned value SHALL be returned. The text content that remains after removing forbidden tags SHALL be saved.

#### Scenario: Script
- **WHEN** `<p>Hi</p><script>alert(1)</script>` is written to a `richtext` field
- **THEN** `<p>Hi</p>` is saved, without the `script` tag and its content

#### Scenario: Event handler
- **WHEN** `<p onclick="x()">Hi</p>` is written to a `richtext` field
- **THEN** `<p>Hi</p>` is saved, without the attribute `onclick`

#### Scenario: Dangerous link scheme
- **WHEN** `<a href="javascript:alert(1)">x</a>` is written to a `richtext` field
- **THEN** the saved HTML does not contain a link with the scheme `javascript:`

#### Scenario: Safe link
- **WHEN** `<a href="https://example.com">x</a>` is written to a `richtext` field
- **THEN** the link is saved with the same address

#### Scenario: Image
- **WHEN** `<img src="https://evil.example/a.png">` is written to a `richtext` field
- **THEN** the tag is removed from the saved value

#### Scenario: Image from the storage
- **WHEN** `<img src="/storage/{uuid}.png" alt="Logo" onerror="x()">` is written to a `richtext` field
- **THEN** `img` is saved with the same address and `alt`, without `onerror`

#### Scenario: Image width
- **WHEN** `<img src="/storage/{uuid}.png" width="200" height="100">` is written to a `richtext` field
- **THEN** `img` is saved with `width="200"` without `height`

#### Scenario: Invalid width
- **WHEN** `<img src="/storage/{uuid}.png" width="5000">` or `width="10px"` is written to a `richtext` field
- **THEN** `img` is saved without `width`

#### Scenario: Image caption
- **WHEN** `<figure class="x" onclick="y()"><img src="/storage/{uuid}.png"><figcaption>Office</figcaption></figure>` is written to a `richtext` field
- **THEN** `figure` is saved with `img` and `figcaption` "Office", without `class` and `onclick`

#### Scenario: Aligned image
- **WHEN** `<figure style="text-align: center"><img src="/storage/{uuid}.png"></figure>` is written to a `richtext` field
- **THEN** `figure` is saved with `style="text-align: center"` and `img`

#### Scenario: Aligned image with a caption
- **WHEN** `<figure style="text-align: right"><img src="/storage/{uuid}.png"><figcaption>Office</figcaption></figure>` is written to a `richtext` field
- **THEN** `figure` is saved with `style="text-align: right"`, `img`, and `figcaption` "Office"

#### Scenario: Alignment through margins
- **WHEN** `<figure style="width: 500px; max-width: 100%; margin-left: auto; margin-right: auto; text-align: center; position: absolute"><img src="/storage/{uuid}.png"><figcaption>Office</figcaption></figure>` is written to a `richtext` field
- **THEN** `figure` is saved only with `width: 500px`, `max-width: 100%`, `margin-left: auto`, `margin-right: auto`, `text-align: center`, without `position`

#### Scenario: Admissible widths of figure
- **WHEN** `width: 200px`, `width: fit-content`, or (saved earlier) `width: min-content` is written on `figure`
- **THEN** each value is saved

#### Scenario: Inadmissible values on figure
- **WHEN** `width: 9999px`, `width: 50%`, or `margin-left: 50px` is written on `figure`
- **THEN** these properties are removed

#### Scenario: Other properties on figure
- **WHEN** `<figure style="position: absolute; text-align: center"><img src="/storage/{uuid}.png"></figure>` is written to a `richtext` field
- **THEN** `figure` is saved only with `style="text-align: center"`

#### Scenario: Image address is not relative
- **WHEN** `<img src="//host/storage/{uuid}.png">`, `<img src="http://localhost/storage/{uuid}.png">`, or `<img src="data:image/png;base64,AAAA">` is written to a `richtext` field
- **THEN** the saved value has no `img` tag

#### Scenario: Alignment is kept
- **WHEN** `<p style="text-align: center">Hi</p>` is written to a `richtext` field
- **THEN** the paragraph is saved with `style="text-align: center"`

#### Scenario: Text and background color are kept
- **WHEN** `<span style="color: #ff0000; background-color: #ffff00">x</span>` is written to a `richtext` field
- **THEN** `span` is saved with both properties

#### Scenario: Dangerous style is cleaned
- **WHEN** `<span style="color: red; position: fixed; background-color: url(javascript:x)">x</span>` is written to a `richtext` field
- **THEN** the saved `style` has no `position` and no `url(`, and the text `x` is saved

#### Scenario: Style on a non-allowed tag is removed
- **WHEN** `<strong style="color: #f00">x</strong>` is written to a `richtext` field
- **THEN** `<strong>x</strong>` is saved without `style`

#### Scenario: Frame from an allowed host is kept
- **WHEN** `<iframe src="https://www.youtube-nocookie.com/embed/abc" onload="x()"></iframe>` is written to a `richtext` field
- **THEN** `iframe` is saved with the same address, `sandbox`, and `loading="lazy"`, without `onload`

#### Scenario: The allow attribute is cleaned
- **WHEN** `<iframe src="https://www.youtube-nocookie.com/embed/abc" allow="autoplay; camera; picture-in-picture"></iframe>` is written to a `richtext` field
- **THEN** the saved `allow` equals `autoplay; picture-in-picture`, and `camera` is removed

#### Scenario: Google Drive or social network frame is kept
- **WHEN** `<iframe src="https://drive.google.com/file/d/abc/preview"></iframe>` or `<iframe src="https://www.facebook.com/plugins/post.php?href=x"></iframe>` is written to a `richtext` field
- **THEN** both `iframe` are saved with `sandbox` and `loading="lazy"`

#### Scenario: Google Forms frame is kept
- **WHEN** `<iframe src="https://docs.google.com/forms/d/e/abc/viewform?embedded=true"></iframe>` is written to a `richtext` field
- **THEN** `iframe` is saved with the same address, `sandbox`, and `loading="lazy"`

#### Scenario: Frame outside the path prefix is removed
- **WHEN** `<iframe src="https://www.facebook.com/some.page"></iframe>` is written to a `richtext` field
- **THEN** the saved value has no `iframe` tag

#### Scenario: Frame from a foreign host or not https is removed
- **WHEN** `<iframe src="https://evil.example/x"></iframe>` or `<iframe src="http://www.youtube.com/embed/a"></iframe>` is written to a `richtext` field
- **THEN** the saved value has no `iframe` tag

#### Scenario: Pasted bold and italic are kept
- **WHEN** `<p><b>Bold</b> and <i>italic</i></p>` is written to a `richtext` field
- **THEN** the saved value keeps the `b` and `i` tags with their text

#### Scenario: Attributes of bold and italic are removed
- **WHEN** `<b style="color:red" onclick="x()">A</b><i class="y">B</i>` is written to a `richtext` field
- **THEN** the saved value is `<b>A</b><i>B</i>`

#### Scenario: A table keeps its structure
- **WHEN** `<table><thead><tr><th>Time</th><th>Event</th></tr></thead><tbody><tr><td>10:00</td><td>Opening</td></tr></tbody></table>` is written to a `richtext` field
- **THEN** the saved value keeps the `table`, `thead`, `tbody`, `tr`, `th`, and `td` tags with their text in the same cells

#### Scenario: Cell spans are kept as whole numbers
- **WHEN** `<table><tr><td colspan="3" rowspan="2">A</td></tr></table>` is written to a `richtext` field
- **THEN** the saved value keeps `colspan="3"` and `rowspan="2"` on the cell

#### Scenario: A wrong span is removed and the cell stays
- **WHEN** `<table><tr><td colspan="0" rowspan="x">A</td><td colspan="500">B</td></tr></table>` is written to a `richtext` field
- **THEN** both cells stay with their text and none of them has `colspan` or `rowspan`

#### Scenario: Pasted widths and styles of a table are removed
- **WHEN** `<table style="width:400pt" class="x" onclick="y()"><tr><td style="width:175.2pt" width="234" valign="top"><p style="text-align:center">A</p></td></tr></table>` is written to a `richtext` field
- **THEN** the saved value has `table`, `tr`, and `td` without attributes, and the paragraph inside keeps its `text-align`

#### Scenario: Caption and column groups are removed
- **WHEN** `<table><caption>Results</caption><colgroup><col style="width:50%"></colgroup><tr><td>A</td></tr></table>` is written to a `richtext` field
- **THEN** the saved value has no `caption`, `colgroup`, or `col` tag, the text `Results` remains, and the cell `A` stays in its table

### Requirement: Length of rich text by its visible text
The boundary values of length (`min`, `max` from the settings of the field), if they are set, SHALL apply to the number of characters of the visible text of the `richtext` value, not to the length of the HTML markup.

#### Scenario: Markup is not counted
- **WHEN** a `richtext` field has `max` = 5, and `<p><strong>Hello</strong></p>` is written
- **THEN** the value is accepted, because the visible text has 5 characters

#### Scenario: Exceeding
- **WHEN** a `richtext` field has `max` = 5, and `<p>Hello!</p>` is written
- **THEN** the write is rejected with an error for this field

### Requirement: The "rich text" type does not change the general mechanism
The addition of the `richtext` type SHALL NOT change the general mechanism of writing, reading, and querying values, except that the type may itself define which value is empty. For the other types, `null` or an empty string remains empty.

#### Scenario: Other types
- **WHEN** an empty string is written to a field of type `text`
- **THEN** the value is cleared, as before

#### Scenario: List of rich text
- **WHEN** the list `["<p>a</p>", "<p></p>"]` is written to a multiple `richtext` field
- **THEN** only the first element is saved

### Requirement: Images in a rich text value
Each image in a `richtext` value SHALL reference an existing file of the profile `image`; a value that references a non-existent file or a file of another profile SHALL be rejected with an error for this field, and nothing is written. A value that contains an image SHALL be considered non-empty even without visible text. The length by visible text does not count the image itself, but counts the text of the caption (`figcaption`), as does the text search.

#### Scenario: Existing file
- **WHEN** `<p><img src="/storage/{uuid}.png"></p>` is written to a `richtext` field, where the file `{uuid}` of the profile `image` exists
- **THEN** the value is saved

#### Scenario: Non-existent file
- **WHEN** `<img src="/storage/{uuid}.png">` is written to a `richtext` field, and the file `{uuid}` does not exist
- **THEN** the write is rejected with an error for this field

#### Scenario: File of another profile
- **WHEN** `<img>` with the address of a file of the profile `document` is written to a `richtext` field
- **THEN** the write is rejected with an error for this field

#### Scenario: Caption in search and length
- **WHEN** the image caption is "Our office", and the node is searched for the word "office"
- **THEN** the node is found, and the length by visible text includes the characters of the caption

#### Scenario: Only an image
- **WHEN** `<p><img src="/storage/{uuid}.png"></p>` without text is written to a required `richtext` field
- **THEN** the value is considered non-empty and is saved

### Requirement: Writing and reading additional data of a value
The system SHALL support field types whose value consists of a scalar value and additional data. The scalar value SHALL be stored in the value column of the type's table, and the additional data — in the JSON column `options` of the same row. Reading SHALL assemble from them the same composed value that the write accepts. For types without additional data the `options` column SHALL remain empty, and writing and reading do not change. The additional data SHALL be replaced and deleted together with the value to which they belong, by the general rules of writing, multiplicity, and translations.

#### Scenario: Storing and reading a composed value
- **WHEN** a composed value is written to a field of a type with additional data
- **THEN** on reading the same composed value is returned, the scalar part lies in the value column, and the rest in `options`

#### Scenario: Type without additional data
- **WHEN** a value of a field of type `string` is written
- **THEN** the `options` column is empty, and reading returns the value as before

#### Scenario: Clearing
- **WHEN** `null` is passed to a field with a composed value
- **THEN** the value row together with `options` is deleted

#### Scenario: Multiple field with composed values
- **WHEN** a list of composed values is written to a multiple field
- **THEN** each element is stored in a separate row with its own `options`, the order is kept, and reading returns the list in the same order

### Requirement: Field type "YouTube video"
The system SHALL support the field type `youtube`. Its value SHALL consist of the full link to the video (a scalar value, in the table of string values) and additional data: the video identifier (`video_id`), a title, a description, and a screenshot. The identifier SHALL be determined from the link during writing, be stored next to the value, and SHALL NOT be accepted from the input separately. The link SHALL be stored as it was entered (after trimming spaces), together with all its parameters, in particular the timecode `t`. The accepted input forms: `watch?v=`, `youtu.be/`, `embed/`, `live/`, `shorts/`, including `m.youtube.com` and `youtube-nocookie.com`, and also the bare 11 characters of the identifier (they SHALL be converted to `https://www.youtube.com/watch?v={id}`), where `{id}` is the 11-character identifier of the video. The only conversion of the entered link — a link to Shorts (`youtube.com/shorts/{id}`) SHALL be converted to the regular `https://www.youtube.com/watch?v={id}` with other parameters kept. The length of the link is not greater than 512 characters. The title, the description, and the screenshot SHALL be optional and editable; the screenshot is a reference `{uuid}.{extension}` to a file of the profile `image`. Saving SHALL NOT contact YouTube: it writes only what was sent. For a multiple field the general rules of multiple values SHALL apply, and translations SHALL NOT be supported. Since one video can be written with different links, the filter by this type SHALL support only the search by inclusion (`contains`), by which the video is found by its identifier, and equality SHALL NOT be supported. A value without a link SHALL be considered empty.

#### Scenario: Storing and reading a video
- **WHEN** the link `https://www.youtube.com/watch?v=dQw4w9WgXcQ`, a title, a description, and a screenshot are written to a `youtube` field
- **THEN** on reading the link `https://www.youtube.com/watch?v=dQw4w9WgXcQ`, the title, the description, and the screenshot are returned

#### Scenario: Different link forms
- **WHEN** `https://youtu.be/dQw4w9WgXcQ?t=10` or `https://www.youtube.com/embed/dQw4w9WgXcQ` is written to the field
- **THEN** exactly the entered link is saved, in particular `https://youtu.be/dQw4w9WgXcQ?t=10` with the timecode, without changes

#### Scenario: Link to Shorts
- **WHEN** `https://www.youtube.com/shorts/dQw4w9WgXcQ` is written to the field
- **THEN** the regular link `https://www.youtube.com/watch?v=dQw4w9WgXcQ` is saved

#### Scenario: Identifier only
- **WHEN** `dQw4w9WgXcQ` is written to the field
- **THEN** `https://www.youtube.com/watch?v=dQw4w9WgXcQ` is saved

#### Scenario: Timecode is kept
- **WHEN** `https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s` is written to the field
- **THEN** on reading the link with `t=42s` is returned

#### Scenario: Too long link
- **WHEN** the link is longer than 512 characters
- **THEN** the write is rejected with an error next to the field

#### Scenario: Video identifier next to the value
- **WHEN** any accepted form of a video link is written to the field
- **THEN** on reading the video identifier is returned (`dQw4w9WgXcQ` for `https://youtu.be/dQw4w9WgXcQ?t=10`), determined from the link, even if a different one is passed in the request

#### Scenario: Only a title entered manually
- **WHEN** a link without a title, a description, and a screenshot is written to the field
- **THEN** the value is saved, the absent title, description, and screenshot are read as `null`, and the video identifier is determined

#### Scenario: Invalid link
- **WHEN** a text that is not a link to a YouTube video or a video identifier is written to the field (in particular a link to a channel or a playlist)
- **THEN** the write is rejected with an error next to the field, and nothing is saved

#### Scenario: Screenshot not from the image profile
- **WHEN** a link to a non-existent file or to a file of another profile is passed as the screenshot
- **THEN** the write is rejected with an error next to the field

#### Scenario: Saving without contacting YouTube
- **WHEN** a node is saved with a value of a `youtube` field
- **THEN** no request to YouTube is performed

#### Scenario: Required field
- **WHEN** a required `youtube` field is left without a link
- **THEN** the write is rejected with an error next to the field

#### Scenario: Search for a video
- **WHEN** nodes are filtered by a `youtube` field by inclusion of the video identifier
- **THEN** the nodes with this video are returned, in whatever form the link was entered

#### Scenario: Equality is not supported
- **WHEN** nodes are filtered by a `youtube` field by equality
- **THEN** the filter is rejected as an unsupported operator

#### Scenario: The type does not change the general mechanism
- **WHEN** the type `youtube` is added
- **THEN** only its class, the row in the type registry, and the shared mechanism of writing `options` are needed; there are no new tables

### Requirement: Screenshot file of a video in the file relations
The screenshot file of a value of the type `youtube` SHALL be tracked as a file used by the node: the relation with the node is created during the saving of the value and is removed when the screenshot is replaced or cleared or the value is deleted. A file to which such a relation exists SHALL be considered used and SHALL NOT be deleted by the media library or by the cleaning of free files. The file itself SHALL remain in the storage after the relation is removed, until the media library deletes it.

#### Scenario: Screenshot in use
- **WHEN** a node with a video with a screenshot is saved
- **THEN** in the media library the screenshot file has one usage by this node and this field

#### Scenario: Replacing the screenshot
- **WHEN** the screenshot of a video is replaced with another image and the node is saved
- **THEN** the new file is bound to the node, the relation with the old one is removed, and the old file remains in the storage

#### Scenario: Screenshot that was not saved
- **WHEN** a screenshot is uploaded, but the node with it is not saved
- **THEN** the file remains without a relation and is deleted by the cleaning of free files under the usual rule

### Requirement: Type values in the shared value tables
The system SHALL store the values of the type itself in the same value tables as the values of nodes. A value row SHALL have an owner: a node (a reference to the node and its `uuid`) or the type (without a node, and `content_type` equals the key of this type). The owner of the type SHALL have values only for the fields of this type which, by the requirement "Owners of field values", belong to the type. For the type the same languages, weights, validation, normalization, and translatability rules apply as for a node; a type value in a language that is not the base one is not substituted from the base one on reading.

#### Scenario: Writing a type value
- **WHEN** `section_title` "News" in the language `default` is written for the type `news`
- **THEN** the value is saved without a node, and `content_type` equals `news`

#### Scenario: Translation of a type value
- **WHEN** `section_title` "News" in the language `en` is written for the type `news`
- **THEN** there are two independent values (`default`, `en`), and replacing one does not change the other

#### Scenario: Reading type values
- **WHEN** the values of the type `news` are read for the language `en`, and `section_description` is not filled in English
- **THEN** `section_description` equals `null`, the base value is not substituted

#### Scenario: Owner does not match the value
- **WHEN** a row of a node owner without a node or of a type owner with a node is inserted directly in the DB
- **THEN** saving is rejected at the DB level

#### Scenario: Uniqueness of a type value
- **WHEN** for one type, field, language, and weight a type value is written twice
- **THEN** the second save is rejected at the DB level

#### Scenario: Type value does not get into the node selection
- **WHEN** the selection, filtering, search, or sorting of nodes of the type `news` is performed
- **THEN** the values of the type itself do not affect any of the results

#### Scenario: Occupancy of a field
- **WHEN** a field has only type values, and no node has values
- **THEN** the field is considered to have values (it cannot be deleted), but is not considered filled in the nodes

### Requirement: Field type "term"
The system SHALL support the field type "term", whose value is the `uuid` of a taxonomy term, and the vocabulary is set by the field setting `vocabulary` (the key of the vocabulary). The field belongs to the content type, and the values belong to nodes. The value SHALL be stored in the existing value table of string rows (`content_values_string`, with the shared contract: language, weight) without requiring a separate table, and be read as a string — the `uuid` of the term. The field SHALL support multiplicity and the limit of elements in the same way as other types. The write SHALL accept only the `uuid` of an existing term of exactly the vocabulary specified in the settings of the field; anything else is a validation error under the field key. The translatability of the field (`is_translatable`) works by the general rules: a translation of a value is a separate value row in another language, which also references a term.

#### Scenario: Storing and reading
- **WHEN** the `uuid` of an existing term of the vocabulary `categories` is written to the field `category` of a node
- **THEN** on reading this `uuid` is returned

#### Scenario: Multiple field
- **WHEN** a list of three `uuid`s of terms of the vocabulary `tags` is written to the multiple field `tags`
- **THEN** on reading a list of three values in the saved order is returned

#### Scenario: Term of another vocabulary
- **WHEN** the `uuid` of a term of the vocabulary `tags` is written to a field with the vocabulary `categories`
- **THEN** the write is rejected with a validation error under the field key

#### Scenario: Non-existent term
- **WHEN** a `uuid` that is not among the terms is written
- **THEN** the write is rejected with a validation error under the field key

#### Scenario: Not a uuid
- **WHEN** a value that is not a UUID (for example, the `slug` of a term or a number) is written to the field
- **THEN** the write is rejected with a validation error under the field key

#### Scenario: Deleting a used term
- **WHEN** a term referenced by a value of a node field is deleted
- **THEN** the deletion is rejected, and the value remains (see the requirements for deleting a term in `taxonomy`)

#### Scenario: Write against a simultaneous deletion
- **WHEN** a value referencing a term is written simultaneously with the deletion of this term
- **THEN** only one of the operations succeeds: either the term is deleted (and the write is rejected as a reference to a non-existent term), or the value is saved (and the deletion is rejected as a usage)

### Requirement: Settings of a "term" field
The system SHALL require for a field of type "term" the setting `vocabulary` with the key of an existing vocabulary. A field without it, or with the key of a non-existent vocabulary, SHALL not be saved. The vocabulary of the field SHALL be immutable if the field already has values.

#### Scenario: Field without a vocabulary
- **WHEN** a field of type "term" is created without the setting `vocabulary`
- **THEN** saving is rejected with a validation error

#### Scenario: Non-existent vocabulary
- **WHEN** the key of a vocabulary that does not exist is specified in the setting
- **THEN** saving is rejected with a validation error

#### Scenario: Change of the vocabulary of a field with values
- **WHEN** the `vocabulary` of a field that already has values is changed
- **THEN** the change is rejected with a validation error

#### Scenario: Change of the vocabulary of an empty field
- **WHEN** the `vocabulary` of a field without values is changed to the key of an existing vocabulary
- **THEN** the change is saved

### Requirement: Field type "link"
The system SHALL support the field type `url`. Its value SHALL consist of a link (a scalar value, in the table of string values) and an optional title (additional data). The link SHALL be stored as it was entered, after trimming spaces at the edges. The accepted forms: absolute links with the scheme `http` or `https` and a host, `mailto:` and `tel:` links with a non-empty address, and relative ones — a path from the root (`/…`, but not `//…`), `./…`, `../…`, a fragment (`#…`), and a query (`?…`). Links with spaces or control characters inside, and with other schemes (`javascript:`, `data:`, `ftp:`, etc.) SHALL be rejected. The length of the link is not greater than 512 characters, of the title — not greater than 255. The title SHALL be optional: an empty title or one consisting only of spaces is not stored and is read as `null`. A value without a link SHALL be considered empty, even if the title is filled in. The type SHALL have no field settings. Saving SHALL NOT contact the network and SHALL NOT check that the link opens. For a multiple field and translations the general rules SHALL apply. The filter by the type SHALL support equality (`eq`), a list (`in`), and inclusion (`contains`) by the link.

#### Scenario: Storing and reading an absolute link
- **WHEN** the link `https://example.com/page?a=1#top` and the title "Example" are written to a `url` field
- **THEN** on reading the same link and the title "Example" are returned

#### Scenario: Relative link
- **WHEN** `/about`, `./team`, `../docs/a.pdf`, `#contacts`, or `?page=2` is written to the field
- **THEN** the value is saved without changes

#### Scenario: Link without a title
- **WHEN** a link without a title or with a title of only spaces is written to the field
- **THEN** the value is saved, and the title is read as `null`

#### Scenario: Mail and phone
- **WHEN** `mailto:info@example.com` or `tel:+380441234567` is written to the field
- **THEN** the value is saved without changes

#### Scenario: Trimming spaces
- **WHEN** `  /about  ` is written to the field
- **THEN** `/about` is saved

#### Scenario: Forbidden scheme
- **WHEN** `javascript:alert(1)`, `data:text/html,x`, or `ftp://example.com/a` is written to the field
- **THEN** the write is rejected with an error next to the field, and nothing is saved

#### Scenario: Protocol-relative link
- **WHEN** `//example.com/a` is written to the field
- **THEN** the write is rejected with an error next to the field

#### Scenario: Spaces inside a link
- **WHEN** `/about us` is written to the field
- **THEN** the write is rejected with an error next to the field

#### Scenario: Data too long
- **WHEN** the link is longer than 512 characters or the title is longer than 255
- **THEN** the write is rejected with an error next to the field

#### Scenario: Required field
- **WHEN** a required `url` field is left without a link but with a filled-in title
- **THEN** the write is rejected with an error next to the field

#### Scenario: Settings are forbidden
- **WHEN** `settings` is passed to a field of the type `url`
- **THEN** the field write is rejected, as for other types without settings

#### Scenario: Multiple field
- **WHEN** a list of values of the `url` multiple field with different titles is written
- **THEN** each element is stored in a separate row with its own title, the order is kept

#### Scenario: Search by a link
- **WHEN** nodes are filtered by the `url` field by inclusion of `example.com`
- **THEN** the nodes whose link contains `example.com` are returned

#### Scenario: The type does not change the general mechanism
- **WHEN** the type `url` is added
- **THEN** only its class and the row in the type registry are needed; there are no new tables

### Requirement: Field type "reference to a content type"
The system SHALL support the field type `type_ref`. Its value is the `key` of an existing type, stored in the table of string values. The setting `settings.kind` SHALL be required and have one of the values `content`, `page`, `block`; the value SHALL point to an existing type of this kind. The field SHALL support multiplicity and translations by the general rules; the filters — `eq` and `in`. An empty value SHALL clear the field. After values appear, `settings.kind` SHALL NOT change. The write of a value SHALL block the deletion of the reference for the duration of the transaction. A type to which any value of a `type_ref` field refers (except the own values of this type) SHALL NOT be deleted.

#### Scenario: Storing and reading
- **WHEN** the `key` of an existing block type is written to a `type_ref` field with `kind = block`
- **THEN** on reading the same `key` is returned

#### Scenario: Type of another kind
- **WHEN** the `key` of a type of kind `page` is written to a field with `kind = block`
- **THEN** the write is rejected with an error next to the field

#### Scenario: Non-existent type
- **WHEN** a `key` that does not exist is written
- **THEN** the write is rejected with an error next to the field

#### Scenario: Settings are specified incorrectly
- **WHEN** `settings.kind` is absent, does not belong to `content`/`page`/`block`, or there are other keys in `settings`
- **THEN** saving the field is rejected with an error next to `settings.kind`

#### Scenario: Change of the kind after values appear
- **WHEN** `settings.kind` of a field that already has values is changed
- **THEN** the change is rejected

#### Scenario: Deleting a type that is referenced
- **WHEN** a type is deleted whose `key` is written in `type_ref` of any other record
- **THEN** the deletion is rejected with an explanation that the type is used

### Requirement: Field type "reference to a node"
The system SHALL support the field type `node_ref`. Its value is the `uuid` of an existing node (lowercase), stored in the table of string values. The setting `settings.content_type` SHALL be required and point to the `key` of an existing type; the value SHALL point to a node of this type. Support for multiplicity, translations, the filters `eq` and `in`, clearing with an empty value, the immutability of the setting after values appear, and the blocking of references for the duration of the write — as for `type_ref`. A node to which any value of a `node_ref` field refers SHALL NOT be deleted; a type whose nodes are referred to in `node_ref` values of other types SHALL NOT be deleted.

#### Scenario: Storing and reading
- **WHEN** the `uuid` of a node of the type `article` is written to a `node_ref` field with the type `article`
- **THEN** on reading the same `uuid` (lowercase) is returned

#### Scenario: Node of another type
- **WHEN** the `uuid` of a node of the type `news` is written to a field for `article`
- **THEN** the write is rejected with an error next to the field

#### Scenario: Non-existent node or not a uuid
- **WHEN** a non-existent `uuid` or a string that is not a uuid is written
- **THEN** the write is rejected with an error next to the field

#### Scenario: Settings are specified incorrectly
- **WHEN** `settings.content_type` is absent, unknown, or there are other keys in `settings`
- **THEN** saving the field is rejected with an error next to `settings.content_type`

#### Scenario: Deleting a node that is referenced
- **WHEN** a node is deleted whose `uuid` is written in `node_ref` of another node
- **THEN** the deletion is rejected with an explanation that the node is used

#### Scenario: Filter
- **WHEN** nodes are filtered by a `node_ref` field with the operator `eq` and a `uuid`
- **THEN** the nodes that reference this node are returned

### Requirement: Cells of a table in the visible text of rich text
The visible text of a `richtext` value (used by the length limits, the text search, and the check for an empty value) SHALL separate the text of neighboring cells and of neighboring rows of a table with a space, so that the text of two cells never merges into one word. A table whose cells contain no text SHALL not make the value non-empty.

#### Scenario: Cells do not merge in the visible text
- **WHEN** a `richtext` value holds a table with the cells `10:00` and `Opening` in one row
- **THEN** the visible text contains `10:00 Opening`, not `10:00Opening`

#### Scenario: Rows are separated in the visible text
- **WHEN** a `richtext` value holds a table with the rows `A | B` and `C | D`
- **THEN** the visible text is `A B C D`

#### Scenario: A table without text is empty
- **WHEN** `<table><tr><td></td><td></td></tr></table>` is written to a required `richtext` field
- **THEN** the value is rejected as empty, as an empty paragraph is

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

### Requirement: Link and frame addresses in rich text are strict
The system SHALL remove the address of a link in a `richtext` value when it starts with `//` or contains a backslash; the link text SHALL stay. The system SHALL remove a frame (`iframe`) from a `richtext` value when the path of its address contains a dot segment (`.` or `..`), a backslash, or a percent-encoded dot, slash or backslash (`%2e`, `%2f`, `%5c`, in any letter case), even when the host and the path prefix match an allowed entry. Links with a relative address that starts with a single `/`, `#` or `?`, links with the schemes `http`, `https`, `mailto` and `tel`, and frames of the allowed hosts with an ordinary path SHALL be kept as before.

#### Scenario: Protocol-relative link
- **WHEN** `<a href="//example.com/a">x</a>` is written to a `richtext` field
- **THEN** the saved link has no `href` and keeps its text

#### Scenario: Backslash link
- **WHEN** `<a href="/\example.com">x</a>` is written to a `richtext` field
- **THEN** the saved link has no `href`

#### Scenario: Ordinary links
- **WHEN** links with `/about`, `#top`, `?page=2`, `https://example.com/a`, `mailto:info@example.com` and `tel:+380441234567` are written to a `richtext` field
- **THEN** every link keeps its address

#### Scenario: Frame path with dot segments
- **WHEN** `<iframe src="https://www.youtube.com/embed/../account"></iframe>` or the same address with `%2e%2e` is written to a `richtext` field
- **THEN** the frame is removed

#### Scenario: Frame path with an encoded separator
- **WHEN** a frame address of an allowed host has `%2f` or `%5c` in its path
- **THEN** the frame is removed

#### Scenario: Ordinary frame
- **WHEN** `<iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe>` or a Google Drive preview, a Google Maps embed or a Facebook plugin address of an allowed host is written to a `richtext` field
- **THEN** the frame is kept with its forced `sandbox`

### Requirement: Saving one language of a node or a type
The system SHALL provide one operation that saves the translated values of a node, or of a type's own values, in one additional language. The operation SHALL accept only fields that are translatable and belong to the owner, SHALL apply the validation rules of each submitted value but SHALL NOT enforce required fields, SHALL write the language through the usual language rules (multilingual mode on, the language in the list and active), SHALL NOT store a value that equals the base value of the same field (compared as the stored value of the field type, a list as a whole in order), SHALL remove the translation of a field submitted empty, and SHALL leave every other field, every other language and the base values untouched. The whole operation SHALL be atomic. A node or type has a translation in a language when at least one value of the owner exists in it.

#### Scenario: Only differing values are stored
- **WHEN** the operation saves `title` equal to the base title and `body` different from the base body in `en`
- **THEN** only the English body is stored

#### Scenario: Non-translatable field
- **WHEN** the operation receives a field that is not translatable
- **THEN** it is rejected with a validation error under that key and nothing is stored

#### Scenario: Required field is not enforced
- **WHEN** the operation receives an empty value for a required translatable field
- **THEN** the translation of that field is removed and no required error is raised

#### Scenario: Invalid language
- **WHEN** the operation targets an inactive language or multilingual mode is off
- **THEN** it is rejected with a validation error under `lang`

#### Scenario: Atomicity
- **WHEN** one submitted value is invalid
- **THEN** none of the submitted values is stored

#### Scenario: Translated language
- **WHEN** a node has one stored English value and none in Polish
- **THEN** the node counts as translated into English and not into Polish

### Requirement: Reading with fallback to the base language
The reading of node values and of a type's own values SHALL support an explicit fallback mode, used by the public site and off by default. In this mode, for each requested field the system SHALL return the value stored in the requested language and, when the field has none (the value is blank according to its type), the value stored in the base language. The fallback SHALL be applied per field: other fields of the same node keep their own language. A multiple field SHALL fall back as a whole list, never mixing items of two languages, and a composed value (a video, a link) SHALL fall back as a whole. The language rules of reading and the default behavior of reading without the mode (a missing translation reads as missing) SHALL NOT change.

#### Scenario: Missing translation falls back
- **WHEN** a node has the base title "Привіт" and no English title, and it is read in English with the fallback mode
- **THEN** the title is "Привіт"

#### Scenario: Translation wins
- **WHEN** the node has the English title "Hello" and it is read in English with the fallback mode
- **THEN** the title is "Hello"

#### Scenario: Fields fall back independently
- **WHEN** a node has an English `title` and only a base `body`, and it is read in English with the fallback mode
- **THEN** the title is English and the body is the base body

#### Scenario: Multiple field
- **WHEN** a multiple field has the base list `a, b` and the English list `c`
- **THEN** the English reading returns `c`; with no English list it returns `a, b`; the lists are never merged

#### Scenario: Strict reading is unchanged
- **WHEN** the same node is read in English without the fallback mode
- **THEN** a field without an English value reads as missing

#### Scenario: Type values
- **WHEN** a type's `section_title` exists only in the base language and is read in English with the fallback mode
- **THEN** the base value is returned
