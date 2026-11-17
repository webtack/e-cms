# Public site

The public routes, the visibility of nodes, and the data that theme templates receive ([site-themes.md](site-themes.md)). The responses are regular HTML from Blade, without Inertia/React.

## Routes

They are in the package file `routes/public.php`, which the provider loads once the application has booted, so after every other route, including the host's ([package-bootstrap.md](package-bootstrap.md#routes-and-their-order)). They exist only when `ecms.public_site` is on (the default). The service routes (the admin panel, `/login`, `/logout`, `/locale`, `/email/**`, `/up`, `/storage/**`, `/vendor/ecms/**`) always win: the priority is **service path → page/type → 404**. `/search` and `/sitemap` are service paths that are pages of the site itself ([Site search](#site-search), [type-sitemap.md](type-sitemap.md)); `/sitemap.xml` is the XML sitemap. The list of reserved first segments is `content.reserved_paths` ([content-model.md](content-model.md#reserved-paths)).

| Address | Controller | Response |
| --- | --- | --- |
| `/` | `HomeController` | the `home` template |
| `/search` | `SearchController` (route `public.search`) | the `search/results` template: the site search ([below](#site-search)) |
| `/sitemap` | `SitemapController` (route `public.sitemap`) | the `sitemap/page` template: the web sitemap ([type-sitemap.md](type-sitemap.md#web-sitemap)) |
| `/sitemap.xml` | `SitemapXmlController` (route `public.sitemap.xml`) | the XML sitemap, `application/xml`, not themed ([type-sitemap.md](type-sitemap.md#xml-sitemap)) |
| `/{path}` (one or more segments) | `PublicNodeController@show` (route `public.show`) | a page, a type list, or a record of a type, resolved in the order below; otherwise the themed 404 |
| everything else (an empty segment, an address below `/search` or `/sitemap`, characters outside `[a-z0-9_-]`, file names) | `NotFoundController` (the handler of `NotFoundHttpException`) | `errors/404`, status 404 |

**Resolution order** of `/{path}` (`PublicNodeController::show()`): (1) with one segment, a visible page (`kind = page`) with this slug; (2) the `content` type whose slug equals the **whole path**, shown as a list (a type slug may have several segments, for example `/common/institutions`); (3) with two or more segments, the type whose slug equals the path without its last segment, and its visible node with the last segment as the slug (`/common/institutions/hello`); (4) the themed 404. A type list wins over a node when one address could mean both ([content-model.md](content-model.md#type-slug) keeps new data from doing so). A part of a nested slug (`/common` when only `common/institutions` exists) is 404, and so is a node of another type or an invisible node. The depth is not limited by the route; an unknown deep path costs at most one lookup of a page and two lookups of a type. The reserved check applies to the first segment.

Every segment must match `[a-z0-9_-]+` (the legacy slug format, `ContentNode::LEGACY_SLUG_PATTERN`, wider than the strict slug so that imported addresses such as `/news/volnovaha---x` or `/news/etno_wave-x` work; see [Legacy slugs](content-model.md#node-slug)). Lookups are exact, so an unknown segment still ends in the 404, and uppercase letters or spaces match no route. The first must not be reserved, so `/favicon.ico` or `GET /logout` do not reach the controller: the first has no route and gets the site's 404, the second keeps the usual 405. `/news//hello` (an empty segment) has no route either. The controller additionally filters out a reserved first segment (in case such a slug string is in the DB, bypassing validation) with a standard 404.

There is no separate `Route::fallback`: a GET route "for everything" would change 404 to 405 for any non-GET request to an unknown URL. (The route `/{path}` accepts every slug-shaped address of any depth, so a non-GET request to such an address answers 405, as it already did for one and two segments.) Instead, the package provider adds a renderer (only with `ecms.public_site`) that serves the template `errors/404` for `NotFoundHttpException` only for browser GET/HEAD requests outside the service prefixes (`NotFoundController::render()`); the admin panel (`/admin/unknown`), `/storage/**`, JSON requests, and other verbs keep the standard 404. Blocks (`kind = block`) have no addresses of their own: `/block-footer` gives 404, and a block is inserted by the template through an explicit call `@block('block-footer')` ([theme-templates.md](theme-templates.md#blocks)).

## Language prefix

With multilingual mode on ([content-languages.md](content-languages.md#multilingual-switch)) every public address is also available in each **active** additional language under the language code as the first segment: `/en` (home), `/en/about-us`, `/en/news/story`, `/en/search?q=lamp`, `/en/sitemap`. The slug of a page or record is one for all languages; the base language has no prefix.

The prefix is handled, only when `ecms.public_site` is on, by the global middleware (prepended by the package provider, not by the host's `bootstrap/app.php`) `Webtack\Ecms\Http\Middleware\ResolvePublicLanguage`, which runs **before routing**, so every route, controller and `route:cache` keep working on the unprefixed path:

| Address | Result |
| --- | --- |
| `/en/about-us`, `/EN/about-us` | the request is re-initialised as `/about-us` and read in `en` (the code is matched case-insensitively) |
| `/uk/about-us?x=1` with the base code `uk` | `301` to `/about-us?x=1` (one address per page in the base language) |
| `/xx/about-us`, `/pl/about-us` with `pl` inactive, `/en/about-us` while multilingual mode is off | an ordinary address: the site 404 |
| any address while the package tables do not exist yet (before the migrations ran) | untouched: the middleware does not query the database, so the host answers its own `/api/x` |
| `/en/admin/dashboard`, `/en/login`, `/en/sitemap.xml` | the prefix is **not** stripped before a service path ([Routes](#routes)): the site 404, the admin panel is never served. Only `/search` and `/sitemap` may carry a prefix |

The first check rejects any first segment that cannot be a language code (length, character set), and a single-language site does no extra work: the active codes are a cached list (`Webtack\Ecms\Content\PublicLanguages`, dropped whenever a language, the switch or the base code changes). No cookie, no `Accept-Language`, no admin locale changes the reading language: the same URL gives the same page to a crawler and a visitor.

**Reading language and fallback.** The middleware records the language in the scoped `Webtack\Ecms\Content\LocalizedUrl` (`lang()` is `default` for the base language, else the code) and switches `PresentContext` to it with the fallback on. A node that is visible is served in every language: each value missing in the language is replaced by the base-language value of the **same field** (a multiple field and a composed value as a whole), see [content-values.md](content-values.md#reading-with-fallback). The same fallback applies to term names, menu items, field labels, site settings, file names and descriptions, type section and meta values. Visibility never depends on the language. The admin panel, writers and APIs keep the strict reading.

**Document and interface language.** `$lang` (`<html lang>`) is the code of the reading language. The interface strings (`__()`) follow the reading language when the host or the package has `{code}.json` ([admin-i18n.md](admin-i18n.md#override-rule)), else the base language's locale (the base code when it has such a file, else `config('app.locale')`); with the switch off nothing changes (`config('app.locale')`). `SetLocale` does this for `public.*` routes.

**Language codes and the host's routes.** A language code is a reserved first segment of the whole application: adding a language is refused when the code equals the first segment of a route of the host (`/en/contact` rules out `en`), with the reason `route` shown on the languages page. The host's own `/en/...` routes would otherwise stop working, because the prefix is stripped before routing.

**Prefixing internal links.** `LocalizedUrl::path('/about-us')` returns `/en/about-us` on an English page and the address unchanged on a base-language page; absolute URLs, anchors, `mailto:`, service paths and addresses that already name a language are never prefixed.

## Site search


`GET /search?q=…&page=N` (`SearchController`, route `public.search`) searches the whole site. The first segment `search` is reserved (`content.reserved_paths`, [content-model.md](content-model.md#reserved-paths)), so no page slug and no `content` type slug starting with `search` can be saved, and `/search` is never served as a page, a type list or a node. The route is registered before `/{path}`; only the exact address `/search` is the search page, and `/search/anything` gets the themed 404 (`PublicPaths::SITE_PATHS` tells `NotFoundController` that `search` is a site page, not a service prefix).

**Rules** (`Webtack\Ecms\Content\SiteSearch`, independent of HTTP and of the theme):

- **Scope.** Visible nodes ([Visibility](#visibility)) of kinds `page` and `content`; blocks and records of a type without a slug (no public address) are never returned. A node matches when its `title` or a value of one of its text fields (field types `string`, `text`, `richtext`) contains the whole term. Other field types (`url`, files, references, ...) and the values of the type itself (`section_*`, `meta_*` of the type) are not searched.
- **Language.** The reading language of the request (`PresentContext::$lang`), without fallback to the base language: a node that has the term only in its base-language value is not found in another language.
- **Match.** A `LIKE '%term%'` condition, case-insensitive as far as the database collation is (MySQL: yes; SQLite in tests: ASCII only). `%` and `_` in the term are matched literally (escape character `!`, as in [content-querying.md](content-querying.md#search)). A node with several matching values appears once. There is no ranking: the order is `published_at` descending, then `id` descending.
- **Term.** `q` is trimmed and cut to `content.search.max_length` (100) characters; a `q` that is not a string (`q[]=x`) is empty. An empty term shows the page with the form only, a term shorter than `content.search.min_length` (3) characters shows the form with a hint; neither runs a node query, and both answer 200.
- **Pagination.** `content.per_page` results per page; the paginator links keep `q`. No matches answer 200 with an empty list.
- **Indexing.** Every search page carries the header `X-Robots-Tag: noindex` (a header, not a `<meta>` tag, so it works with every theme and shell).
- **Rate limit.** The route uses `throttle:search`: `limits.search_per_minute` (default 60, `.env` `LIMIT_SEARCH_PER_MINUTE`, `config/limits.php`) requests per minute per IP address. A request over the limit is answered with the framework's 429 response, without querying nodes; other pages are not affected. Per-IP limits need the real client address: behind a reverse proxy configure trusted proxies, otherwise all visitors share one IP (see [admin-auth](admin-auth.md#rate-limiting)).
- **Cost.** The number of queries does not depend on the number of results: one page of nodes with the total, one read of the searchable fields of the shown types, one batch read of the values per shown type, and one read of the section titles. `LIKE '%…%'` uses no index (the same trade-off as the per-type search), which the minimum and the maximum term length bound.

**Template variables** of `search/results` (besides the [shared ones](#layout-and-template-data)):

| Variable | Value |
| --- | --- |
| `$query` | the clean term (trimmed and cut), `''` when there is none |
| `$tooShort` | `true` when the term is not empty but shorter than `$minLength` |
| `$minLength` | `content.search.min_length`, for the hint text |
| `$results` | a paginator of `Webtack\Ecms\Content\SearchResult` that keeps `q` in its links, or `null` while there is no query (empty or too short term) |

A `SearchResult` has `title` (the slug when the node has no title in the reading language), `url` (`/{slug}` for a page, `/{type slug}/{node slug}` for a record), `kind` (`page` or `content`), `sectionTitle` (the public title of the type, [TypeSection](#layout-and-template-data); `null` for a page), `publishedAt` (the node's `published_at`), `excerpt` (plain text without tags, not longer than `content.meta_description_max`; `null` when the node has no non-empty text value other than the title) and the parts `titleParts` and `excerptParts`.

**Excerpt around the match.** The excerpt is taken from the first text field (in field order, the title excluded) whose plain text contains the term (literal, case-insensitive). When the text does not fit in `content.meta_description_max`, it is a window around the first occurrence: it starts about a third of the maximum before the match, both edges are moved to word boundaries, and "…" marks each edge where the text continues (it counts in the maximum). A text that fits is returned whole. When no text field contains the term (the node matched by its title only, or the database collation matched what the PHP comparison does not, such as diacritics under `utf8mb4_unicode_ci`, or text inside HTML markup), the excerpt is the start of the first non-empty text value and nothing is highlighted: a known limit, the result is still found. No extra queries: the values of all text fields of the shown nodes are already read in one batch.

**Parts for highlighting.** `titleParts` and `excerptParts` are lists of `['text' => string, 'match' => bool]` built by `Webtack\Ecms\Content\TermHighlighter` from the final `title` and `excerpt`. Every case-insensitive occurrence of the term is a separate part with `match` true and the text as written in the node; the parts joined give back the text exactly. With no occurrence the list is one unflagged part; `excerptParts` is `[]` when there is no excerpt. Parts are plain text, never markup, so a template escapes them ([example](theme-templates.md#site-search-results)).

The `default` theme has no `search/results` template (its layout has no search form); a theme that wants the search adds the template ([theme-templates.md](theme-templates.md#site-search-results)). Without it `GET /search` answers with the missing-template error, like any route whose template is absent. Tests: `tests/Feature/Public/SiteSearchTest.php`, `tests/Feature/Public/SearchRouteTest.php`, `tests/Unit/Content/TermHighlighterTest.php`.

## Visibility

Only a visible node is shown: `is_active`, `published_at <= now()`, `unpublished_at` is empty or in the future (`ContentNode::scopeVisible()`, `findBySlug(onlyVisible: true)`, `NodeQueryParams(onlyVisible: true)`). A disabled, scheduled, expired, non-existent node, and a node of another kind or type give 404.

## Layout and template data

Every response is `html` (the document: language, `<title>`, theme assets) → `layout` (the header, the footer) → the page content (`@extends('theme::layout')`). The controllers do not call `view()`, only `ThemeManager::render()`; the shared variables are collected by `Webtack\Ecms\Theme\PublicData`:

| Variable | Value |
| --- | --- |
| `$site` | `SiteSettingsView`: the site settings (name, logos, contacts) in the reading language, each missing text read from the default ones ([Language prefix](#language-prefix)) |
| `$lang` | the code of the document language for `<html lang>`: the reading language of the address; for the base language the saved base code, and the application locale on a single-language site |
| `$theme` | the name of the active theme |
| `$title` | the document title **as is, without a suffix**: `meta.title` (the node's own `meta_title`, the selected field of the type, the node's `title`, the type's `meta_title`, or the type's Section title), otherwise the node's `title`; without a node — the site name. The suffix " — {site name}" is added by the theme with a hook or in its own `layout` |
| `$meta` | always an array `['title', 'description', 'image']` (the image URL; `null` if no source gave a value). Collected by `Webtack\Ecms\Theme\PageMeta` along the chain node → type fields → the type's `meta_*` → site settings ([below](#page-meta-data)); `default` outputs `description` and `og:image` in `<head>` |
| `$logoUrl` | the URL of the small logo or `null` |
| `$assets` | the Vite entry points of the active theme |
| `$node`, `$type`, `$values` | the node, its type, and its values (`ContentValueReader::load()`) for the page and details; `$node->views` is the integer view counter, as it was before the current visit is counted |
| `$fields` | the fields the template shows, `key → ContentField`: `page_fields` for the page and details, `list_fields` without `title` for the list cards ([fields on pages](#page-and-list-fields)) |
| `$type`, `$paginator`, `$pagination` | the type, the page of visible nodes, and whether to show the paginator for the list |
| `$period`, `$hasArchive` | list only: which list is shown (`'current'` or `'archive'`) and whether the type has an archive ([archive period](#archive-period-of-a-list)) |
| `$sectionTitle` | list only: the public title of the type, a plain string (the type's `section_title` in the reading language, then in the base language, then `ContentType::name`; an empty value counts as absent). Provided by `Webtack\Ecms\Theme\TypeSection`; the `content/list` templates of both shipped themes print it instead of `$type->name`; list also gets `$sectionDescription` (sanitized HTML or null) and `$sectionCover` (URL or null), printed by both `content/list` templates ([content-model.md](content-model.md#system-fields)) |
| `$breadcrumbs` | the trail of the page: a list of `['title', 'url']`, `url` absolute, `null` on the last item (the current page); empty on the home page and 404. See [theme-templates.md](theme-templates.md#breadcrumbs) |
| `$breadcrumbsJsonLd` | the same trail as a ready `BreadcrumbList` JSON string, or `null` when the trail has fewer than two items |

The templates output fields by the directives `@field`/`@fields`, list cards — `@preview`, blocks — `@block`, menus — `@menu`: [theme-templates.md](theme-templates.md). The header of the `layout` of the theme `default` shows the menu `main` if it exists ([theme-templates.md](theme-templates.md#the-main-menu-in-the-header)).

## View counter

Every successful `GET` of the page of a node of kind `content` (`/{type.slug}/{node.slug}`) or `page` (`/{slug}`) adds one to `content_nodes.views` through `Webtack\Ecms\Content\ViewCounter`, called by `PublicNodeController` **after** the response is built (a page that failed to render is not counted). Type lists, 404 responses, invisible nodes, and blocks (no address) never count.

By default every request that passes the rules below counts, signed-in users and repeated visits included. A request is **not** counted when: counting is switched off (`content.views.enabled`, env `CONTENT_VIEWS_ENABLED`, default on); the method is not `GET` (`HEAD`, `OPTIONS`, ...); a user is signed in (any role) while `content.views.count_authenticated` (env `CONTENT_VIEWS_COUNT_AUTHENTICATED`, default `true`) is `false`; the `User-Agent` is empty or contains, case-insensitively, a substring of `content.views.bot_agents` (`bot`, `crawl`, `spider`, `slurp`, `facebookexternalhit`, `preview`, `headless`, `curl`, `wget`, `python-requests`, `monitor` by default; false positives such as an agent containing "bot" are accepted). Per-visitor deduplication is off by default: `content.views.ttl` (env `CONTENT_VIEWS_TTL`, seconds) is `0`, and the cache is not used at all. With a positive `ttl` (for example `86400`) a visitor, the pair IP + `User-Agent` (no cookies, so no consent banner is needed), is counted once per node per that period using an atomic `Cache::add` key `node-view:{id}:{sha1(ip|ua)}`. The cache store must then persist between requests (`CACHE_STORE=database` does; the `array` store used by tests lives per process), and visitors behind one NAT with the same browser count as one.

The write is one synchronous `UPDATE content_nodes SET views = views + 1` ([content-model.md](content-model.md)) that leaves `updated_at` alone. Failures never change the response: if the cache fails (only possible with `ttl` > 0) the view is not counted (an undercount is better than an inflated number), if the update fails it is only logged at `warning`. A CDN or full-page cache in front of the app would hide visits; none is used now. The counter is shown in the admin table and can sort lists ([admin-content-nodes.md](admin-content-nodes.md), [content-querying.md](content-querying.md#sorting-by-service-attributes)).

## Page meta data

The `<head>` of every public page (home, page, list and node details, 404) gets `<title>`, `meta description`, and `og:image`. Each value is taken **separately** from the first available source (the class `Webtack\Ecms\Theme\PageMeta`, called from `PublicData::make()`):

| Value | 1. Node | 2. Type field (`settings.public.meta`) | 3. Type meta (the "SEO" tab) | 4. Site settings |
| --- | --- | --- | --- | --- |
| title | `meta_title` | `title`: a `string` field; then (step 2a) the node's `title` | the type's `meta_title`; then its Section title (`section_title`, the "Display" tab; reading language, then base) | none (in `$title` — the site name, when the node's `title` is absent too) |
| description | `meta_description` | `description`: a `text` or `richtext` field | the type's `meta_description` | the site description |
| image | `meta_image` | `image`: an `image` or `youtube` field | the type's `meta_image` | the large logo, and if it is absent — the small one |

- The fields are selected by the administrator in the "Meta data" block of the "SEO" tab of the `content` and `page` types ([admin-content-types.md](admin-content-types.md#meta-data-block-on-the-seo-tab)); the node's `title` cannot be selected in the block — it is already in the title chain after the selected field. The type's own `meta_*` values are entered on the same tab and are read from the DB for the base language (`ContentValueReader::loadForType()`), only when the previous sources gave no value, and only for types that hold them (not for blocks). Blocks have no meta data.
- A page without a node (the list of nodes of a type) has sources 3 and 4 (steps 1 and 2 are empty); the home page and 404 — only the fourth.
- **Title.** The order: the node's `meta_title` → the selected `string` field → the node's `title` → the type's `meta_title` → the type's Section title → (in `$title`) the site name. Thus the type's `meta_title` and, when it is empty, the Section title apply where there is no node (the type list), and for a node with a `title` they come after it. The administrative `name` of the type is never a title source; a type with neither `meta_title` nor Section title keeps the site name. The `meta_title` is read in the base language, the Section-title fallback in the reading language. `<title>` does not append the site name.
- The type description is cleaned in the same way as field text: spaces are compressed, and the length is cut to `content.meta_description_max`.
- A multiple field gives the **first** value. An empty value (an empty string, only spaces, no file) is considered absent and passes the choice to the next source.
- The HTML is stripped from a field's description (`HtmlSanitizer::text()`: tags removed, entities decoded, spaces compressed), and the text is cut to `content.meta_description_max` (160) characters at a word boundary with "…" at the end; the site description and the node's `meta_description` are not cut. The title is not cut.
- `youtube` gives the address of the saved screenshot of the video (`screen`); the site does not contact YouTube while showing the page. A video without a screenshot gives no value, and the next source is taken.
- A field that was deleted or whose type changed is ignored (`PublicDisplaySettings::clean()`), and the next source is taken. When there is no source at all, the tag `meta description` / `og:image` is not output.

Coverage: `tests/Feature/Public/PageMetaTest.php` (including the type meta), `PublicRoutesTest`, e2e `e2e/public-site.spec.js` (the `<head>` of a list with the type's `meta_description`).

## Page and list fields

What to show is defined by the "Public display" section of the type (`settings.public`, read by `PublicDisplaySettings::resolve()`), and `PublicNodeController` only passes the result to the template:

| Setting | Action on the site |
| --- | --- |
| `page_fields` | the fields of a page (`kind = page`) and of the details of a `content` node, in the saved order; no key — all added fields of the type |
| `list_fields` | the card fields in the list after the link title (`title` is always there); no key — only `title` |
| `sort` | the sorting of the list (no key — the newest first, `published_at desc`); a service attribute is allowed, including `views` for "popular first" (`{field: "views", direction: "desc"}`) |
| `per_page` | nodes per page (`0` — all, up to `content.max_all`) |
| `pagination` | whether to show the paginator (with `per_page = 0` there is never one) |
| `archive` | splits the list into the main list and the archive by one date ([below](#archive-period-of-a-list)); no key — the list is not split |

`title`, the node's service attributes, `meta_*`, and `section_*` are not output as regular fields. The values of list cards are read by one query per page of the paginator, and references (terms, related nodes) are resolved in batches, so the number of queries does not depend on the number of cards ([content-values.md](content-values.md#public-presentation)).

## Archive period of a list

A `content` type whose public settings have an `archive` (`{field, sort}`, [content-querying.md](content-querying.md#public-type-settings)) splits its list by the archive date, an added `date` / `datetime` field, `created_at`, or `unpublished_at`:

| Address | Shows | Sort |
| --- | --- | --- |
| `/{type slug}` | the visible records that are **not past** | the main `sort` |
| `/{type slug}?period=archive` | the visible records that are **past** | `archive.sort` |

- **Boundary.** "Past" is decided against the start of the current day in UTC (`now()->startOfDay()`, computed by `PublicNodeController::list()`), so a record dated today, even at `00:00`, stays in the main list during the whole day. The exact rules per date kind (a multiple field, an empty value, `unpublished_at`) are in [Period selection](content-querying.md#period-selection); the two lists are complements, and visibility ([above](#visibility)) is applied to both.
- **Paging.** Both lists use the type's `per_page` and the paginator; the `page` parameter works in both, and the paginator links of the archive keep `period=archive`.
- **Other values.** A `period` value other than `archive` gives the main list without an error. A type without an archive ignores the parameter and shows the same list as without it.
- **Template variables** (`content/list`): `$period` is `'current'` or `'archive'` (the list shown), `$hasArchive` is `true` when the type has an archive. The core renders no switch itself; a theme builds a link to the archive from these two variables ([theme-dcculture.md](theme-dcculture.md)).

Coverage: `tests/Feature/Public/PublicRoutesTest.php` (the archive period).

## Template candidates

From specific to general ([site-themes.md](site-themes.md#candidate-cascade)); `{t}` — the type key, `{id}` — the numeric `id` of the node:

| Route | Candidates | Hook |
| --- | --- | --- |
| Home | `home` | `home` |
| Page | `{t}/page`, `pages/page-{t}--{id}`, `pages/page-{t}`, `pages/page` | `page` |
| List | `{t}/list`, `content/list` | `content.list` |
| Card of a node in the list | `{t}/preview--{id}`, `{t}/preview`, `content/preview` | `content.preview` |
| Details | `{t}/details--{id}`, `{t}/details`, `content/details` | `content.details` |
| Site search | `search/results` | `search` |
| Web sitemap | `sitemap/page` | `sitemap` |
| 404 | `errors/404` | `errors.404` |

Tests: `tests/Feature/Public/PublicRoutesTest.php`; e2e — `e2e/public-site.spec.js` ([e2e-testing.md](e2e-testing.md)).
