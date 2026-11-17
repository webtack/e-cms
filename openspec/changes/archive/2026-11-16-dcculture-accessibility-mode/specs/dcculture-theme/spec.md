## MODIFIED Requirements

### Requirement: Top bar and header content
The top bar SHALL show the current date, the working-status text, the phone as a `tel:` link, the accessibility button (the dropdown of the capability `dcculture-accessibility`) and, on screens 1024 px wide and wider, social links. On screens narrower than 1024 px the top bar SHALL show a search button and a menu button in place of the social links. The site header SHALL show the logo linking to `/`, the department name with its subtitle, the "official site" tagline and, on screens 1024 px wide and wider, a search form. In this change this content SHALL be the static text of the design, not data from site settings.

#### Scenario: Static header content
- **WHEN** any public page is rendered
- **THEN** the header shows the department name and subtitle of the design, and the logo links to `/`

#### Scenario: Social links on a desktop
- **WHEN** a visitor opens any public page on a 1440 px wide screen
- **THEN** the top bar shows the social links and neither the search button nor the menu button, and the header shows the search form

#### Scenario: Buttons instead of social links on a phone
- **WHEN** a visitor opens any public page on a 375 px wide screen
- **THEN** the top bar shows the search button and the menu button on its right side, the social links are not shown, and the header does not show the search form

#### Scenario: Accessibility button in the top bar
- **WHEN** a visitor opens any public page
- **THEN** the top bar shows the accessibility button, which opens a dropdown instead of navigating to another address
