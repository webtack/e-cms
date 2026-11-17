# Spec Delta

## MODIFIED Requirements

### Requirement: Page meta data: node, type fields, site settings
The `<head>` of every public page (home, page, list and node details, 404) SHALL contain meta data, each value of which is taken separately from the first available source in the order: (1) the meta values of the node itself (`meta_title`, `meta_description`, `meta_image`); (2) the field selected in the "Meta data" block of the "SEO" tab of the node's type (`settings.public.meta`); (3) the meta values of the type itself, recorded on its "SEO" tab (`meta_title`, `meta_description`, `meta_image` of the type); (4) the site settings. For the title (`<title>`) the node's `title` stands between the second and the third source, so the type's own title applies only where the node's `title` is absent, and the fourth source is the site name. The type's own title SHALL be its `meta_title`, and when the type has no `meta_title`, its `section_title` (the "Section title" of the "Display" tab of a `content` type), taken in the reading language and then in the base language, as the public title of the type is; the administrative `name` of the type SHALL NOT be used. The value of the title SHALL be output as is, without appending the site name (it is added by theme hooks or templates if needed); for `meta description` — the site description; for `og:image` — the large site logo, and if it is absent — the small one. When there is no source, the corresponding tag SHALL NOT be output (a title without the site name — the application name). The list page of a type has no node and takes only the third and the fourth sources (for the title — the type's `meta_title`, then its `section_title`, then the site name); the home page and 404 — only the fourth.

The value of a selected field SHALL be taken as follows: for a multiple field — the first value; "title" — from a `string` type field; "description" — from a `text` or `richtext` field, where the HTML is cut to the visible text, spaces are compressed, and the result is shortened to 160 characters (if needed at a word boundary, with "…" at the end); "image" — from an `image` type field (the file address) or a `youtube` video (the address of the saved video screenshot; without a screenshot the field gives no value, and the next source is taken). The meta value of the type SHALL be taken from the base language by the same cleaning and shortening rules as the selected field of the same value (the type description is shortened to 160 characters); the `meta_title` of the type is taken from the base language, while its `section_title` fallback follows the reading language. An empty value SHALL be considered absent.

#### Scenario: Meta data of the home page
- **WHEN** the site name "Acme", the description "Best things", and a large logo are set in the site settings, and a guest opens `/`
- **THEN** the `<title>` equals "Acme", the `<head>` contains `meta name="description"` with the value "Best things", and `og:image` with the address of the large logo

#### Scenario: A page without its own meta data and without selected fields
- **WHEN** the page has no `meta_description` and `meta_image`, the type has no `settings.public.meta` and its own `meta_*`, and the site settings have a description and a logo
- **THEN** the `<head>` of the page contains the site description and `og:image` with the site logo

#### Scenario: The node's own meta data have priority
- **WHEN** the node has `meta_description` "About us", the type selects the field `summary` for the description, has its own `meta_description`, and the site settings have a description
- **THEN** `meta description` equals "About us"

#### Scenario: A type field instead of the type meta data
- **WHEN** the node has no `meta_*`, the type selects `summary` for the description and has its own `meta_description` "Type text"
- **THEN** `meta description` contains the text of `summary`

#### Scenario: Type meta data instead of the site settings
- **WHEN** the node has no `meta_*`, the type selects no fields but has `meta_description` "Type text" and `meta_image`, and the site has a description and a logo
- **THEN** `meta description` equals "Type text", and `og:image` — the address of the type's `meta_image`

#### Scenario: Section title as the type's meta title
- **WHEN** a guest opens the list page of a `content` type that has `section_title` "Latest news" and no `meta_title`
- **THEN** `<title>` is "Latest news"

#### Scenario: Meta title is more important than the section title
- **WHEN** a guest opens the list page of a `content` type that has `meta_title` "News | Acme" and `section_title` "Latest news"
- **THEN** `<title>` is "News | Acme"

#### Scenario: Section title in the reading language
- **WHEN** a guest opens the list page in a language in which the type has `section_title` "Новини" (the base language value is "News") and the type has no `meta_title`
- **THEN** `<title>` is "Новини"

#### Scenario: Section title in the base language
- **WHEN** the type has no `meta_title` and no `section_title` in the reading language, but has `section_title` "News" in the base language
- **THEN** `<title>` is "News"

#### Scenario: No titles on the type
- **WHEN** a `content` type has neither `meta_title` nor `section_title` and the site name is "Acme"
- **THEN** `<title>` is "Acme", not the administrative name of the type

#### Scenario: The node's title is more important than the type's section title
- **WHEN** a node without `meta_title` has the `title` "About us", the type selects no title, has no `meta_title`, but has `section_title` "Latest news"
- **THEN** `<title>` is "About us"

#### Scenario: Type meta data on the list page
- **WHEN** a guest opens the list page of nodes of a type that has `meta_title` "News" and `meta_description` "All news"
- **THEN** `<title>` is "News", `meta description` is "All news"

#### Scenario: Page of type `page` with type meta data
- **WHEN** a node of type `page` has no `meta_description`, and its type has `meta_description` "Pages"
- **THEN** `meta description` equals "Pages"

#### Scenario: The node's title is more important than the type's meta title
- **WHEN** a node without `meta_title` has the `title` "About us", the type selects no title, but has `meta_title` "Type title"
- **THEN** `<title>` is "About us", not "Type title"

#### Scenario: Title without the site name suffix
- **WHEN** a node has the `title` "About us", has no `meta_title`, and the site settings have the name "Acme"
- **THEN** `<title>` is "About us", without " — Acme"

#### Scenario: A type field instead of the site settings
- **WHEN** a node has no `meta_*`, the type selects `summary` for the description, `cover` for the image, and `headline` for the title, and the type has no own `meta_*`
- **THEN** `meta description` contains the text of `summary`, `og:image` — the address of `cover`, `<title>` — the value of `headline`

#### Scenario: Multiple field
- **WHEN** the selected "image" field is multiple and has three files
- **THEN** `og:image` contains the address of the first file

#### Scenario: HTML in the description
- **WHEN** the selected "formatted text" field contains `<p>Hello <strong>world</strong></p>`
- **THEN** `meta description` equals "Hello world", without tags

#### Scenario: Long description
- **WHEN** the text of the selected field or the type's `meta_description` is longer than 160 characters
- **THEN** `meta description` has no more than 160 characters and ends with "…"

#### Scenario: Video screenshot
- **WHEN** the "YouTube video" field is selected for the image, and the node has a video with a screenshot
- **THEN** `og:image` contains the address of the screenshot

#### Scenario: Video without a screenshot
- **WHEN** the node has a video without a screenshot, the type has no `meta_image`, and the site settings have a logo
- **THEN** `og:image` contains the address of the site logo

#### Scenario: Empty settings
- **WHEN** the site description and logo are not set, and the node and the type have no `meta_*` and no values of the selected fields
- **THEN** the `<head>` contains neither `meta description` nor `og:image`
