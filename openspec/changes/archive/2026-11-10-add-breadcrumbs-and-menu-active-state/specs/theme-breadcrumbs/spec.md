# Spec Delta

## Purpose

Gives every public page a breadcrumb trail built from the content-type hierarchy, hands it to theme templates as ready data, and publishes the same trail as `BreadcrumbList` structured data for search engines.

## ADDED Requirements

### Requirement: Trail of a record
For the details page of a `content` record the trail SHALL be, in order: the home page, then every ancestor type of the record's type from the root down that has a public address (a type without a slug is skipped), then the record's type, then the record. An ancestor and the record's type SHALL link to the public list address of the type (its slug as an absolute path); the record is the current page and SHALL NOT link. The title of a type item SHALL be the public title of the type (the `section_title` value in the reading language, with the fallbacks of the `public-site` capability), not its administrative name; the title of the record item SHALL be the record's `title` value, and its slug when the record has no title.

#### Scenario: A record of a nested type
- **WHEN** the type `institutions` (slug `common/institutions`) has the parent type `common` (slug `common`) and the page `/common/institutions/hello` is shown
- **THEN** the trail is the home page, `common` linking to `/common`, `institutions` linking to `/common/institutions`, and `hello` without a link

#### Scenario: Types are titled with their section title
- **WHEN** the type `news` has the administrative name `News (admin)` and the `section_title` value `Новини`
- **THEN** the trail item of the type is `Новини`

#### Scenario: A parent type without a slug
- **WHEN** the type of a record has a parent type that has no slug
- **THEN** that parent is not part of the trail and the rest of the trail is unchanged

#### Scenario: A record without a title
- **WHEN** a record has no `title` value
- **THEN** its trail item uses the record's slug as the title

### Requirement: Trail of a list
For the list page of a `content` type the trail SHALL be the home page, the ancestor types with a public address as for a record, and the type itself as the current page without a link. Paging a list (`?page=2`) SHALL NOT change the trail.

#### Scenario: A list of a root type
- **WHEN** `/news` shows the list of the root type `news`
- **THEN** the trail is the home page and `news` without a link

### Requirement: Trail of a page
For a page (`kind = page`) the trail SHALL always be the home page and the page itself as the current page without a link, with the page's `title` value (its slug when there is no title). The menus SHALL NOT take part in building the trail.

#### Scenario: A page that is in a menu
- **WHEN** `/about` is shown and the menu `main` contains it under a parent item
- **THEN** the trail is the home page and `About`, without the parent item

### Requirement: Pages without a trail
The home page, the 404 page, and any page that is not a record, a type list, or a page SHALL have an empty trail.

#### Scenario: The home page
- **WHEN** `/` is shown
- **THEN** the trail is empty

#### Scenario: A missing page
- **WHEN** an address that does not exist is requested
- **THEN** the 404 page is shown with an empty trail

### Requirement: Addresses and the home item
Every item of the trail except the current page SHALL have an absolute URL (scheme and host of the request, then the path). The current page SHALL have no URL. The home item SHALL link to `/` and its title SHALL be the translation of "Home" in the current interface language.

#### Scenario: Absolute addresses
- **WHEN** a trail is built on the host `https://example.org`
- **THEN** its items link to `https://example.org/` and `https://example.org/news`, and the last item has no address

#### Scenario: The home title follows the language
- **WHEN** the interface language is Ukrainian
- **THEN** the title of the home item is the Ukrainian translation of "Home"

### Requirement: Trail available to templates
Every public template SHALL receive the trail as the variable `$breadcrumbs` — a list of items with a `title` and a `url` (`null` for the current page), empty when the page has no trail — and the structured data as the variable `$breadcrumbsJsonLd` — a ready string, or `null` when the trail has fewer than two items. Both variables SHALL be available to the document shell, the layout, and every partial they include, without a special call.

#### Scenario: A template reads the trail
- **WHEN** a template of a record page prints `$breadcrumbs`
- **THEN** it receives the items of the record's trail

#### Scenario: The document shell reads the structured data
- **WHEN** the document shell of a theme prints `$breadcrumbsJsonLd` for a record page
- **THEN** it receives a non-empty string

### Requirement: Trail hook
The system SHALL run the `preprocess` hook `breadcrumbs` before the trail is exposed, so that a theme can add, remove, retitle, or clear items. `$breadcrumbs` and `$breadcrumbsJsonLd` SHALL both be derived from the list as the hook leaves it.

#### Scenario: A theme clears the trail
- **WHEN** the hook of the active theme empties the list
- **THEN** `$breadcrumbs` is empty and `$breadcrumbsJsonLd` is `null`

#### Scenario: A theme retitles an item
- **WHEN** the hook changes the title of an item
- **THEN** the new title is in both `$breadcrumbs` and the structured data

### Requirement: Breadcrumb structured data
`$breadcrumbsJsonLd` SHALL be one JSON document of type `BreadcrumbList` in the `https://schema.org` context. Each trail item SHALL be a `ListItem` with its one-based `position` and its `name`; every item except the last SHALL have `item` with its absolute URL, and the last item SHALL have no `item`. The text SHALL be safe to print inside a `<script type="application/ld+json">` element: no title, whatever it contains, SHALL be able to end the element or inject markup, and non-ASCII characters and slashes SHALL stay readable (not `\uXXXX` escapes).

#### Scenario: The structure of a record trail
- **WHEN** the trail is the home page, `news`, and the record `Hello`
- **THEN** the document lists three `ListItem` elements at positions 1, 2, and 3, the first two with `item`, and the third with only its `name`

#### Scenario: A title that tries to close the script
- **WHEN** a record's title contains `</script><script>alert(1)</script>`
- **THEN** the structured data string contains no literal `</script>`

#### Scenario: A short trail
- **WHEN** the trail has fewer than two items
- **THEN** there is no structured data
