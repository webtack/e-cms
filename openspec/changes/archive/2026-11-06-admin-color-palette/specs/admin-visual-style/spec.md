# Spec Delta

## Purpose

Defines the visual style of the admin panel: an indigo colour palette in the light and dark themes, white surfaces on a muted page, semantic tones for statuses and yes/no values, and a consistent bordered grouping of form fields. It describes how the interface looks, not what it does.

## ADDED Requirements

### Requirement: Admin colour palette
The admin panel SHALL use one indigo accent colour for primary actions, the active navigation item, focus indication and links, in both the light and the dark theme. Links in page content (names in tables, "Used in" references, text links in forms) SHALL be shown in the link colour, which in the dark theme SHALL be lighter than in the light theme so that it stays readable. The palette SHALL not change which controls exist or how they behave.

#### Scenario: Primary action colour
- **WHEN** a user opens any admin form in the light theme
- **THEN** the primary action button ("Save") is indigo with light text, and the focused input shows an indigo focus ring

#### Scenario: Link colour in both themes
- **WHEN** a user opens the users list in the light theme and then switches to the dark theme
- **THEN** the user names are links in indigo in both themes, and the dark-theme link colour is readable on the dark surface

### Requirement: Tinted sidebar and muted page background
The side navigation SHALL be a light surface with a faint indigo tint in the light theme (and a dark tinted surface in the dark theme), and the active item SHALL be marked by a soft indigo highlight. The page area behind the content SHALL be a muted, slightly cool grey that is visibly different from white, in both themes (a dark equivalent in the dark theme).

#### Scenario: Sidebar and page contrast
- **WHEN** a user opens `/admin/dashboard` in the light theme
- **THEN** the sidebar is a faintly tinted light surface, the active item has an indigo highlight, and the page area is a muted grey rather than white

### Requirement: White surfaces on the muted page
Tables, form sections, the header and the sticky form action bar SHALL be shown as white surfaces (the card colour) over the muted page area in the light theme, and as a slightly lighter surface than the page in the dark theme. Text inputs, selects, multiline inputs and outline buttons SHALL use the same white surface colour, not the page colour.

#### Scenario: Table on the muted page
- **WHEN** a user opens a list page with a data table in the light theme
- **THEN** the table is a white bordered panel on the muted page background

#### Scenario: Input on a form section
- **WHEN** a user opens a form that has a text input inside a bordered section
- **THEN** the input background is white and distinguishable from the muted page

### Requirement: Semantic tones
The panel SHALL provide three semantic tones in addition to the accent: success (green), warning (amber) and info (the accent colour). A badge in a tone SHALL be shown as a soft tint of that tone with text in the same tone and no icon. A tone SHALL never be the only carrier of meaning: the badge always contains its text label.

#### Scenario: Badge carries text
- **WHEN** a success badge is shown
- **THEN** it contains a text label, and the meaning is understood without relying on the colour

### Requirement: Publication status colours
The badge of a publication state (of a menu item and of a node) SHALL use a tone per state: "Published" — success, "Scheduled" — info, "Expired" — warning, "Disabled" — neutral grey. The label text of each state SHALL not change.

#### Scenario: Statuses of menu items
- **WHEN** a user opens a menu with items in the states published, scheduled, expired and disabled
- **THEN** their badges are green, indigo, amber and grey respectively, each with its text label

### Requirement: Yes/No values as badges
A yes/no (boolean) value shown in a table or detail view SHALL be displayed as text only: "Yes" as a success badge and "No" as quiet muted text without a background. A table column MAY request a warning tone for "No", in which case "No" is shown as a warning badge. The texts "Yes" and "No" SHALL remain the translated words of the interface language.

#### Scenario: Yes and No in the field list
- **WHEN** a user opens the fields list of a content type
- **THEN** a "Yes" flag is a green badge with the text "Yes", and a "No" flag is muted text "No"

#### Scenario: Warning tone for an inactive user
- **WHEN** a user opens the users list that contains an inactive user
- **THEN** the "Active" cell of that user is an amber badge with the text "No", and the active users have a green badge "Yes"

### Requirement: Admin typography
The admin panel SHALL use one typeface and a compact type scale. The page title of each admin page SHALL be semibold at 20px; the base text size SHALL be 14px; table column headers SHALL be 12px, medium weight and in the muted text colour; the group labels of the side navigation SHALL be small uppercase text with letter spacing. Section headings inside forms SHALL keep their current size. Text inputs SHALL stay at 16px on narrow screens so that mobile browsers do not zoom the page when an input is focused. The login and email verification pages are not covered by this requirement.

#### Scenario: Page title size
- **WHEN** a user opens any admin page (for example `/admin/users`)
- **THEN** the page title is 20px semibold, smaller than before, and is the only first-level heading on the page

#### Scenario: Table header typography
- **WHEN** a user opens a list page with a data table
- **THEN** the column headers are 12px muted text, and the cell text is 14px

#### Scenario: Sidebar group labels
- **WHEN** a user looks at the side navigation
- **THEN** the labels of the groups (for example "Structure" and "Access") are small uppercase text with letter spacing, and the item titles stay regular size

#### Scenario: Auth pages unchanged
- **WHEN** a user opens the login page
- **THEN** its heading keeps its current size

### Requirement: Bordered field groups with a legend
A group of related controls inside a form section SHALL be enclosed in a border with a caption (legend) naming the group. This SHALL apply at least to: "Page fields", "Card fields", "Default sort" and the pagination controls in "Public display"; "Columns", "Search fields" and "Default sort" in the "Node list" section; "Emails" and "Phones" in the "Contacts" section of the site settings; and "Used in" in the media details. Each group SHALL be exposed to assistive technology as a group named by its legend. Grouping SHALL not change field order, labels, validation messages or the saved values.

#### Scenario: Public display groups
- **WHEN** a user opens the "Display" tab of a content type
- **THEN** "Page fields", "Card fields", "Default sort" and the pagination controls each appear in their own bordered group with a legend

#### Scenario: Contacts groups
- **WHEN** a user opens the site settings page
- **THEN** the "Contacts" section shows "Emails" and "Phones" as two separate bordered groups with legends

#### Scenario: Grouping keeps saving unchanged
- **WHEN** a user changes a value inside a group and saves the form
- **THEN** the saved values are the same as they were before the grouping was introduced

### Requirement: Consistent form sections
Form sections that are bordered blocks with a title (the main form, "Public display", "Node list", the permission blocks of a role, the settings sections of the site) SHALL share one appearance: the same border, corner radius, padding and white surface.

#### Scenario: Same look across forms
- **WHEN** a user opens the user form, the role form and the site settings
- **THEN** their bordered sections have the same border, corner radius, padding and white background
