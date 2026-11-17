## ADDED Requirements

### Requirement: Resource-heavy helper endpoints are rate limited
The system SHALL limit, per signed-in user, requests to the file upload endpoint, the two lookup endpoints for references, and the YouTube lookup endpoint. A request over the limit SHALL be answered with status 429 and SHALL NOT perform the work. The YouTube lookup SHALL have a lower limit than the others, because each call can spend the quota of an external service. The limit of one user SHALL NOT affect another user, and a request within the limit SHALL behave as before, including its access checks.

#### Scenario: Within the limit
- **WHEN** an editor uploads files and uses the reference autocomplete at a normal pace
- **THEN** every request is answered as before

#### Scenario: Over the limit
- **WHEN** one user sends more requests to one of these endpoints in a minute than its limit allows
- **THEN** the extra requests are answered with status 429 and no file is stored and no external request is made

#### Scenario: Another user
- **WHEN** one user is over the limit of an endpoint and another user calls the same endpoint
- **THEN** the second user's request is answered as usual

#### Scenario: Access is still checked
- **WHEN** a user without the right to write anything calls the upload endpoint within the limit
- **THEN** the response is 403 as before
