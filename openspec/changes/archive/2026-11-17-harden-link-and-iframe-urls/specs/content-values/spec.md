## ADDED Requirements

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
