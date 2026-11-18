## MODIFIED Requirements

### Requirement: Sanitization of HTML of formatted text
Before saving, the system SHALL clean the value of `richtext` by an allow-list: only the tags of headings, paragraphs, line breaks, emphasis (bold, italic, underline, strikethrough), quotes, bulleted and numbered lists, links, `span` and frames from the allowed hosts are allowed. All other tags, attributes and event handlers SHALL be removed; for links only the schemes `http`, `https`, `mailto`, `tel` (and relative addresses) SHALL be allowed, and links SHALL have safe `rel` values. The attribute `style` SHALL be allowed only on paragraphs, headings, quotes, list items and `span`, and SHALL keep only the properties `text-align` (`left`, `center`, `right`, `justify`), `color` and `background-color` with a value in the format `#rgb`, `#rrggbb` or `rgb(r, g, b)`; any other property or value (in particular `url(...)`, `expression(...)`, `position`) SHALL be removed. The frame `iframe` SHALL remain only with an address `https` on a host (with a path prefix if needed) from the list allowed in the configuration, which by default includes video services, Google Drive and Google Docs, social networks, and only with the attributes `src`, `width`, `height`, `title`, `allowfullscreen`, `allow`; the system SHALL force on it `sandbox` and `loading="lazy"`, otherwise the whole tag SHALL be removed. The cleaning SHALL take place on the server regardless of the widget, and on reading the already cleaned value SHALL be returned. The text content that remains after removing the forbidden tags SHALL be kept.

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

#### Scenario: Alignment is kept
- **WHEN** `<p style="text-align: center">Hi</p>` is written into the field `richtext`
- **THEN** the paragraph is saved with `style="text-align: center"`

#### Scenario: Text and background colour are kept
- **WHEN** `<span style="color: #ff0000; background-color: #ffff00">x</span>` is written into the field `richtext`
- **THEN** the `span` is saved with both properties

#### Scenario: Unsafe style is cleaned
- **WHEN** `<span style="color: red; position: fixed; background-color: url(javascript:x)">x</span>` is written into the field `richtext`
- **THEN** the saved `style` has no `position` and no `url(`, and the text `x` is saved

#### Scenario: style on a disallowed tag is removed
- **WHEN** `<strong style="color: #f00">x</strong>` is written into the field `richtext`
- **THEN** `<strong>x</strong>` is saved without `style`

#### Scenario: Frame from an allowed host is kept
- **WHEN** `<iframe src="https://www.youtube-nocookie.com/embed/abc" onload="x()"></iframe>` is written into the field `richtext`
- **THEN** an `iframe` with the same address, `sandbox` and `loading="lazy"` is saved, without `onload`

#### Scenario: Attribute allow is cleaned
- **WHEN** `<iframe src="https://www.youtube-nocookie.com/embed/abc" allow="autoplay; camera; picture-in-picture"></iframe>` is written into the field `richtext`
- **THEN** the saved `allow` equals `autoplay; picture-in-picture`, and `camera` is removed

#### Scenario: Google Drive or social network frame is kept
- **WHEN** `<iframe src="https://drive.google.com/file/d/abc/preview"></iframe>` or `<iframe src="https://www.facebook.com/plugins/post.php?href=x"></iframe>` is written into the field `richtext`
- **THEN** both `iframe` tags are saved with `sandbox` and `loading="lazy"`

#### Scenario: Google Forms frame is kept
- **WHEN** `<iframe src="https://docs.google.com/forms/d/e/abc/viewform?embedded=true"></iframe>` is written into the field `richtext`
- **THEN** the `iframe` is saved with the same address, `sandbox` and `loading="lazy"`

#### Scenario: Frame outside the path prefix is removed
- **WHEN** `<iframe src="https://www.facebook.com/some.page"></iframe>` is written into the field `richtext`
- **THEN** the saved value has no `iframe` tag

#### Scenario: Frame from a foreign host or not https is removed
- **WHEN** `<iframe src="https://evil.example/x"></iframe>` or `<iframe src="http://www.youtube.com/embed/a"></iframe>` is written into the field `richtext`
- **THEN** the saved value has no `iframe` tag
