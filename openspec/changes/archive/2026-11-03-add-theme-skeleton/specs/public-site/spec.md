# Spec Delta

## Purpose

The public site: the routes of the home page, pages, lists and details of content, the visibility of records, the response 404 and the shell of the page into which the theme inserts the content.

## ADDED Requirements

### Requirement: Головна сторінка
`GET /` SHALL render the template `home` inside the shell of the site (`html` → `layout`) with the status 200.

#### Scenario: Opening the home page
- **WHEN** a guest opens `/`
- **THEN** the response 200 contains the content of the template `home` in `layout`

### Requirement: Сторінка за slug
`GET /{slug}` SHALL render the record of the kind `page` with this slug, if the record is visible (active, published, not withdrawn from publication). For an invisible, non-existent or record of another kind, the response SHALL be 404.

#### Scenario: Visible page
- **WHEN** an active published page with the slug `about` exists, and a guest opens `/about`
- **THEN** the response 200 displays this page

#### Scenario: Disabled page
- **WHEN** the page `about` has `is_active = false`
- **THEN** `/about` responds with 404

#### Scenario: Scheduled and expired page
- **WHEN** `published_at` of the page is in the future or `unpublished_at` is in the past
- **THEN** `/about` responds with 404

#### Scenario: Record not of the kind page
- **WHEN** a record of the kind `block` or `content` has the slug `about`
- **THEN** `/about` does not render this record (404, if there is no page or type with such a slug)

### Requirement: Список записів типу content
`GET /{type.slug}` SHALL render the list of visible records of the type of the kind `content` whose slug matches the first segment. An unknown slug of a type SHALL give 404.

#### Scenario: List of a type
- **WHEN** the type `news` of the kind `content` has the slug `news` and two visible records, and a guest opens `/news`
- **THEN** the response 200 contains both records and does not contain invisible ones

#### Scenario: Unknown type
- **WHEN** a guest opens `/unknown`, and there is neither a page nor a type with such a slug
- **THEN** the response is 404

### Requirement: Деталі запису типу content
`GET /{type.slug}/{node.slug}` SHALL render the visible record of this type by the slug of the record. A record of another type, an invisible one or a non-existent one SHALL give 404.

#### Scenario: Details of a visible record
- **WHEN** the type with the slug `news` has a visible record with the slug `hello`, and a guest opens `/news/hello`
- **THEN** the response 200 displays the record

#### Scenario: Record of another type
- **WHEN** the record `hello` belongs to the type with the slug `events`, and a guest opens `/news/hello`
- **THEN** the response is 404

#### Scenario: Invisible record
- **WHEN** the record `hello` is disabled
- **THEN** `/news/hello` responds with 404

### Requirement: Службові шляхи мають пріоритет
The public routes SHALL NOT overlap the service ones: `/admin/**`, `/login`, `/logout`, `/locale`, `/email/**`, `/up`, `/storage/**`, `/build/**` SHALL work as before, and not be treated as the slug of a page or a type.

#### Scenario: Login and the admin
- **WHEN** a guest opens `/login`, and an authenticated user opens `/admin/dashboard`
- **THEN** the pages of the login and of the admin are answered, and not the 404 of the site

#### Scenario: Service path is not taken as a page
- **WHEN** the DB has (bypassing the validation) a page with the slug `login`
- **THEN** `/login` remains the login page

### Requirement: Сторінка 404
For any public path that does not match a record or a type, and also for invisible records, the system SHALL render the template `errors/404` inside the shell of the site with the status 404. The other HTTP errors of the admin do not change.

#### Scenario: Unknown path
- **WHEN** a guest opens `/no/such/page/at/all`
- **THEN** the response has the status 404 and the content of the template `errors/404` in `layout`

#### Scenario: Error in the admin
- **WHEN** an authenticated user opens a non-existent `/admin/unknown`
- **THEN** the behaviour has not changed relative to before (the standard response, not the template of the site)

### Requirement: Оболонка сторінки й дані шаблонів
Each public response SHALL consist of the shell `html` (the document: the language, the heading, the resources of the theme) and `layout` (the header, the footer) with the content of the page inside. The templates SHALL receive at least the settings of the site (the name, the logos, the contacts), the current record or type (where applicable) and the name of the active theme. The heading of the document SHALL be built from the `title` of the record and the name of the site.

#### Scenario: Heading of the document
- **WHEN** a visible page with the `title` «Про нас» is opened on a site with the name «Acme»
- **THEN** the `<title>` of the document contains «Про нас» and «Acme»

#### Scenario: Data of the site in the template
- **WHEN** the template `layout` outputs the name of the site
- **THEN** the name from the settings of the site is displayed

### Requirement: Блоки не маршрутизуються
The records of the kind `block` SHALL NOT have their own public addresses. Their placement on a page SHALL happen only by an explicit call in a template.

#### Scenario: Direct access to a block
- **WHEN** a block with the slug `block-footer` exists, and a guest opens `/block-footer`
- **THEN** the response is 404

### Requirement: Публічні дані без Inertia
The public responses SHALL be ordinary HTML from Blade templates, without loading the application of the admin (React/Inertia). The global middleware of Inertia SHALL NOT break the public responses.

#### Scenario: Response without Inertia
- **WHEN** a guest opens `/`
- **THEN** the HTML does not contain the root element of Inertia `<div id="app" data-page=...>`
