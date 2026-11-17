# Spec Delta

## Purpose

The public theme `dcculture` of the Department of Culture and Youth of Poltava City Council: its shared page frame shown on every public page, the home page template, and the design's styling. It lets the site look like the approved design while the content stays static until backend data is wired in.

## ADDED Requirements

### Requirement: Theme structure and activation
The theme `dcculture` SHALL be a valid theme directory with a `theme.json` and its own `assets/` and `views/`. It SHALL contain only the templates that differ from `default`; every other template (pages, content, fields, blocks, menus, 404 content) SHALL still be taken from `default`. The theme SHALL be active when `APP_THEME=dcculture`.

#### Scenario: Theme check passes
- **WHEN** `APP_THEME=dcculture` and the theme check is run
- **THEN** the check reports no problems for `dcculture` and for `default`

#### Scenario: A template the theme does not override
- **WHEN** a visitor opens a page whose template exists only in `default`
- **THEN** that template is rendered by `default` inside the `dcculture` frame

### Requirement: Shared page frame on every public page
Every public page (home, page, node list and details, 404) SHALL render the same frame: the top bar with a small brand stripe, the site header, the main navigation, the page content, and the site footer, in this order. The frame SHALL be defined once and SHALL NOT be repeated in individual page templates. The page content SHALL appear between the main navigation and the footer.

#### Scenario: Frame on the home page
- **WHEN** a guest opens `/`
- **THEN** the page contains the top bar, the brand stripe, the site header, the main navigation, the home content and the footer, in that order

#### Scenario: Frame around other pages
- **WHEN** a guest opens a content page or a non-existent address (404)
- **THEN** the same top bar, header, main navigation and footer are shown around that page's content

### Requirement: Top bar and header content
The top bar SHALL show the current date, the working-status text, the phone as a `tel:` link, a link to the accessibility version, and social links. The site header SHALL show the logo linking to `/`, the department name with its subtitle, the "official site" tagline and a search form. In this change this content SHALL be the static text of the design, not data from site settings.

#### Scenario: Static header content
- **WHEN** any public page is rendered
- **THEN** the header shows the department name and subtitle of the design, and the logo links to `/`

### Requirement: Main navigation with dropdowns without JavaScript
The main navigation SHALL show a home-icon link and the top-level items of the design; items that have sub-items SHALL open a dropdown of three columns, each closed by a brand stripe. The dropdown SHALL open on pointer hover and on keyboard focus within the item, without any JavaScript. The navigation items SHALL be static markup in this change.

#### Scenario: Dropdown on hover
- **WHEN** a visitor moves the pointer over the item "Департамент"
- **THEN** its dropdown with three columns of links becomes visible

#### Scenario: Dropdown on keyboard focus
- **WHEN** a visitor tabs onto a link inside an item with a dropdown
- **THEN** the dropdown stays visible while focus is inside the item

### Requirement: Home page sections
The home page SHALL show, in this order after the frame's navigation: the hero (heading, lead text, photo strip between brand stripes), the "about the city" block, the news and the poster columns, the tourist banner, the contest band, and the map section. The text, images and links of these sections SHALL be the static content of the design. The home page SHALL not depend on any content node, menu or block existing.

#### Scenario: Home sections present
- **WHEN** a guest opens `/` on an empty database
- **THEN** the six sections are present in the stated order and the page responds without error

#### Scenario: Photo strip without JavaScript
- **WHEN** the home page is opened with scripts disabled
- **THEN** the photo strip still scrolls continuously by CSS animation

### Requirement: Design styling and assets
The theme SHALL use the design's colour tokens, the fonts Nunito, Inter and Philosopher served from the site itself (no external font requests), and the design's component styles. Images and fonts SHALL be served from the site itself under `/app/dcculture/images/` and `/app/dcculture/fonts/`. The theme SHALL load no JavaScript from its own bundle for the frame or the home page.

#### Scenario: No external requests for fonts
- **WHEN** the home page is loaded
- **THEN** all font files are requested from the same origin under `/app/dcculture/fonts/`

#### Scenario: Images resolve
- **WHEN** the home page is loaded
- **THEN** every image of the frame and the home sections loads with a successful status

#### Scenario: Admin panel unaffected
- **WHEN** an administrator opens any `/admin` page
- **THEN** the look of the admin panel is the same as before this change

### Requirement: Wide-screen layout
The theme SHALL implement the desktop layout of the design: a centred content container 1200 px wide with side margins. Layouts for narrower screens are not required by this change.

#### Scenario: Container on a 1440 px window
- **WHEN** the home page is shown in a 1440 px wide window
- **THEN** the content container is 1200 px wide and horizontally centred
