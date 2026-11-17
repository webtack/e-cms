## ADDED Requirements

### Requirement: Site search is rate limited
The system SHALL limit requests to the site search page per IP address. A request over the limit SHALL be answered with status 429 and SHALL NOT query the nodes. Requests within the limit SHALL behave as before, and the other public pages SHALL NOT be affected.

#### Scenario: Within the limit
- **WHEN** a guest searches a few times in a minute
- **THEN** every search is answered with its results

#### Scenario: Over the limit
- **WHEN** one IP address sends more search requests in a minute than the limit allows
- **THEN** the extra requests are answered with status 429 without querying nodes, and other pages of the site still open

#### Scenario: Another IP address
- **WHEN** one IP address is over the search limit and a guest from another IP address searches
- **THEN** the search of the second guest is answered as usual
