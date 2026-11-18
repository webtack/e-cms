# Spec Delta

## MODIFIED Requirements

### Requirement: Санітизація HTML форматованого тексту
Before saving, the system SHALL clean the value of `richtext` by an allow-list: only the tags of headings, paragraphs, line breaks, emphasis (bold, italic, underline, strikethrough), quotes, bulleted and numbered lists, links, `span`, images from the storage (with a caption) and frames from allowed hosts are allowed. All other tags, attributes and event handlers SHALL be removed; in links only the schemes `http`, `https`, `mailto`, `tel` (and relative addresses) SHALL be allowed, and links SHALL have safe `rel` values. The attribute `style` SHALL be allowed only on paragraphs, headings, quotes, list items, `span` and `figure` (on `figure` only `text-align`, `width`, `max-width` and `margin-left`/`margin-right`, see below) and SHALL keep only the properties `text-align` (`left`, `center`, `right`, `justify`), `color` and `background-color` with a value in the format `#rgb`, `#rrggbb` or `rgb(r, g, b)`; any other property or value (in particular `url(...)`, `expression(...)`, `position`) SHALL be removed. The tag `img` SHALL remain only with the attributes `src`, `alt` and `width`, and only when `src` is a relative address of a file of the storage of the form `/storage/{uuid}.{extension}` (without the protocol and the domain); an image with any other address (external, protocol-relative `//host/...`, `data:`, with another path) SHALL be removed entirely. The attribute `width` SHALL remain only as an integer from 1 to 4000 (otherwise the attribute is removed, and the image remains); `height` SHALL be removed. The caption SHALL be stored as a `figure` containing `img` and a `figcaption` with the text; `figcaption` SHALL have no attributes, `figure` SHALL have only a `style` with the width, the alignment and `max-width: 100%`: `width` — only `{N}px` (an integer 1–4000), `fit-content` or `min-content` (saved earlier), `margin-left`/`margin-right` — only `auto` or `0`, `max-width` — only `100%`, `text-align` — `left|center|right`. The frame `iframe` SHALL remain only with an `https` address on a host (if needed with a path prefix) from the list of allowed hosts in the configuration, which by default includes video services, Google Drive and Google Docs, social networks, and only with the attributes `src`, `width`, `height`, `title`, `allowfullscreen`, `allow`; the system SHALL forcibly set `sandbox` and `loading="lazy"` on it, otherwise the whole tag SHALL be removed. The cleaning SHALL take place on the server regardless of the widget, and when reading, the already cleaned value SHALL be returned. The text content that remains after removing the forbidden tags SHALL be kept.

#### Scenario: Script
- **WHEN** `<p>Hi</p><script>alert(1)</script>` is written into the field `richtext`
- **THEN** `<p>Hi</p>` is saved, without the tag `script` and its content

#### Scenario: Event handler
- **WHEN** `<p onclick="x()">Hi</p>` is written into the field `richtext`
- **THEN** `<p>Hi</p>` is saved, without the attribute `onclick`

#### Scenario: Dangerous link scheme
- **WHEN** `<a href="javascript:alert(1)">x</a>` is written into the field `richtext`
- **THEN** the saved HTML contains no link with the scheme `javascript:`

#### Scenario: Safe link
- **WHEN** `<a href="https://example.com">x</a>` is written into the field `richtext`
- **THEN** the link is saved with the same address

#### Scenario: Image
- **WHEN** `<img src="https://evil.example/a.png">` is written into the field `richtext`
- **THEN** the tag is removed from the saved value

#### Scenario: Image from the storage
- **WHEN** `<img src="/storage/{uuid}.png" alt="Лого" onerror="x()">` is written into the field `richtext`
- **THEN** the `img` with the same address and `alt` is saved, without `onerror`

#### Scenario: Image width
- **WHEN** `<img src="/storage/{uuid}.png" width="200" height="100">` is written into the field `richtext`
- **THEN** the `img` is saved with `width="200"` without `height`

#### Scenario: Invalid width
- **WHEN** `<img src="/storage/{uuid}.png" width="5000">` or `width="10px"` is written into the field `richtext`
- **THEN** the `img` is saved without `width`

#### Scenario: Image caption
- **WHEN** `<figure class="x" onclick="y()"><img src="/storage/{uuid}.png"><figcaption>Офіс</figcaption></figure>` is written into the field `richtext`
- **THEN** a `figure` with an `img` and a `figcaption` «Офіс» is saved, without `class` and `onclick`

#### Scenario: Aligned image
- **WHEN** `<figure style="text-align: center"><img src="/storage/{uuid}.png"></figure>` is written into the field `richtext`
- **THEN** a `figure` with `style="text-align: center"` and an `img` is saved

#### Scenario: Aligned image with a caption
- **WHEN** `<figure style="text-align: right"><img src="/storage/{uuid}.png"><figcaption>Офіс</figcaption></figure>` is written into the field `richtext`
- **THEN** a `figure` with `style="text-align: right"`, an `img` and a `figcaption` «Офіс» is saved

#### Scenario: Alignment through margins
- **WHEN** `<figure style="width: 500px; max-width: 100%; margin-left: auto; margin-right: auto; text-align: center; position: absolute"><img src="/storage/{uuid}.png"><figcaption>Офіс</figcaption></figure>` is written into the field `richtext`
- **THEN** a `figure` with only `width: 500px`, `max-width: 100%`, `margin-left: auto`, `margin-right: auto`, `text-align: center` is saved, without `position`

#### Scenario: Allowed widths of figure
- **WHEN** `width: 200px`, `width: fit-content` or (saved earlier) `width: min-content` is written on a `figure`
- **THEN** each value is saved

#### Scenario: Invalid values on figure
- **WHEN** `width: 9999px`, `width: 50%` or `margin-left: 50px` is written on a `figure`
- **THEN** these properties are removed

#### Scenario: Other properties on figure
- **WHEN** `<figure style="position: absolute; text-align: center"><img src="/storage/{uuid}.png"></figure>` is written into the field `richtext`
- **THEN** a `figure` with only `style="text-align: center"` is saved

#### Scenario: The address of an image is not relative
- **WHEN** `<img src="//host/storage/{uuid}.png">`, `<img src="http://localhost/storage/{uuid}.png">` or `<img src="data:image/png;base64,AAAA">` is written into the field `richtext`
- **THEN** the saved value contains no tag `img`

#### Scenario: Alignment is kept
- **WHEN** `<p style="text-align: center">Hi</p>` is written into the field `richtext`
- **THEN** a paragraph with `style="text-align: center"` is saved

#### Scenario: Text and background colour are kept
- **WHEN** `<span style="color: #ff0000; background-color: #ffff00">x</span>` is written into the field `richtext`
- **THEN** a `span` with both properties is saved

#### Scenario: Dangerous style is cleaned
- **WHEN** `<span style="color: red; position: fixed; background-color: url(javascript:x)">x</span>` is written into the field `richtext`
- **THEN** the saved `style` has no `position` and no `url(`, and the text `x` is saved

#### Scenario: Style on a disallowed tag is removed
- **WHEN** `<strong style="color: #f00">x</strong>` is written into the field `richtext`
- **THEN** `<strong>x</strong>` is saved without `style`

#### Scenario: Frame from an allowed host is kept
- **WHEN** `<iframe src="https://www.youtube-nocookie.com/embed/abc" onload="x()"></iframe>` is written into the field `richtext`
- **THEN** an `iframe` with the same address, `sandbox` and `loading="lazy"` is saved, without `onload`

#### Scenario: Attribute allow is cleaned
- **WHEN** `<iframe src="https://www.youtube-nocookie.com/embed/abc" allow="autoplay; camera; picture-in-picture"></iframe>` is written into the field `richtext`
- **THEN** the saved `allow` equals `autoplay; picture-in-picture`, and `camera` is removed

#### Scenario: Frame of Google Drive or a social network is kept
- **WHEN** `<iframe src="https://drive.google.com/file/d/abc/preview"></iframe>` or `<iframe src="https://www.facebook.com/plugins/post.php?href=x"></iframe>` is written into the field `richtext`
- **THEN** both `iframe`s are saved with `sandbox` and `loading="lazy"`

#### Scenario: Frame of Google Forms is kept
- **WHEN** `<iframe src="https://docs.google.com/forms/d/e/abc/viewform?embedded=true"></iframe>` is written into the field `richtext`
- **THEN** the `iframe` is saved with the same address, `sandbox` and `loading="lazy"`

#### Scenario: Frame outside the path prefix is removed
- **WHEN** `<iframe src="https://www.facebook.com/some.page"></iframe>` is written into the field `richtext`
- **THEN** the saved value contains no tag `iframe`

#### Scenario: Frame from a foreign host or not https is removed
- **WHEN** `<iframe src="https://evil.example/x"></iframe>` or `<iframe src="http://www.youtube.com/embed/a"></iframe>` is written into the field `richtext`
- **THEN** the saved value contains no tag `iframe`

## ADDED Requirements

### Requirement: Зображення в значенні форматованого тексту
Each image in the value of `richtext` SHALL reference an existing file of the profile `image`; a value that references a non-existent file or a file of another profile SHALL be rejected with an error for this field, and nothing is written. A value that contains an image SHALL be considered non-empty even without visible text. The length by the visible text does not count the image itself, but counts the text of the caption (`figcaption`), as well as the search by text does.

#### Scenario: Existing file
- **WHEN** `<p><img src="/storage/{uuid}.png"></p>` is written into the field `richtext`, where the file `{uuid}` of the profile `image` exists
- **THEN** the value is saved

#### Scenario: Non-existent file
- **WHEN** `<img src="/storage/{uuid}.png">` is written into the field `richtext`, and the file `{uuid}` does not exist
- **THEN** the record is rejected with an error for this field

#### Scenario: File of another profile
- **WHEN** an `<img>` with the address of a file of the profile `document` is written into the field `richtext`
- **THEN** the record is rejected with an error for this field

#### Scenario: Caption in search and length
- **WHEN** the caption of an image is «Наш офіс» and the record is searched by the word «офіс»
- **THEN** the record is found, and the length by the visible text includes the characters of the caption

#### Scenario: Only an image
- **WHEN** `<p><img src="/storage/{uuid}.png"></p>` without text is written into a required field `richtext`
- **THEN** the value is considered non-empty and saved
