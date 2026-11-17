# Spec Delta

## MODIFIED Requirements

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

## ADDED Requirements

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
