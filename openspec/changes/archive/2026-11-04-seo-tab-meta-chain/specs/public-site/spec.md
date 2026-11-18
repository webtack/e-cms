# Spec Delta

## MODIFIED Requirements

### Requirement: Мета-дані сторінки: запис, поля типу, налаштування сайту
The `<head>` of every public page (the home page, a page, the list and the details of a record, 404) SHALL contain metadata, each value of which is taken separately from the first available source in this order: (1) the meta values of the record itself (`meta_title`, `meta_description`, `meta_image`); (2) the field chosen in the block «Мета-дані» ("Meta data") of the tab «SEO» of the record's type (`settings.public.meta`); (3) the meta values of the type itself, written on its tab «SEO» (`meta_title`, `meta_description`, `meta_image` of the type); (4) the settings of the site. For the heading (`<title>`), between the second and the third source stands the `title` of the record, so `meta_title` of the type takes effect only where the record has no `title`; the fourth source is the name of the site. The value of the heading SHALL be output as is, without appending the name of the site (it is added, if needed, by the hooks or the templates of the theme); for `meta description` — the description of the site; for `og:image` — the large logo of the site, and when there is none — the small one. When no source exists, the corresponding tag SHALL NOT be output (a heading without the name of the site is the name of the application). The list page of records of a type has no record and takes only the third and the fourth sources (for the heading — `meta_title` of the type, then the name of the site); the home page and 404 — only the fourth.

The value of the chosen field SHALL be taken as follows: for a multiple field — the first value; «заголовок» ("heading") — from a field of the type «рядок»; «опис» ("description") — from a field of the type «текст» or «форматований текст», where the HTML is stripped to the visible text, the spaces are collapsed, and the result is shortened to 160 characters (if needed at a word boundary, with «…» at the end); «зображення» ("image") — from a field of the type «зображення» (the file address) or «відео YouTube» (the address of the saved screenshot of the video; without a screenshot the field gives no value, and the next source is taken). The meta value of the type SHALL be taken from the base language by the same cleaning and shortening rules as the chosen field of the same value (the description of the type is shortened to 160 characters). An empty value SHALL be considered absent.

#### Scenario: Meta data of the home page
- **WHEN** the name «Acme», the description «Best things» and a large logo are set in the settings of the site, and a guest opens `/`
- **THEN** `<title>` equals «Acme», the `<head>` contains `meta name="description"` with the value «Best things» and `og:image` with the address of the large logo

#### Scenario: A page without its own metadata and without chosen fields
- **WHEN** a page has no `meta_description` and `meta_image`, the type has no `settings.public.meta` and no own `meta_*`, and the settings of the site have a description and a logo
- **THEN** the `<head>` of the page contains the description of the site and `og:image` with the logo of the site

#### Scenario: Own metadata of a record has priority
- **WHEN** a record has `meta_description` «About us», the type chooses the field `summary` for the description, has its own `meta_description`, and the settings of the site have a description
- **THEN** `meta description` equals «About us»

#### Scenario: Chosen field instead of the metadata of the type
- **WHEN** a record has no `meta_*`, the type chooses `summary` for the description and has its own `meta_description` «Type text»
- **THEN** `meta description` contains the text of `summary`

#### Scenario: Metadata of the type instead of the site settings
- **WHEN** a record has no `meta_*`, the type chooses no fields but has `meta_description` «Type text» and `meta_image`, and the site has a description and a logo
- **THEN** `meta description` equals «Type text», and `og:image` is the address of `meta_image` of the type

#### Scenario: Metadata of the type on the list page
- **WHEN** a guest opens the list page of records of a type that has `meta_title` «News» and `meta_description` «All news»
- **THEN** `<title>` is «News», `meta description` is «All news»

#### Scenario: Page of the type `page` with metadata of the type
- **WHEN** a record of the type `page` has no `meta_description`, and its type has `meta_description` «Pages»
- **THEN** `meta description` equals «Pages»

#### Scenario: The heading of a record has priority over the meta heading of the type
- **WHEN** a record without `meta_title` has `title` «About us», the type chooses no heading but has `meta_title` «Type title»
- **THEN** `<title>` is «About us», not «Type title»

#### Scenario: Heading without the suffix with the name of the site
- **WHEN** a record has `title` «About us», has no `meta_title`, and the settings of the site have the name «Acme»
- **THEN** `<title>` is «About us», without « — Acme»

#### Scenario: Chosen field instead of the settings of the site
- **WHEN** a record has no `meta_*`, the type chooses `summary` for the description, `cover` for the image and `headline` for the heading, and the type has no own `meta_*`
- **THEN** `meta description` contains the text of `summary`, `og:image` is the address `cover`, `<title>` is the value of `headline`

#### Scenario: Multiple field
- **WHEN** the chosen field «зображення» is multiple and has three files
- **THEN** `og:image` contains the address of the first file

#### Scenario: HTML in the description
- **WHEN** the chosen field «форматований текст» contains `<p>Hello <strong>world</strong></p>`
- **THEN** `meta description` equals «Hello world», without tags

#### Scenario: Long description
- **WHEN** the text of the chosen field or `meta_description` of the type is longer than 160 characters
- **THEN** `meta description` has no more than 160 characters and ends with «…»

#### Scenario: Screenshot of a video
- **WHEN** the field «відео YouTube» is chosen for the image, and the record has a video with a screenshot
- **THEN** `og:image` contains the address of the screenshot

#### Scenario: Video without a screenshot
- **WHEN** the record has a video without a screenshot, the type has no `meta_image`, and the settings of the site have a logo
- **THEN** `og:image` contains the address of the logo of the site

#### Scenario: Empty settings
- **WHEN** the description and the logo of the site are not set, and the record and the type have no `meta_*` and no values of the chosen fields
- **THEN** the `<head>` contains no `meta description` and no `og:image`
