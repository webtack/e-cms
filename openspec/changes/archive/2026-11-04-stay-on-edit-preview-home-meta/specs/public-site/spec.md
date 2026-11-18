# Spec Delta

## ADDED Requirements

### Requirement: Мета-дані сторінки: запис, поля типу, налаштування сайту
The `<head>` of every public page (the home page, a page, the list and the details of a record, 404) SHALL contain metadata, each value of which is taken separately from the first available source in this order: (1) the meta values of the record itself (`meta_title`, `meta_description`, `meta_image`); (2) the field chosen in the block «Мета-дані» ("Meta data") of the public settings of the type of the record (`settings.public.meta`); (3) the settings of the site. For the heading (`<title>`) the third source is the name of the site, and between the second and the third stands the `title` of the record by the rules of the document heading; for `meta description` — the description of the site; for `og:image` — the large logo of the site, and when there is none — the small one. When no source exists, the corresponding tag SHALL NOT be output (a heading without the name of the site is the name of the application). The home page and pages without a record have only the third source.

The value of the chosen field SHALL be taken as follows: for a multiple field — the first value; «заголовок» ("heading") — from a field of the type «рядок»; «опис» ("description") — from a field of the type «текст» or «форматований текст», where the HTML is stripped to the visible text, the spaces are collapsed, and the result is shortened to 160 characters (if needed at a word boundary, with «…» at the end); «зображення» ("image") — from a field of the type «зображення» (the file address) or «відео YouTube» (the address of the saved screenshot of the video; without a screenshot the field gives no value, and the next source is taken). An empty value of a field SHALL be considered absent.

#### Scenario: Meta data of the home page
- **WHEN** the name «Acme», the description «Best things» and a large logo are set in the settings of the site, and a guest opens `/`
- **THEN** `<title>` equals «Acme», the `<head>` contains `meta name="description"` with the value «Best things» and `og:image` with the address of the large logo

#### Scenario: A page without its own metadata and without chosen fields
- **WHEN** a page has no `meta_description` and `meta_image`, the type has no `settings.public.meta`, and the settings of the site have a description and a logo
- **THEN** the `<head>` of the page contains the description of the site and `og:image` with the logo of the site

#### Scenario: Own metadata of a record has priority
- **WHEN** a record has `meta_description` «About us», the type chooses the field `summary` for the description, and the settings of the site have a description
- **THEN** `meta description` equals «About us»

#### Scenario: Chosen field instead of the settings of the site
- **WHEN** a record has no `meta_*`, the type chooses `summary` for the description, `cover` for the image and `headline` for the heading
- **THEN** `meta description` contains the text of `summary`, `og:image` is the address `cover`, `<title>` is the value of `headline` and the name of the site

#### Scenario: Multiple field
- **WHEN** the chosen field «зображення» is multiple and has three files
- **THEN** `og:image` contains the address of the first file

#### Scenario: HTML in the description
- **WHEN** the chosen field «форматований текст» contains `<p>Hello <strong>world</strong></p>`
- **THEN** `meta description` equals «Hello world», without tags

#### Scenario: Long description
- **WHEN** the text of the chosen field is longer than 160 characters
- **THEN** `meta description` has no more than 160 characters and ends with «…»

#### Scenario: Screenshot of a video
- **WHEN** the field «відео YouTube» is chosen for the image, and the record has a video with a screenshot
- **THEN** `og:image` contains the address of the screenshot

#### Scenario: Video without a screenshot
- **WHEN** the record has a video without a screenshot, and the settings of the site have a logo
- **THEN** `og:image` contains the address of the logo of the site

#### Scenario: Empty settings
- **WHEN** the description and the logo of the site are not set, and the record has no `meta_*` and no values of the chosen fields
- **THEN** the `<head>` contains no `meta description` and no `og:image`
