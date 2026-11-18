## ADDED Requirements

### Requirement: Тип поля «посилання»
The system SHALL support the field type `url`. Its value SHALL consist of a link (a scalar value, in the table of string values) and an optional title (additional data). The link SHALL be stored as entered, after trimming the spaces at the edges. The accepted forms: absolute links with the scheme `http` or `https` and a host, `mailto:` and `tel:` links with a non-empty address, and relative ones — a path from the root (`/…`, but not `//…`), `./…`, `../…`, a fragment (`#…`) and a query (`?…`). Links with whitespace or control characters inside and with other schemes (`javascript:`, `data:`, `ftp:` etc.) SHALL be rejected. The length of the link SHALL be no more than 512 characters, of the title — no more than 255. The title SHALL be optional: an empty title or one consisting only of spaces is not stored and is read as `null`. A value without a link SHALL be considered empty, even if the title is filled. The type SHALL have no settings of the field. Saving SHALL NOT access the network nor check that the link opens. The general rules SHALL apply to a multiple field and to translations. The filter by the type SHALL support equality (`eq`), a list (`in`) and containment (`contains`) by the link.

#### Scenario: Saving and reading an absolute link
- **WHEN** the link `https://example.com/page?a=1#top` and the title `Приклад` are written to the field `url`
- **THEN** on reading the same link and the title `Приклад` are returned

#### Scenario: Relative link
- **WHEN** `/about`, `./team`, `../docs/a.pdf`, `#contacts` or `?page=2` are written to the field
- **THEN** the value is stored unchanged

#### Scenario: Link without a title
- **WHEN** a link without a title or with a title of only spaces is written to the field
- **THEN** the value is stored, and the title is read as `null`

#### Scenario: Mail and phone
- **WHEN** `mailto:info@example.com` or `tel:+380441234567` is written to the field
- **THEN** the value is stored unchanged

#### Scenario: Trimming of spaces
- **WHEN** `  /about  ` is written to the field
- **THEN** `/about` is stored

#### Scenario: Forbidden scheme
- **WHEN** `javascript:alert(1)`, `data:text/html,x` or `ftp://example.com/a` is written to the field
- **THEN** the write is rejected with an error next to the field, nothing is stored

#### Scenario: Protocol-relative link
- **WHEN** `//example.com/a` is written to the field
- **THEN** the write is rejected with an error next to the field

#### Scenario: Spaces inside the link
- **WHEN** `/about us` is written to the field
- **THEN** the write is rejected with an error next to the field

#### Scenario: Data that is too long
- **WHEN** the link is longer than 512 characters or the title is longer than 255
- **THEN** the write is rejected with an error next to the field

#### Scenario: Required field
- **WHEN** a required field `url` remains without a link but with a filled title
- **THEN** the write is rejected with an error next to the field

#### Scenario: Settings are forbidden
- **WHEN** `settings` are passed to a field of the type `url`
- **THEN** the write of the field is rejected, as for the other types without settings

#### Scenario: Multiple field
- **WHEN** a list of values with different titles is written to a multiple field `url`
- **THEN** each element is stored in a separate row with its own title, the order is kept

#### Scenario: Search by link
- **WHEN** the records are filtered by the field `url` with containment `example.com`
- **THEN** the records whose link contains `example.com` are returned

#### Scenario: The type does not change the general mechanism
- **WHEN** the type `url` is added
- **THEN** only its class and a row in the registry of types need changes; there are no new tables
