# Spec Delta

## ADDED Requirements

### Requirement: Active state in the main navigation
The main navigation SHALL mark the active item of the menu `main` with the class `is-active` and `aria-current="page"` on its link, in the top level and in a dropdown, and SHALL mark the top-level item that contains the active sub-item with the class `is-trail`. Both states SHALL be visibly different from an ordinary item in the design's colours, on screens 1024 px wide and wider and in the full-screen modal below it. The home-icon link SHALL be marked active on the home page only. A top-level item that is a dropdown trigger without an address SHALL still show the trail state when one of its sub-items is active.

#### Scenario: A top-level item
- **WHEN** the page of a top-level item without sub-items is shown
- **THEN** its link is marked active

#### Scenario: A sub-item
- **WHEN** the page of a sub-item in a dropdown is shown
- **THEN** the sub-link in the dropdown is marked active and its top-level parent is marked as in the trail

#### Scenario: The home page
- **WHEN** the home page is shown
- **THEN** the home-icon link is marked active and no other item is

### Requirement: Breadcrumbs in the theme
Every public page that has a trail SHALL show the breadcrumbs between the main navigation and the page content, as a list of links to the ancestors followed by the current page without a link, styled to the design (colour tokens, fonts, and spacing of the theme) and readable without horizontal scrolling from 360 px width. A long trail SHALL wrap or scroll inside its own area instead of widening the page. A page without a trail (the home page, a 404) SHALL NOT show the breadcrumbs. The breadcrumb structured data SHALL be the one the base document provides.

#### Scenario: A record page
- **WHEN** a record page of a nested type is shown
- **THEN** the breadcrumbs appear between the main navigation and the content, with the current page last and not a link

#### Scenario: The home page
- **WHEN** the home page is shown
- **THEN** no breadcrumbs are shown

#### Scenario: A narrow screen
- **WHEN** a record page with a long trail is shown at 360 px width
- **THEN** the page has no horizontal scrolling

### Requirement: Section title as the list heading
The list template of the theme `dcculture` SHALL use the public title of the type (`$sectionTitle`) in the section heading of the list, not the administrative name of the type.

#### Scenario: A type with a section title
- **WHEN** the list of a type whose administrative name differs from its section title is shown
- **THEN** the section heading shows the section title
