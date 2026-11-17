# public-site Specification

## Purpose

The public site: the routes of the home page, pages, content lists and details, node visibility, the 404 response, and the page shell into which the theme inserts the content.

## Requirements

### Requirement: Home page
`GET /` SHALL serve the `home` template inside the site shell (`html` → `layout`) with status 200.

#### Scenario: Opening the home page
- **WHEN** a guest opens `/`
- **THEN** the response 200 contains the content of the `home` template in `layout`

### Requirement: Page by slug
`GET /{slug}` SHALL serve a node of kind `page` with this slug, if the node is visible (active, published, not unpublished). For an invisible, non-existent, or a node of another kind the response SHALL be 404.

#### Scenario: Visible page
- **WHEN** an active published page with the slug `about` exists, and a guest opens `/about`
- **THEN** the response 200 displays this page

#### Scenario: Disabled page
- **WHEN** the page `about` has `is_active = false`
- **THEN** `/about` responds with 404

#### Scenario: Scheduled and expired page
- **WHEN** the `published_at` of the page is in the future or the `unpublished_at` is in the past
- **THEN** `/about` responds with 404

#### Scenario: Node not of kind page
- **WHEN** a node of kind `block` or `content` has the slug `about`
- **THEN** `/about` does not serve this node (404, if there is no page or type with such a slug)

### Requirement: List of nodes of a content type
`GET /{type.slug}` SHALL serve the list of visible nodes of a type of kind `content` whose slug equals the whole path of the address; the slug may have several segments (`/common/institutions`). An unknown type slug SHALL give 404.

#### Scenario: List of a type
- **WHEN** the type `news` of kind `content` has the slug `news` and two visible nodes, and a guest opens `/news`
- **THEN** the response 200 contains both nodes and does not contain the invisible ones

#### Scenario: List of a type with a slug of several segments
- **WHEN** the type has the slug `common/institutions` and two visible nodes, and a guest opens `/common/institutions`
- **THEN** the response 200 contains both nodes and does not contain the invisible ones

#### Scenario: Part of a nested slug is not a list
- **WHEN** the type has the slug `common/institutions`, no type has the slug `common`, and a guest opens `/common`
- **THEN** the response is 404

#### Scenario: Unknown type
- **WHEN** a guest opens `/unknown`, and there is neither a page nor a type with such a slug
- **THEN** the response is 404

### Requirement: Details of a node of the content type
`GET /{type.slug}/{node.slug}` SHALL serve the visible node of this type by the node's slug; the type slug may have several segments (`/common/institutions/hello`), and the last segment of the address is the node slug. A node of another type, an invisible one, or a non-existent one SHALL give 404.

#### Scenario: Details of a visible node
- **WHEN** the type with the slug `news` has a visible node with the slug `hello`, and a guest opens `/news/hello`
- **THEN** the response 200 displays the node

#### Scenario: Details of a node of a type with a slug of several segments
- **WHEN** the type with the slug `common/institutions` has a visible node with the slug `hello`, and a guest opens `/common/institutions/hello`
- **THEN** the response 200 displays the node

#### Scenario: Node of another type
- **WHEN** the node `hello` belongs to the type with the slug `events`, and a guest opens `/news/hello`
- **THEN** the response is 404

#### Scenario: Node of another type with a nested slug
- **WHEN** the node `hello` belongs to the type with the slug `common/institutions`, and a guest opens `/common/institutions-sources/hello`
- **THEN** the response is 404

#### Scenario: Invisible node
- **WHEN** the node `hello` is disabled
- **THEN** `/news/hello` responds with 404

### Requirement: Service paths have priority
The public routes SHALL NOT override the service ones: `/admin/**`, `/login`, `/logout`, `/locale`, `/email/**`, `/up`, `/storage/**`, `/build/**` SHALL work as before, and not be treated as the slug of a page or a type. The public routes SHALL be registered after every other route of the application, including routes added by the host, so a host route always wins over a CMS page of the same address.

#### Scenario: Login and the admin panel
- **WHEN** a guest opens `/login`, and an authenticated user opens `/admin/dashboard`
- **THEN** the login and admin pages respond, not the site's 404

#### Scenario: A service path is not taken as a page
- **WHEN** the DB (bypassing validation) has a page with the slug `login`
- **THEN** `/login` remains the login page

#### Scenario: Host route wins
- **WHEN** the host application defines `GET /contact` and a CMS page has the slug `contact`
- **THEN** `/contact` is answered by the host route

### Requirement: 404 page
For any public path that does not correspond to a node or a type, and also for invisible nodes, the system SHALL serve the template `errors/404` inside the site shell with status 404, when the public site is enabled (`ecms.public_site`). When the public site is disabled, the standard Laravel 404 SHALL be used. Other HTTP errors of the admin panel do not change.

#### Scenario: Unknown path
- **WHEN** a guest opens `/no/such/page/at/all`
- **THEN** the response has status 404 and the content of the template `errors/404` in `layout`

#### Scenario: Error in the admin panel
- **WHEN** an authenticated user opens a non-existent `/admin/unknown`
- **THEN** the behavior has not changed relative to the previous one (the standard response, not the site template)

#### Scenario: Public site disabled
- **WHEN** `ecms.public_site` is false and an unknown path is opened
- **THEN** the standard Laravel 404 is returned

### Requirement: Page shell and template data
Each public response SHALL consist of the shell `html` (the document: language, title, theme resources) and `layout` (the header, the footer) with the page content inside. The templates SHALL receive at least the site settings (name, logos, contacts), the current node or type (where applicable), and the name of the active theme. The title of the document SHALL be built from the `title` of the node and the site name.

#### Scenario: Document title
- **WHEN** a visible page with the `title` "About us" is opened on a site with the name "Acme"
- **THEN** the `<title>` of the document contains "About us" and "Acme"

#### Scenario: Site data in the template
- **WHEN** the `layout` template outputs the site name
- **THEN** the name from the site settings is displayed

### Requirement: Blocks are not routed
Nodes of kind `block` SHALL NOT have their own public addresses. Their placement on a page SHALL happen only by an explicit call in the template.

#### Scenario: Direct access to a block
- **WHEN** a block with the slug `block-footer` exists, and a guest opens `/block-footer`
- **THEN** the response is 404

### Requirement: Public data without Inertia
Public responses SHALL be regular HTML from Blade templates, without loading the admin application (React/Inertia). The global Inertia middleware SHALL NOT break public responses.

#### Scenario: Response without Inertia
- **WHEN** a guest opens `/`
- **THEN** the HTML does not contain the Inertia root element `<div id="app" data-page=...>`

### Requirement: Fields on a page and in node details
The page (`kind = page`) and the details of a `content` node SHALL output, after the heading, the fields listed in the public settings of the type (`page_fields`), in the configured order. If there is no setting, all added fields of the type SHALL be output. The system values (`title`, `meta_*`, `section_*`) SHALL NOT be output as regular fields, but `meta_title`, `meta_description`, and `meta_image` SHALL get into the `<head>` of the document when they are set. The fields SHALL be output by the theme's field templates.

#### Scenario: Configured fields of a page
- **WHEN** the page type has `page_fields = ['body']`, and the node has the fields `body` and `note`
- **THEN** the HTML contains the value of `body`, and `note` is absent

#### Scenario: Without a setting — all added fields
- **WHEN** the type has no `page_fields`
- **THEN** the page outputs all added fields that have values

#### Scenario: Document meta data
- **WHEN** the node has `meta_title` and `meta_description`
- **THEN** the `<title>` contains `meta_title`, and the `<head>` — the meta description

### Requirement: Node cards in the list
The list of nodes of the type `content` SHALL output each node as a card (preview) from the list of candidates `{t}/preview--{id}`, `{t}/preview`, `content/preview`, where `{id}` is the numeric `id` of the node. The card SHALL show the `title` as a link to the node and the fields from `list_fields`. The list SHALL use the sorting, the number of nodes per page, and the paginator from the public settings of the type; without settings — the newest first. Only visible nodes SHALL get into the list.

#### Scenario: Card fields
- **WHEN** the type has `list_fields = ['title', 'price']`
- **THEN** the card of each node shows the title-link and the price

#### Scenario: Overridden node card
- **WHEN** the theme has `news/preview--7`
- **THEN** the node with `id = 7` of the type `news` is output by this template, and the others — by `news/preview` or `content/preview`

#### Scenario: Paginator disabled
- **WHEN** `pagination = false`
- **THEN** the list does not contain links to pages

#### Scenario: Sorting
- **WHEN** the type has `sort = {field: 'title', direction: 'asc'}`
- **THEN** the nodes go by title in ascending order

### Requirement: Menu in the site header
The site header (`layout`) SHALL output the menu `main`, if it exists, on every public page, including 404. If the menu `main` does not exist, the header SHALL be output without a menu.

#### Scenario: The main menu exists
- **WHEN** the menu `main` exists with visible items
- **THEN** every public page contains these items in the header

#### Scenario: The main menu is absent
- **WHEN** the menu `main` has not been created
- **THEN** the pages are displayed without an error and without a menu

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

### Requirement: Counting views of public node pages
The public site SHALL increase the view counter (`views`) of a node by one when a successful (200) response to a `GET` request is built for the page of that node: the details page of a node of kind `content` (`/{type.slug}/{node.slug}`) or a node of kind `page` (`/{slug}`). It SHALL NOT count type lists, blocks (they have no address), 404 responses, invisible nodes, or requests with a method other than `GET` (including `HEAD` and `OPTIONS`). A request SHALL NOT be counted when its `User-Agent` header is empty or contains (case-insensitively) a substring from the configurable list `content.views.bot_agents`, or when counting is disabled by `content.views.enabled`. Requests of authenticated users of any role SHALL be counted by default and SHALL be excluded only when `content.views.count_authenticated` is false (env `CONTENT_VIEWS_COUNT_AUTHENTICATED`, default true). Repeated requests of the same visitor SHALL be counted by default; when `content.views.ttl` (env `CONTENT_VIEWS_TTL`, seconds, default `0`) is greater than zero, a visitor, identified by the node, the IP address, and the `User-Agent`, SHALL be counted for a node at most once per that period, the check SHALL be atomic so that concurrent requests do not pass twice, and no cookies SHALL be used. The increase SHALL be one atomic update that does not change `updated_at`, made after the response is built. A failure of the cache (when `ttl` is greater than zero) or of the update SHALL NOT change the response and SHALL only be logged.

#### Scenario: A visit to a content node
- **WHEN** a guest opens `/news/hello` of a visible node with `views` = 5
- **THEN** the response is 200, and `views` becomes 6

#### Scenario: A visit to a page
- **WHEN** a guest opens `/about` of a visible page
- **THEN** the `views` of that page increases by one

#### Scenario: Repeated visit of the same visitor
- **WHEN** `content.views.ttl` is `0` (the default) and the same visitor (the same IP and `User-Agent`) opens the same node twice
- **THEN** `views` increases by two

#### Scenario: Another visitor or another node
- **WHEN** another visitor opens the same node, or the same visitor opens another node
- **THEN** that node's `views` increases by one for each

#### Scenario: The period has passed
- **WHEN** `content.views.ttl` is greater than zero and the same visitor opens the node again after that period
- **THEN** `views` increases again

#### Scenario: A repeated visit within a configured period
- **WHEN** `content.views.ttl` is 86400 and the same visitor (the same IP and `User-Agent`) opens the same node twice within the period
- **THEN** `views` increases only once

#### Scenario: Authenticated users excluded by configuration
- **WHEN** `content.views.count_authenticated` is false and a signed-in user opens a visible node
- **THEN** `views` does not change

#### Scenario: Lists and blocks are not counted
- **WHEN** a guest opens `/news` (a type list)
- **THEN** no counter changes

#### Scenario: Not found and invisible nodes
- **WHEN** a guest opens the address of a disabled node, or an unknown address
- **THEN** the response is 404, and no counter changes

#### Scenario: A bot
- **WHEN** the `User-Agent` is `Googlebot/2.1`, or `curl/8.0`, or empty
- **THEN** the response is 200, and `views` does not change

#### Scenario: Not a GET request
- **WHEN** a `HEAD` request opens an existing node
- **THEN** `views` does not change

#### Scenario: An authenticated user
- **WHEN** a signed-in user (any role) opens a visible node
- **THEN** `views` increases by one

#### Scenario: Counting is disabled
- **WHEN** `content.views.enabled` is false and a guest opens a visible node
- **THEN** `views` does not change

#### Scenario: The update time is kept
- **WHEN** a view is counted
- **THEN** `updated_at` of the node does not change

#### Scenario: A failure does not break the page
- **WHEN** the cache store (with `ttl` greater than zero) or the counter update throws an error during a visit
- **THEN** the response is still 200 with the page, and the error is written to the log

### Requirement: Views in public templates
The node passed to the templates of the public site SHALL expose its counter as the integer attribute `views`, on the details page and on a page of kind `page`, and for the nodes of a type list (the cards). A page showing the counter SHALL show the value read before the current visit is counted, without a second query.

#### Scenario: A template reads views
- **WHEN** a theme template of `content/details` prints `node.views` for a node with 12 views
- **THEN** the value 12 is available as an integer

#### Scenario: Views in list cards
- **WHEN** a list template reads the `views` of the nodes of the paginator
- **THEN** each node has its own integer value

### Requirement: Public sorting by views
The public display settings of a type SHALL accept `views` as the sort field of the public list (`settings.public.sort.field`), as with the other service attributes, so that a type can show the most viewed nodes first.

#### Scenario: Popular nodes first
- **WHEN** a type has `settings.public.sort` = `{field: "views", direction: "desc"}` and three visible nodes with 3, 30, and 12 views, and a guest opens the type list
- **THEN** the nodes are shown in the order 30, 12, 3

#### Scenario: Saving the setting
- **WHEN** the display settings of a type are saved with the sort field `views`
- **THEN** the saving succeeds

### Requirement: Resolution of an address of any depth
The public site SHALL resolve an address of one or more segments in this order: (1) with one segment, a visible node of kind `page` with this slug; (2) a type of kind `content` whose slug equals the whole address, shown as a list; (3) with two or more segments, a type whose slug equals the address without its last segment, and its visible node with the last segment as the slug; (4) otherwise the 404 page. A reserved site path SHALL be checked by the first segment of the address, and the service routes SHALL keep priority over the public ones. A type list SHALL win over a node when the same address could mean both. Addresses with an empty segment or with characters outside the slug format SHALL give 404.

#### Scenario: A type list wins over a node
- **WHEN** the type with the slug `common` has a visible node `institutions` (created before the collision rules), and another type has the slug `common/institutions`
- **THEN** `/common/institutions` shows the list of the type `common/institutions`

#### Scenario: A page with one segment
- **WHEN** a visible page has the slug `about`, and a guest opens `/about`
- **THEN** the response 200 displays this page

#### Scenario: No page for several segments
- **WHEN** a visible page has the slug `about`, and a guest opens `/about/team`
- **THEN** the response is 404, unless a type has the slug `about` with a visible node `team`

#### Scenario: Reserved first segment
- **WHEN** a guest opens `/admin/something` or `/storage/something-unknown`
- **THEN** the public site does not serve it (the service route or the standard response applies)

#### Scenario: Deep unknown address
- **WHEN** a guest opens `/no/such/page/at/all`
- **THEN** the response is the 404 page of the site

#### Scenario: Invalid segment
- **WHEN** a guest opens `/news//hello` or `/News_1/hello`
- **THEN** the response is the 404 page of the site

### Requirement: Public addresses accept legacy node slugs
The public site SHALL serve a visible node whose slug is in the legacy format (lowercase letters, digits, hyphens including repeated, leading or trailing ones, and underscores) at the same address as any other node, so that the addresses of the legacy site keep working after the import. The format of the part of an address SHALL still exclude uppercase letters, spaces, empty parts and any other characters, which give the 404 page. The slug of a type is not affected: it keeps its own format.

#### Scenario: Node with repeated hyphens
- **WHEN** the type with the slug `news` has a visible node with the slug `volnovaha---misto-geroy-ukrayini`, and a guest opens `/news/volnovaha---misto-geroy-ukrayini`
- **THEN** the response 200 displays the node

#### Scenario: Node with an underscore and a trailing hyphen
- **WHEN** visible nodes have the slugs `etno_wave-poltava` and `jam-session-`, and a guest opens their addresses
- **THEN** the response 200 displays each node

#### Scenario: Unsupported characters
- **WHEN** a guest opens `/news/Hello` or `/news/he llo`
- **THEN** the response is the 404 page of the site

### Requirement: Public title of a type
The public title of a `content` type SHALL be its `section_title` value (the "Section title" entered on the type's "Display" tab) in the reading language; when the type has no value in that language, the value in the base language; when it has neither, the type's administrative name. An empty value SHALL count as absent. The title SHALL be a plain string, escaped by the template that prints it.

#### Scenario: A translated title
- **WHEN** the type has `section_title` in the reading language
- **THEN** the public title is that value

#### Scenario: A missing translation
- **WHEN** the type has no `section_title` in the reading language but has one in the base language
- **THEN** the public title is the base-language value

#### Scenario: No section title
- **WHEN** the type has no `section_title` in any language
- **THEN** the public title is the type's administrative name

### Requirement: Section title on the list page
The template of a type list page SHALL receive the public title of the type as the variable `$sectionTitle`, in addition to the existing variables.

#### Scenario: The list of a type with a section title
- **WHEN** `/news` shows the list of the type `news` that has the `section_title` `Новини`
- **THEN** the list template receives `$sectionTitle` equal to `Новини`

### Requirement: Archive period of the list of a content type
When a type of kind `content` has an archive in its public settings, `GET /{type.slug}` SHALL show only the visible nodes that are **not past** by the archive date, sorted by the main public sort, and `GET /{type.slug}?period=archive` SHALL show only the visible **past** nodes, sorted by the archive sort. "Past" SHALL be decided against the start of the current day in UTC, so a node stays in the main list during the whole day of its date. Both lists SHALL use the type's `per_page` and paginator, the page parameter SHALL work in both, and the links of the paginator SHALL keep `period=archive` in the archive list. A `period` value other than `archive` SHALL be treated as the main list, without an error. For a type without an archive the list SHALL stay as it is without the parameter, and `period=archive` SHALL be ignored (the same list is shown). The template SHALL receive which period is shown and whether the type has an archive, so that it can render a switch between the two lists.

#### Scenario: Main list hides the past
- **WHEN** a type `events` has `archive.field = date`, one visible node dated today and one dated last week, and a guest opens `/events`
- **THEN** the response 200 contains the node of today and does not contain the node of last week

#### Scenario: Archive list shows the past
- **WHEN** the same type, and a guest opens `/events?period=archive`
- **THEN** the response 200 contains the node of last week and does not contain the node of today

#### Scenario: A node of today stays
- **WHEN** a node has the `date` value of today 00:00 and the current time is 15:00
- **THEN** the node is in `/events` and not in `/events?period=archive`

#### Scenario: Order of the lists
- **WHEN** the main sort is `date` ascending and the archive sort is `date` descending, and there are several nodes in each period
- **THEN** `/events` lists the nearest date first, and `/events?period=archive` lists the most recent past date first

#### Scenario: Invisible nodes stay hidden
- **WHEN** a node is disabled or scheduled and its date fits the requested period
- **THEN** it is not shown in either list

#### Scenario: Paginator keeps the period
- **WHEN** the archive has more nodes than `per_page` and a guest opens `/events?period=archive`
- **THEN** the paginator links to the other pages contain `period=archive`

#### Scenario: Unknown period value
- **WHEN** a guest opens `/events?period=foo`
- **THEN** the response is the main list

#### Scenario: Type without an archive
- **WHEN** a type has no archive and a guest opens `/news?period=archive`
- **THEN** the response is the same list as `/news`, sorted by the public sort of the type

#### Scenario: Template data
- **WHEN** a guest opens the list of a type with an archive
- **THEN** the template can tell that the type has an archive and which period is shown

### Requirement: Site search route and reserved path
`GET /search` SHALL serve the site search page, with the search term in the query parameter `q` and the results page number in `page`. The first segment `search` SHALL be a reserved path (`content.reserved_paths`): no page slug and no `content` type slug whose first segment is `search` can be saved, and `/search` is never served as a page, a type list or a node address. The route SHALL take priority over `/{path}`. Only the exact address `/search` is the search page; `/search/anything` SHALL give the 404 of the site.

#### Scenario: Opening the search page
- **WHEN** a guest opens `/search?q=lamp`
- **THEN** the response 200 is the search page, not a page, type list or 404

#### Scenario: Page slug search is rejected
- **WHEN** an administrator saves a page with the slug `search`
- **THEN** the slug is rejected as reserved

#### Scenario: Type slug starting with search is rejected
- **WHEN** an administrator saves a `content` type with the slug `search/reports`
- **THEN** the slug is rejected as reserved

#### Scenario: Deeper address
- **WHEN** a guest opens `/search/lamp`
- **THEN** the response is the 404 of the site

### Requirement: Search scope and visibility
The search SHALL look through the nodes of kinds `page` and `content` and SHALL NOT return nodes of kind `block`. Only visible nodes (active, published, not unpublished) SHALL be returned. A `content` node whose type has no slug has no public address and SHALL NOT be returned. The search SHALL match the node's `title` and the values of its text fields (`string`, `text`, `richtext`) in every language of the node: the base language and every active additional language, not only the reading language of the request. A node SHALL be returned once however many of its languages match, and its title SHALL be shown in the reading language with fallback to the base language, while its excerpt SHALL be taken around the first match in the language in which the match was found. The match SHALL be a case-insensitive "contains" match of the whole term. Every result SHALL link to the public address of the node (`/{slug}` for a page, `/{type slug}/{node slug}` for a record).

#### Scenario: Match in the title
- **WHEN** a visible page has the title "Lamp museum" and a guest searches `lamp`
- **THEN** the page is in the results and links to its address

#### Scenario: Match in a text field of a record
- **WHEN** a visible record of the type with the slug `news` has the word `lamp` in a `richtext` field and a guest searches `lamp`
- **THEN** the record is in the results and links to `/news/{slug}`

#### Scenario: Invisible nodes are hidden
- **WHEN** a node that contains the term is disabled, scheduled, or expired
- **THEN** it is not in the results

#### Scenario: Blocks and nodes without an address
- **WHEN** a block, or a record of a type without a slug, contains the term
- **THEN** it is not in the results

#### Scenario: Reading language without fallback
- **WHEN** the reading language is not the base language and a node has the term only in its base-language value
- **THEN** the node is in the results, because the search is not limited to the reading language, and its title is shown in the reading language, falling back to the base language

#### Scenario: Match in another language
- **WHEN** a guest on the English site searches a word that occurs only in the Ukrainian text of a page
- **THEN** the page is in the results once

#### Scenario: Match in several languages
- **WHEN** the term occurs in the base and in the English value of one node
- **THEN** the node appears once in the results

### Requirement: Search term rules
The term SHALL be trimmed and cut to 100 characters. A term shorter than 3 characters, or an absent or empty `q`, SHALL show the search page with the form and no results, without querying nodes, and without an error status. The characters `%` and `_` in the term SHALL be matched literally.

#### Scenario: Too short term
- **WHEN** a guest opens `/search?q=ab`
- **THEN** the response 200 shows the form and a hint about the minimum length, and no results

#### Scenario: Empty term
- **WHEN** a guest opens `/search`
- **THEN** the response 200 shows the form only

#### Scenario: Wildcards are literal
- **WHEN** a guest searches `100%` and one visible node contains `100%` while another contains `1000`
- **THEN** only the first node is in the results

#### Scenario: Long term
- **WHEN** a guest searches a term of 150 characters
- **THEN** only its first 100 characters are used and the page is answered with status 200

### Requirement: Search results page
The results SHALL be paginated by `content.per_page` and the pagination links SHALL keep `q`. The page SHALL be rendered through the theme with the template candidate `search/results` and the hook `search`, inside the site shell, and SHALL ask search engines not to index it. The template SHALL receive the search term, the list of results (title, address, type kind, section title, publication date, plain-text excerpt, and the title and the excerpt split into parts that mark the occurrences of the term) and the paginator. A search with no matches SHALL answer 200 with an empty list.

#### Scenario: Paginated results
- **WHEN** more nodes match than fit on one page
- **THEN** the paginator links to the other pages contain the same `q`

#### Scenario: No matches
- **WHEN** a guest searches a term that matches no visible node
- **THEN** the response 200 is rendered with an empty result list

#### Scenario: Not indexed
- **WHEN** a guest opens any search page
- **THEN** the document tells robots not to index it

#### Scenario: Excerpt is plain text
- **WHEN** a matching `richtext` field contains HTML tags
- **THEN** the excerpt of the result contains no tags and is not longer than `content.meta_description_max`

### Requirement: Search excerpt around the match
The excerpt of a result SHALL show the place where the term was found. It SHALL be taken from the first text field of the node (in field order, the title excluded) whose plain text contains the term case-insensitively, and SHALL be a part of that text around the first occurrence, not longer than `content.meta_description_max` characters, cut at word boundaries, with "…" at each edge where the text continues. When no text field contains the term in its plain text (for example the node matched by its title only, or the database matched something the plain-text comparison does not), the excerpt SHALL be the start of the first non-empty text value, as before. A node without a non-empty text value other than the title SHALL have no excerpt.

#### Scenario: Match deep in a long text
- **WHEN** a record has a `body` of 1000 characters with the word `lamp` after the 500th character and a guest searches `lamp`
- **THEN** the excerpt of its result contains `lamp` and is not longer than `content.meta_description_max`, and begins and ends with "…"

#### Scenario: Match in a later field
- **WHEN** the first text field of a record does not contain the term and its second text field does
- **THEN** the excerpt is taken from the second field

#### Scenario: Match at the start of the text
- **WHEN** the term is in the first words of the text
- **THEN** the excerpt starts at the beginning of the text without a leading "…"

#### Scenario: Match in the title only
- **WHEN** a node matches by its title and no text field contains the term
- **THEN** the excerpt is the start of the first non-empty text value

#### Scenario: Multibyte text
- **WHEN** the text around the match holds multibyte letters
- **THEN** the cut never breaks a letter

### Requirement: Search result parts for highlighting
Every result SHALL carry its title and its excerpt additionally as a list of parts, each part holding a piece of the text and a flag telling whether the piece is an occurrence of the term. The parts in order SHALL give back exactly the plain title and the plain excerpt. Every case-insensitive occurrence of the term in the text SHALL be a separate flagged part. When the term does not occur in the text, or the text is empty, there SHALL be no flagged part. The parts SHALL hold plain text only, never markup, so that a template escapes them when it prints them.

#### Scenario: Occurrences in the title
- **WHEN** a page has the title "Lamp museum of the lamp" and a guest searches `lamp`
- **THEN** the title parts are "Lamp" (flagged), " museum of the " and "lamp" (flagged)

#### Scenario: Case is kept
- **WHEN** the text has the term in another case than the typed one
- **THEN** the flagged part holds the text as it is written in the node

#### Scenario: No occurrence
- **WHEN** the title does not contain the term
- **THEN** the title parts are one unflagged part equal to the title

#### Scenario: Literal characters
- **WHEN** a guest searches `100%` or a term with regular-expression characters such as `a.b` or `(x`
- **THEN** only the literal text is flagged and no error occurs

#### Scenario: Parts rebuild the text
- **WHEN** the parts of a title or an excerpt are joined
- **THEN** the result equals the plain title or the plain excerpt

### Requirement: Site search is rate limited
The system SHALL limit requests to the site search page per IP address. A request over the limit SHALL be answered with status 429 and SHALL NOT query the nodes. Requests within the limit SHALL behave as before, and the other public pages SHALL NOT be affected.

#### Scenario: Within the limit
- **WHEN** a guest searches a few times in a minute
- **THEN** every search is answered with its results

#### Scenario: Over the limit
- **WHEN** one IP address sends more search requests in a minute than the limit allows
- **THEN** the extra requests are answered with status 429 without querying nodes, and other pages of the site still open

#### Scenario: Another IP address
- **WHEN** one IP address is over the search limit and a guest from another IP address searches
- **THEN** the search of the second guest is answered as usual
