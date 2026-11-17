# Design

## Context

See `proposal.md` for the motivation. Current state:

- `routes/web.php` registers `/` and `/{path}` last; the regex of `{path}` excludes the first segments of `PublicPaths::reserved()` (`config('content.reserved_paths')`). The reserved list is also what `PublicPaths` and `AvailablePublicPath` use to reject slugs, so one config value covers the validation and the routing ([public-site.md](../../../docs/public-site.md), [content-model.md](../../../docs/content-model.md#reserved-paths)).
- `ContentNodeQuery` searches one type only (`OR` of `LIKE '%…%' ESCAPE '!'` over the type's text fields, `EXISTS` subqueries on `content_values_*`). It needs a `ContentType`, so it cannot answer a whole-site query.
- Values live in `content_values_string` / `content_values_text` (one row per node, field, language, weight; `lang = 'default'` for the base language). `title` is an ordinary `string` value of the node. Type-owned values (`section_*`, `meta_*` of the type) have `content_node_id = NULL`, so joining on the node id never reads them.
- Public controllers extend `PublicController` and answer through `ThemeManager::render()` (candidate cascade + hooks); the shared variables come from `PublicData::make()`. The reading language of the request is `PresentContext::$lang` (scoped service).
- The `dcculture` header already prints `partials/search-field` (field `q`, `action="#"`) in the header and in the search modal; `search-modal.js` only opens and closes the modal, so a plain `GET` form needs no script change.

## Goals / Non-Goals

**Goals:**

- One service, `App\Content\SiteSearch`, that answers "which visible `page`/`content` nodes contain this term", independent from HTTP and from the theme, so the implementation can later move to a full-text index without touching the controller or templates.
- A fixed number of queries per request: one for the page of ids (with the total), one batch read of the shown fields, one read of the type section titles; never per result.

**Non-Goals:**

- Relevance ranking, stemming, morphology, highlighting, search suggestions, filters by type, or search inside blocks.
- Full-text indexes or a separate search index table.
- Changes to the `default` theme (its layout has no search form) and to the admin panel.
- Counting views for the search page.

## Decisions

**1. `LIKE` over the existing value tables (variant A).** The service builds one query on `content_nodes` joined to `content_types` with `kind IN ('page','content')`, `visible()`, and for `content` types a non-empty `slug`; the term condition is `EXISTS` on `content_values_string` OR `content_values_text` where `content_node_id = content_nodes.id`, `lang = <reading lang>`, `field_key` is one of the searchable fields of the node's type, and `value LIKE ? ESCAPE '!'`. This reuses the escaping and escape character of `ContentNodeQuery` and needs no migration. Alternatives: a `FULLTEXT` index (MySQL only, breaks the SQLite tests, poor Ukrainian morphology) and an index table (the best for ranking but a separate change); both stay possible behind `SiteSearch`.

**2. Which fields are searched.** `title` plus every field of the node's type whose field type is `searchable()` (`string`, `text`, `richtext`); the keys are resolved from `content_fields` joined by `content_type`, not hard-coded, and `settings.list.search` of a type is not used (it narrows the per-type admin/list search, not the site search). Fields of types `url`, `youtube`, files, references and so on are not searched. Because `title` is a normal `string` field of every page/content type, it needs no special case beyond being part of the key list.

**3. Language: reading language, no fallback.** The condition uses `PresentContext::$lang`, the same language the page shows, matching the "no fallback" rule of `ContentNodeQuery`. A monolingual site reads `default`. The shown title and excerpt are read in the same language through `ContentValueReader::load(nodes, ['title', …], lang)`.

**4. Visibility and address.** `ContentNode::scopeVisible()` is applied. The URL of a result is `/{slug}` for a `page` and `/{type.slug}/{node.slug}` for a `content` node; types with no slug are excluded in SQL (so the total and the pages stay correct) instead of being filtered in PHP. Result order is `published_at desc, id desc` (no ranking).

**5. Result data and excerpt.** `SiteSearch` returns a `LengthAwarePaginator` of small value objects (`title`, `url`, `kind`, `sectionTitle` or `null`, `excerpt`), not models, so templates do not depend on how the search works. The excerpt is the first non-empty searched text value of the node (other than `title`) in the reading language, passed through `HtmlSanitizer::text()` and cut to `content.meta_description_max` at a word boundary like `PageMeta`; it starts from the beginning of the text, not around the match (a match-centred snippet is deferred). The section title comes from `TypeSection::title()`, one lookup per distinct type; pages have none.

**6. Term handling.** `SiteSearch` takes the already-clean term: the controller trims `q`, cuts it to 100 characters (`mb_substr`), and treats anything under 3 characters (`mb_strlen`) as "no query" without calling the service. The minimum (3) and maximum (100) are config values `content.search.min_length` and `content.search.max_length`, so the controller, the hint text and tests share one source. A non-string `q` (for example `q[]=x`) is treated as empty.

**7. Route and reservation.** `'search'` is appended to `content.reserved_paths` (so `PublicPaths`, `AvailablePublicPath`, the `{path}` regex and the controller guard all apply with no new code). `Route::get('/search', SearchController::class)->name('public.search')` is registered before `/{path}`; since `/{path}` now excludes the first segment `search`, `/search/anything` matches no route and ends in the site 404 through `NotFoundController`, as the spec requires.

**8. Controller and template contract.** `SearchController` extends `PublicController`, builds `['query' => $term, 'tooShort' => bool, 'minLength' => 3, 'results' => paginator|null]`, and calls `respond(['search/results'], 'search', …)` with the translated word "Search" as the record title. The paginator keeps `q` in its links (`appends(['q' => $term])`). `noindex` is sent as the response header `X-Robots-Tag: noindex` set by the controller, not as a `<meta>` tag: it works with every theme, needs no change in `html.blade.php` (shared with the `default` theme), and cannot be lost by a theme's own document shell. Alternative: a `$robots` variable printed in `<head>`, rejected because it changes a shared `default` file.

**9. Theme `dcculture`.** `partials/search-field.blade.php` becomes `action="/search" method="get"` and takes an optional `$value` (default empty) for the field's `value`, so the header (no value) and the results page (`'value' => $query`) share one partial. New `views/search/results.blade.php` extends `theme::layout` and prints the heading, the form, then the cards, the hint, or the empty state; the card is `partials/search-result.blade.php`. Styles go in the existing theme stylesheets under `themes/dcculture/assets`; texts in `lang/{uk,en}/dcculture.php` under `search`. Documentation: [public-site.md](../../../docs/public-site.md) (route, variables, candidates), [theme-templates.md](../../../docs/theme-templates.md), [theme-dcculture.md](../../../docs/theme-dcculture.md), [content-model.md](../../../docs/content-model.md#reserved-paths).

## Risks / Trade-offs

- [`LIKE '%x%'` does not use an index and scans the value tables] → acceptable for the current data size, same trade-off as the per-type search; the 3-character minimum and the 100-character cap bound the cost; `SiteSearch` is isolated so it can be replaced by `FULLTEXT` or an index table.
- [Case-insensitivity depends on the database: MySQL's collation is case-insensitive, SQLite's `LIKE` only for ASCII] → tests use ASCII terms for case checks; production is MySQL.
- [Existing data may already use the slug `search` or a type slug starting with `search/`, which becomes unreachable] → check the database before applying; rename such records.
- [The excerpt starts from the beginning of the text, so it may not show the match] → accepted for the first version; a match-centred snippet can replace it inside `SiteSearch` without changing templates.
- [Multiple values of one field and translated rows can produce several matching rows per node] → the condition is `EXISTS` per node, so a node appears once.
- [Search is not rate-limited] → the term cap and the minimum length bound a single request; rate limiting is out of scope.

## Open Questions

- Whether to add a "search in site" toggle per type later (not needed now: all `page` and `content` types are searched).
