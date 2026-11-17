## MODIFIED Requirements

### Requirement: Item address
An item SHALL have an optional address `url` — a string of up to 2048 characters. The allowed forms: relative, starting with `/` (but not with `//`), `#`, or `?`; absolute with the scheme `http`, `https`, `mailto`, or `tel`. Other schemes (in particular `javascript:` and `data:`), addresses with spaces, control characters or a backslash, and addresses starting with `//` SHALL be rejected with a validation error for the field `url`. An empty address (`''` or `null`) SHALL be stored as absent and mean a heading item of a group without a link. The system does not check the address against existing pages and records.

#### Scenario: Relative address
- **WHEN** the address `/about` is set for an item
- **THEN** the address is saved

#### Scenario: Absolute address
- **WHEN** the address `https://example.com/page` or `mailto:info@example.com` is set for an item
- **THEN** the address is saved

#### Scenario: Dangerous scheme
- **WHEN** the address `javascript:alert(1)` is set for an item
- **THEN** saving is rejected with a validation error for the field `url`

#### Scenario: Address without a scheme and without a slash
- **WHEN** the address `about` or `//example.com` is set for an item
- **THEN** saving is rejected with a validation error for the field `url`

#### Scenario: Backslash in the address
- **WHEN** the address `/\example.com` is set for an item
- **THEN** saving is rejected with a validation error for the field `url`

#### Scenario: Heading item of a group
- **WHEN** the address of an item is left empty
- **THEN** the item is saved without an address
