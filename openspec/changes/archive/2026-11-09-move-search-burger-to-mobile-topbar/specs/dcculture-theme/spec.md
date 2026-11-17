# Spec Delta

## MODIFIED Requirements

### Requirement: Top bar and header content
The top bar SHALL show the current date, the working-status text, the phone as a `tel:` link, a link to the accessibility version, and, on screens 1024 px wide and wider, social links. On screens narrower than 1024 px the top bar SHALL show a search button and a menu button in place of the social links. The site header SHALL show the logo linking to `/`, the department name with its subtitle, the "official site" tagline and, on screens 1024 px wide and wider, a search form. In this change this content SHALL be the static text of the design, not data from site settings.

#### Scenario: Static header content
- **WHEN** any public page is rendered
- **THEN** the header shows the department name and subtitle of the design, and the logo links to `/`

#### Scenario: Social links on a desktop
- **WHEN** a visitor opens any public page on a 1440 px wide screen
- **THEN** the top bar shows the social links and neither the search button nor the menu button, and the header shows the search form

#### Scenario: Buttons instead of social links on a phone
- **WHEN** a visitor opens any public page on a 375 px wide screen
- **THEN** the top bar shows the search button and the menu button on its right side, the social links are not shown, and the header does not show the search form

### Requirement: Main navigation
The main navigation SHALL show a home-icon link and the top-level items of the design; items that have sub-items SHALL open a dropdown of three columns, each closed by a brand stripe. On screens 1024 px wide and wider the dropdown SHALL open on pointer hover and on keyboard focus within the item, without requiring JavaScript. On screens narrower than 1024 px no navigation bar SHALL be shown; the menu button in the top bar SHALL open the navigation in a full-screen modal and SHALL expose its state to assistive technology; the modal SHALL contain the home link, every top-level item and a close button; inside it each item that has sub-items SHALL expand and collapse its sub-links on activation; the modal SHALL close with the close button and on the Escape key, and the page behind it SHALL NOT scroll while it is open. The navigation items SHALL be static markup in this change.

#### Scenario: Dropdown on hover
- **WHEN** a visitor on a 1440 px wide screen moves the pointer over the item "Департамент"
- **THEN** its dropdown with three columns of links becomes visible

#### Scenario: Dropdown on keyboard focus
- **WHEN** a visitor on a 1440 px wide screen tabs onto a link inside an item with a dropdown
- **THEN** the dropdown stays visible while focus is inside the item

#### Scenario: Collapsed navigation on a phone
- **WHEN** a visitor opens any public page on a 375 px wide screen
- **THEN** no navigation bar is shown under the header, and the menu button is shown in the top bar

#### Scenario: Opening the menu
- **WHEN** a visitor on a 375 px wide screen activates the menu button in the top bar
- **THEN** a full-screen modal opens with the home link, every top-level item and a close button, the button reports it is expanded, and the page behind does not scroll

#### Scenario: Expanding a sub-menu
- **WHEN** the menu modal is open on a 375 px wide screen and the visitor activates the item "Департамент"
- **THEN** its sub-links become visible and the item reports it is expanded, and the item's link does not navigate

#### Scenario: Closing the menu
- **WHEN** the menu modal is open and the visitor activates the close button or presses Escape
- **THEN** the modal closes, the page scrolls again, and focus returns to the menu button

#### Scenario: Menu without the script
- **WHEN** a visitor on a 375 px wide screen has scripts disabled
- **THEN** the page content is readable and the menu is not available; the menu requires the script below 1024 px

### Requirement: Design styling and assets
The theme SHALL use the design's colour tokens, the fonts Nunito, Inter and Philosopher served from the site itself (no external font requests), and the design's component styles. Images and fonts SHALL be served from the site itself under `/app/dcculture/images/` and `/app/dcculture/fonts/`. The theme MAY load its own JavaScript bundle; the frame and the home page SHALL remain readable when it does not run, and on screens 1024 px wide and wider the navigation links SHALL remain reachable. On screens narrower than 1024 px the menu and the search require the script.

#### Scenario: No external requests for fonts
- **WHEN** the home page is loaded
- **THEN** all font files are requested from the same origin under `/app/dcculture/fonts/`

#### Scenario: Images resolve
- **WHEN** the home page is loaded
- **THEN** every image of the frame and the home sections loads with a successful status

#### Scenario: Admin panel unaffected
- **WHEN** an administrator opens any `/admin` page
- **THEN** the look of the admin panel is the same as before this change

#### Scenario: Page readable without the script
- **WHEN** the home page is opened with scripts disabled
- **THEN** all sections are visible, and on a 1440 px wide screen all navigation links are reachable

### Requirement: Adaptive frame and home sections
Below 1024 px the frame and the home sections SHALL adapt as follows, with the same texts, images and links in the same order: the top bar SHALL hide its secondary items and keep the phone and the accessibility link reachable; the header SHALL NOT show the search form, which the search button in the top bar SHALL open in a modal instead; the news and the poster columns SHALL be stacked with the poster under the news; the "about the city" block, the news cards, the contest cards, and the footer columns SHALL stack into fewer columns, down to one on phones; the tourist-banner content and button, and the map address card, SHALL fit the screen width without overlapping each other or being cut off; the footer SHALL grow to fit its content.

#### Scenario: News and poster on a tablet
- **WHEN** the home page is shown in a 768 px wide window
- **THEN** the "Афіша" column is placed below the news column and both use the container width

#### Scenario: Contest cards by width
- **WHEN** the home page is shown at 1440 px, at 768 px and at 375 px
- **THEN** the contest cards are shown in three, two and one column respectively

#### Scenario: Footer on a phone
- **WHEN** the footer is shown in a 375 px wide window
- **THEN** its three groups (contacts, schedule, useful links) are stacked in one column and all text is visible without overlap

#### Scenario: Photo strip on a phone
- **WHEN** the home page is shown in a 375 px wide window
- **THEN** the photo strip keeps scrolling continuously without a visible gap or jump, and does not widen the page

#### Scenario: Opening the search
- **WHEN** a visitor on a 375 px wide screen activates the search button in the top bar
- **THEN** a modal opens over a dimmed page with the search form and a close button, the input has keyboard focus, the button reports it is expanded, and the page behind does not scroll

#### Scenario: Closing the search
- **WHEN** the search modal is open and the visitor activates the close button, taps the dimmed area, or presses Escape
- **THEN** the modal closes, the page scrolls again, and focus returns to the search button
