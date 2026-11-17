## ADDED Requirements

### Requirement: Styled login page
The login page SHALL be displayed over a full-page background image, with the login form placed in a card-coloured block centred on the page, and the admin logo (its white variant in the dark theme) and the site name from the site settings shown at the top of that block, with the application name as the fallback. The language switcher SHALL remain available. The form fields, validation errors, and behaviour of the login SHALL NOT change.

#### Scenario: Look of the login page
- **WHEN** a guest opens `/login`
- **THEN** the page shows the background image, the logo, the site name, and the email and password fields inside a card block

#### Scenario: Errors in the card
- **WHEN** a guest submits wrong credentials
- **THEN** the error message is shown inside the card block, and the page keeps its look
