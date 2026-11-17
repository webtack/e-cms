# Spec Delta

## ADDED Requirements

### Requirement: Breadcrumbs in the default theme
The theme `default` SHALL show the trail (`$breadcrumbs`) as a navigation landmark with an ordered list, above the page content, with the current page as the last item marked as the current page for assistive technology and every other item a link. The navigation SHALL have an accessible name. The markup SHALL live in its own template that another theme can replace without touching the layout, and SHALL carry classes a theme can style (`breadcrumbs`, `breadcrumbs__item`). When the trail is empty (the home page, a 404), nothing SHALL be output.

#### Scenario: A record page
- **WHEN** a record page of a nested type is shown
- **THEN** the page has a navigation with a list of the trail items, where the last one is not a link and is marked as the current page

#### Scenario: The home page
- **WHEN** the home page is shown
- **THEN** no breadcrumbs navigation is output

### Requirement: Breadcrumb structured data in the document head
The base document template of the theme `default` SHALL print the structured data (`$breadcrumbsJsonLd`) in the `<head>` as a `<script type="application/ld+json">` element when it is not `null`, and print nothing otherwise. A theme that replaces only its breadcrumbs template SHALL keep the structured data.

#### Scenario: A record page
- **WHEN** a record page is shown
- **THEN** the `<head>` contains one `application/ld+json` script with a `BreadcrumbList`

#### Scenario: The home page
- **WHEN** the home page is shown
- **THEN** the `<head>` has no `application/ld+json` script

### Requirement: Active menu states in the default theme
The menu templates of the theme `default` SHALL mark the active item (`is_active`) with the class `is-active` and `aria-current="page"` on its link, and each item in the trail (`is_trail`) with the class `is-trail`, and SHALL style both states so that they are visibly different from an ordinary item and from each other. The classes SHALL be added to the existing hook classes, not in place of them.

#### Scenario: The active item
- **WHEN** the page of a menu item is shown
- **THEN** its link has `aria-current="page"` and the class `is-active`, and its parent items have the class `is-trail`

### Requirement: Section title as the list heading
The list template of the theme `default` SHALL use the public title of the type (`$sectionTitle`) as the page heading, not the administrative name of the type.

#### Scenario: A type with a section title
- **WHEN** the list of a type whose administrative name differs from its section title is shown
- **THEN** the heading is the section title
