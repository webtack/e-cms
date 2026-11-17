# type-sitemap Specification

## Purpose
Gives every `content` and `page` type its own sitemap settings and serves two sitemaps built from them: an XML one for search engines and a human-readable one for visitors, so editors decide what the site shows in each.

## Requirements

### Requirement: Sitemap settings of a type
A type of kind `content` or `page` SHALL be able to hold sitemap settings in `settings.sitemap`, with two groups. The group `xml` SHALL have the booleans `type` (the public list page of the type, `/{type slug}`) and `nodes` (the visible records of the type; for a `page` type, its visible pages), plus `changefreq` and `priority`. The group `web` SHALL have the booleans `type` and `nodes`. A type of kind `page` SHALL NOT have `type` in either group (it has no list page); a type of kind `block` SHALL NOT have `settings.sitemap` at all (blocks have no public address). `changefreq` SHALL be one of `always`, `hourly`, `daily`, `weekly`, `monthly`, `yearly`, `never`; `priority` SHALL be a decimal from `0.0` to `1.0` with one digit after the point (`0.0`, `0.1`, …, `1.0`). `changefreq` and `priority` SHALL be stored only while `xml.type` or `xml.nodes` is on, and when stored they default to `weekly` and `0.5`. A type without `settings.sitemap`, or with every checkbox off, SHALL NOT appear in either sitemap: there are no defaults that put a type into a map. Other keys of `settings` SHALL NOT be touched when the sitemap settings change. A value outside these rules SHALL be rejected with a validation error and nothing SHALL be saved.

#### Scenario: Stored settings
- **WHEN** an administrator turns on "Content type" and "Content" in the XML group with `monthly` and `0.8`, turns on only "Content type" in the web group, and saves
- **THEN** `settings.sitemap` holds `xml.type`, `xml.nodes`, `xml.changefreq = monthly`, `xml.priority = 0.8` and `web.type`, and the other keys of `settings` are unchanged

#### Scenario: Defaults for the frequency and the priority
- **WHEN** an administrator turns on only `xml.nodes` and does not touch the two selects
- **THEN** the stored values are `weekly` and `0.5`

#### Scenario: Frequency and priority are dropped with the checkboxes
- **WHEN** an administrator turns both XML checkboxes off and saves
- **THEN** the `xml` group no longer stores `changefreq` and `priority`

#### Scenario: Everything off
- **WHEN** an administrator turns every checkbox off and saves
- **THEN** the type has no `settings.sitemap` and appears in neither map

#### Scenario: Invalid frequency
- **WHEN** a request carries `changefreq = sometimes` or `priority = 1.5`
- **THEN** it fails validation and the stored settings do not change

#### Scenario: Page type has no list page
- **WHEN** a request for a `page` type carries `xml.type` or `web.type`
- **THEN** it fails validation

### Requirement: XML sitemap route
`GET /sitemap.xml` SHALL answer with status 200, the media type `application/xml` and the charset `utf-8`, and the body SHALL be a sitemap document of the sitemaps.org protocol (`urlset`, one `url` per address with `loc`, and `lastmod`, `changefreq`, `priority` where the rules below give them). The route SHALL take priority over `/{path}` and SHALL be available without signing in. `loc` SHALL be the absolute address on the host the request came to. The document SHALL NOT depend on the reading language: the public addresses are the same in every language.

#### Scenario: Opening the sitemap
- **WHEN** a guest opens `/sitemap.xml`
- **THEN** the response is 200 with `application/xml; charset=utf-8` and a well-formed `urlset`

#### Scenario: Empty configuration
- **WHEN** no type has sitemap settings and a guest opens `/sitemap.xml`
- **THEN** the document holds only the home page

### Requirement: Content of the XML sitemap
The first `url` SHALL be the home page (`/`, absolute) with `changefreq` `monthly` and `priority` `1.0` and without `lastmod`; it is always present and is not configurable. After it, for every type whose `xml.type` is on, there SHALL be a `url` for its list address `/{type slug}` of a `content` type, and for every type whose `xml.nodes` is on, one `url` per visible node of the type: `/{slug}` for a page and `/{type slug}/{node slug}` for a record. A node is visible when it is active, its publication date has come and it is not unpublished; hidden, scheduled and expired nodes SHALL NOT be listed. A `content` type without a slug has no public address, so neither its list nor its records SHALL be listed. Every `url` of a type SHALL carry that type's `changefreq` and `priority`. `lastmod` SHALL be the node's last change date in the W3C datetime format with the time-zone offset; for a list address it SHALL be the latest change date of the visible nodes of the type, and it SHALL be omitted when the type has no visible node. The types SHALL be ordered by their key; within a type the list address comes first and then the nodes from the latest changed to the oldest. An address that would appear twice SHALL be listed once. The archive view of a list (`?period=archive`) SHALL NOT be listed. All visible nodes SHALL be listed: there is no per-type limit.

#### Scenario: List and records
- **WHEN** the type `news` (slug `news`) has `xml.type` and `xml.nodes` on with `weekly` and `0.5`, and has the visible records `a` and `b`
- **THEN** the document holds `/news`, `/news/a` and `/news/b` (each with `weekly` and `0.5`), after the home page

#### Scenario: Only the list
- **WHEN** the type `events` has only `xml.type` on
- **THEN** the document holds `/events` and none of its records

#### Scenario: Visible nodes only
- **WHEN** a record of an `xml.nodes` type is switched off, scheduled for the future or already unpublished
- **THEN** its address is not in the document

#### Scenario: Pages
- **WHEN** the page type `about` has `xml.nodes` on and a visible page with the slug `contacts`
- **THEN** the document holds `/contacts`

#### Scenario: Type without a slug
- **WHEN** a `content` type has no slug and `xml.type` and `xml.nodes` on
- **THEN** none of its addresses is in the document

#### Scenario: Not configured
- **WHEN** a type has no sitemap settings or only web settings
- **THEN** none of its addresses is in the XML document

#### Scenario: Last change dates
- **WHEN** a record was last changed on 2026-11-13 at 23:37:48 +02:00
- **THEN** its `lastmod` is `2026-11-13T23:37:48+02:00`, and the `lastmod` of the list of its type is the latest such date among the visible records of the type

#### Scenario: More records than the legacy limit
- **WHEN** a type with `xml.nodes` on has 120 visible records
- **THEN** all 120 addresses are in the document

### Requirement: Limit of the XML sitemap
The document SHALL NOT contain more than 50 000 `url` elements (the limit of the protocol). When the addresses exceed the limit, the document SHALL be cut at the limit (the home page and the earlier types are kept) and the cut SHALL be recorded in the application log as a warning; the response stays 200. A sitemap index for more addresses is not provided.

#### Scenario: Over the limit
- **WHEN** the settings select more than 50 000 addresses
- **THEN** the document holds exactly 50 000 `url` elements and the log has a warning about the cut

### Requirement: Web sitemap route
`GET /sitemap` SHALL answer with status 200 through the theme: the candidate template `sitemap/page` (hook `sitemap`) inside the site shell, with the document title "Sitemap" (translated). The route SHALL take priority over `/{path}` and SHALL be available without signing in. Only the exact address `/sitemap` is the page; `/sitemap/anything` SHALL give the 404 of the site. The page SHALL be public and indexable (it carries no `noindex`).

#### Scenario: Opening the page
- **WHEN** a guest opens `/sitemap`
- **THEN** the response 200 is the sitemap page in the theme shell with the title "Sitemap"

#### Scenario: Deeper address
- **WHEN** a guest opens `/sitemap/anything`
- **THEN** the response is the 404 of the site

### Requirement: Content and order of the web sitemap
The page SHALL show a tree of links. The first item SHALL be the home page (`/`, title "Home", translated). After it come, in this order: the visible pages of every `page` type whose `web.nodes` is on (one flat list, ordered by title), and then the tree of `content` types. A `content` type SHALL contribute its own item when `web.type` is on, with the public title of the type (the `section_title` of the reading language, then of the base language, then the administrative name) linking to `/{type slug}`; when `web.nodes` is on it SHALL list its visible records (linking to `/{type slug}/{node slug}`, ordered by title) as the children of that item, followed by its child types (ordered by their public title), each of which follows the same rules. When `web.type` is off, whatever the type contributes (its records and its child types' items) SHALL be shown at the level of its parent instead, so no enabled content is lost. A `content` type without a slug has no address, so it SHALL contribute neither its own item nor its records, but its child types SHALL still be processed. The titles of nodes SHALL be taken in the reading language, then in the base language, and when a node has no title, its slug SHALL be shown. Only visible nodes SHALL be listed. A type that is not in the web group (no `web` settings) SHALL contribute nothing of its own. Items SHALL be links to relative addresses. The archive view of a list SHALL NOT be linked.

#### Scenario: Type with records and a child type
- **WHEN** the type `common` (slug `common`, `web.type` on, `web.nodes` off) has the child `institutions` (slug `common/institutions`, `web.type` on, `web.nodes` on, two visible records)
- **THEN** the tree shows "Home", then "{title of common}" linking to `/common` with the child "{title of institutions}" linking to `/common/institutions`, which has the two records as its children

#### Scenario: Only records, no own item
- **WHEN** the type `news` has `web.nodes` on and `web.type` off and has visible records
- **THEN** the records are listed at the top level of the content types, without a "News" item

#### Scenario: Child shown when the parent is off
- **WHEN** a type has no web settings but its child type has `web.type` on
- **THEN** the child's item is shown at the level where the parent would be

#### Scenario: Pages
- **WHEN** the page type `about` has `web.nodes` on and visible pages "Contacts" and "About us"
- **THEN** after "Home" the page lists "About us" and "Contacts" in title order, linking to `/about-us` and `/contacts`

#### Scenario: Title in the reading language
- **WHEN** the type has `section_title` "Новини" in the reading language and "News" in the base language
- **THEN** the item is "Новини"; without the reading-language value it is "News"

#### Scenario: Hidden nodes
- **WHEN** a record is switched off or scheduled
- **THEN** it is not in the tree

#### Scenario: Nothing configured
- **WHEN** no type has web settings
- **THEN** the page shows only "Home"

### Requirement: Sitemap template data
The template `sitemap/page` SHALL receive, besides the shared variables of every public page, the tree as `items`: a list of items, each with `title` (plain text, to be escaped by the template), `url` (a relative address) and `children` (a list of items, empty when there are none). The `default` and `dcculture` themes SHALL provide the template; a theme that lacks it fails the way any public page without a template does.

#### Scenario: Data of the template
- **WHEN** the page is built
- **THEN** the template can walk `items` recursively and print each title and link

### Requirement: Sitemap reserved path
The first segment `sitemap` SHALL be a reserved path: no page slug and no `content` type slug whose first segment is `sitemap` can be saved, and `/sitemap` is never served as a page, a type list or a node address. `sitemap` SHALL belong to the pages of the site itself (like `search`), so `/sitemap/anything` gets the 404 of the site and not the standard one. `sitemap.xml` cannot be a slug (the slug format has no dot), so it needs no entry.

#### Scenario: Page slug sitemap is rejected
- **WHEN** an administrator saves a page with the slug `sitemap`
- **THEN** the slug is rejected as reserved

#### Scenario: Type slug starting with sitemap is rejected
- **WHEN** an administrator saves a `content` type with the slug `sitemap/archive`
- **THEN** the slug is rejected as reserved
