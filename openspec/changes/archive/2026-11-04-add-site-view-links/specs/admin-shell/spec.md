## ADDED Requirements

### Requirement: Посилання на сайт у шапці
The header of the admin shell SHALL contain an icon button «Відкрити сайт» ("Open site") with a label for screen readers and a hint, which leads to the home page of the public site (`/`) and opens in a new browser tab. The button SHALL be available to every user of the admin on all its pages and SHALL NOT reload or close the current page of the admin.

#### Scenario: Button in the header
- **WHEN** an authenticated user opens any page of the admin
- **THEN** the header has the icon button «Відкрити сайт» ("Open site") with the link `/`

#### Scenario: Opening in a new tab
- **WHEN** the user clicks the button «Відкрити сайт» ("Open site")
- **THEN** the home page of the site opens in a new tab, and the admin remains open in the current one
