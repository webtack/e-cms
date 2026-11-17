## RENAMED Requirements

- FROM: `### Requirement: Main navigation with dropdowns without JavaScript`
- TO: `### Requirement: Main navigation`

## MODIFIED Requirements

### Requirement: Main navigation
The main navigation SHALL show a home-icon link and the top-level items of the design; items that have sub-items SHALL open a dropdown of three columns, each closed by a brand stripe. On screens 1024 px wide and wider the dropdown SHALL open on pointer hover and on keyboard focus within the item, without requiring JavaScript. On screens narrower than 1024 px the navigation SHALL be collapsed behind a menu button; the button SHALL open and close the navigation panel and SHALL expose its state to assistive technology; inside the open panel each item that has sub-items SHALL expand and collapse its sub-links on activation; the panel SHALL close on the Escape key and when the visitor activates something outside it. If the theme script does not run, the navigation SHALL stay usable: below 1024 px the panel SHALL be shown expanded with all sub-links visible. The navigation items SHALL be static markup in this change.

#### Scenario: Dropdown on hover
- **WHEN** a visitor on a 1440 px wide screen moves the pointer over the item "Департамент"
- **THEN** its dropdown with three columns of links becomes visible

#### Scenario: Dropdown on keyboard focus
- **WHEN** a visitor on a 1440 px wide screen tabs onto a link inside an item with a dropdown
- **THEN** the dropdown stays visible while focus is inside the item

#### Scenario: Collapsed navigation on a phone
- **WHEN** a visitor opens any public page on a 375 px wide screen
- **THEN** the top-level items are hidden and a menu button is shown in the navigation bar

#### Scenario: Opening the menu
- **WHEN** a visitor on a 375 px wide screen activates the menu button
- **THEN** the panel opens with the home link and every top-level item, the button reports it is expanded, and activating the button again closes the panel

#### Scenario: Expanding a sub-menu
- **WHEN** the panel is open on a 375 px wide screen and the visitor activates the item "Департамент"
- **THEN** its sub-links become visible and the item reports it is expanded, and the item's link does not navigate

#### Scenario: Closing the menu
- **WHEN** the panel is open and the visitor presses Escape or taps outside the panel
- **THEN** the panel closes

#### Scenario: Menu without the script
- **WHEN** a visitor on a 375 px wide screen has scripts disabled
- **THEN** the navigation panel is shown expanded with every item and sub-link visible

### Requirement: Design styling and assets
The theme SHALL use the design's colour tokens, the fonts Nunito, Inter and Philosopher served from the site itself (no external font requests), and the design's component styles. Images and fonts SHALL be served from the site itself under `/app/dcculture/images/` and `/app/dcculture/fonts/`. The theme MAY load its own JavaScript bundle; the frame and the home page SHALL remain readable and navigable when it does not run.

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
- **THEN** all sections are visible and all navigation links are reachable

## REMOVED Requirements

### Requirement: Wide-screen layout
**Reason**: The theme was desktop-only (1200 px container, `min-width: 1440px`); it now adapts to any screen width, see "Responsive layout".
**Migration**: The 1200 px container on wide screens is kept and restated in "Responsive layout".

## ADDED Requirements

### Requirement: Responsive layout
The theme SHALL render the frame and the home page without horizontal page scrolling at any viewport width from 360 px up. The content container SHALL be centred, SHALL have side margins that shrink as the screen narrows, and SHALL be 1200 px wide on screens 1440 px wide and wider. The theme SHALL use one set of breakpoints for all components: the desktop layout of the approved design applies from 1024 px, and narrower screens use a tablet and phone layout. From 1024 px the page SHALL look as in the approved design.

#### Scenario: Container on a 1440 px window
- **WHEN** the home page is shown in a 1440 px wide window
- **THEN** the content container is 1200 px wide and horizontally centred

#### Scenario: No horizontal scroll on a phone
- **WHEN** the home page is shown in a 360 px wide window
- **THEN** the page width equals the window width and no horizontal page scrollbar appears

#### Scenario: Side margins shrink
- **WHEN** the window is narrowed from 1440 px to 768 px
- **THEN** the content container takes the window width minus smaller side margins, and no content is cut off

### Requirement: Adaptive frame and home sections
Below 1024 px the frame and the home sections SHALL adapt as follows, with the same texts, images and links in the same order: the top bar SHALL hide its secondary items and keep the phone and the accessibility link reachable; the header search field SHALL take the full width under the brand; the news and the poster columns SHALL be stacked with the poster under the news; the "about the city" block, the news cards, the contest cards, and the footer columns SHALL stack into fewer columns, down to one on phones; the tourist-banner content and button, and the map address card, SHALL fit the screen width without overlapping each other or being cut off; the footer SHALL grow to fit its content.

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
